<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderWatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * La salle de controle.
 *
 * Ce que l'administration doit voir sans le chercher : qui s'occupe de quoi,
 * et ce qui ne bouge pas. Une liste dit ce qui existe ; l'alerte dit ce qui
 * coince — et c'est la seule des deux qui evite un client en colere.
 */
class AdminControlRoomTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    /** Commande payee, avec un creneau a l'heure voulue. */
    private function paidOrderAt(string $slotStart, array $overrides = []): array
    {
        [$zone] = $this->createZoneWithSlot();
        [$market] = $this->createMarketFor($zone);
        $slot = $this->createHourlySlot($zone, $slotStart);

        $client = $this->createClient(['name' => 'Awa Hounkpatin']);
        $address = $this->createAddressFor($client, $zone);
        $product = $this->createProduct();

        Sanctum::actingAs($client, ['*']);
        $orderId = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [['type' => 'product', 'product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()->json('data.id');

        $order = Order::find($orderId);
        $order->update(array_merge(['payment_status' => 'completed'], $overrides));

        return [$order->fresh(), $market, $zone];
    }

    public function test_the_day_view_names_who_is_on_each_order(): void
    {
        [$order, $market] = $this->paidOrderAt('10:00');
        $preparateur = $this->createPreparateurFor($market);
        $preparateur->update(['name' => 'Aïssatou Dossou']);
        $order->update(['preparateur_id' => $preparateur->id, 'status' => OrderStatus::PREPARING]);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $row = $this->getJson('/api/v1/admin/orders')->assertOk()->json('data.0');

        // L'intérêt de cet écran est de pouvoir appeler la personne concernée.
        $this->assertSame('Aïssatou Dossou', $row['preparateur']['name']);
        $this->assertSame($preparateur->phone, $row['preparateur']['phone']);
        $this->assertSame('Awa Hounkpatin', $row['client']['name']);
    }

    public function test_a_paid_order_nobody_took_is_urgent_when_the_slot_is_close(): void
    {
        // Le créneau démarre dans 40 minutes et personne ne l'a prise.
        [$order] = $this->paidOrderAt('10:00');
        $order->update(['delivery_date' => now()->toDateString()]);

        $watch = app(OrderWatchService::class);
        $attention = $watch->attention(
            $order->fresh()->load('deliverySlot'),
            \Illuminate\Support\Carbon::parse(now()->toDateString().' 09:20')
        );

        $this->assertSame('urgent', $attention['level']);
        $this->assertStringContainsString('personne ne l’a prise', $attention['reason']);
    }

    public function test_the_same_order_is_calm_when_the_slot_is_tomorrow(): void
    {
        [$order] = $this->paidOrderAt('10:00');

        // Un seuil en heures aurait alerté ici aussi. Le temps qui reste avant
        // le créneau est le seul signal juste.
        $this->assertNull(app(OrderWatchService::class)->attention($order->load('deliverySlot')));
    }

    public function test_a_basket_ageing_at_the_depot_is_flagged(): void
    {
        [$order] = $this->paidOrderAt('23:00', [
            'status' => OrderStatus::READY,
            'ready_at' => now()->subMinutes(90),
        ]);

        $attention = app(OrderWatchService::class)->attention($order->fresh()->load('deliverySlot'));

        // De la marchandise fraîche qui attend qu'on vienne la chercher.
        $this->assertSame('warning', $attention['level']);
        $this->assertStringContainsString('aucun livreur', $attention['reason']);
    }

    public function test_an_abandoned_pick_is_flagged(): void
    {
        [$order] = $this->paidOrderAt('23:00', [
            'status' => OrderStatus::PREPARING,
            'preparing_started_at' => now()->subHours(5),
        ]);

        $attention = app(OrderWatchService::class)->attention($order->fresh()->load('deliverySlot'));

        $this->assertSame('urgent', $attention['level']);
        $this->assertStringContainsString('jamais déposé', $attention['reason']);
    }

    public function test_the_detail_replays_the_journal_with_its_authors(): void
    {
        [$order, $market] = $this->paidOrderAt('23:00');
        $preparateur = $this->createPreparateurFor($market);

        Sanctum::actingAs($preparateur, ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $journal = $this->getJson("/api/v1/admin/orders/{$order->id}")->assertOk()->json('data.journal');

        // Le journal existait depuis le premier jour et n'était lisible nulle
        // part : chaque ligne porte pourtant son auteur.
        $accepted = collect($journal)->firstWhere('to_status', 'accepted');

        $this->assertNotNull($accepted);
        $this->assertSame($preparateur->name, $accepted['actor']['name']);
        $this->assertSame('preparateur', $accepted['actor_role']);
    }

    public function test_the_admin_can_free_an_order_that_was_taken_and_dropped(): void
    {
        [$order, $market] = $this->paidOrderAt('23:00');
        $preparateur = $this->createPreparateurFor($market);
        $order->update(['preparateur_id' => $preparateur->id, 'status' => OrderStatus::ACCEPTED]);

        $admin = $this->createStaff('admin');
        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/v1/admin/orders/{$order->id}/release", [
            'reason' => 'Injoignable depuis ce matin',
        ])->assertOk();

        $order->refresh();
        $this->assertNull($order->preparateur_id);
        $this->assertSame('pending', $order->status->value);

        // La trace dit que c'est l'administration qui a tranché, pas le
        // préparateur qui a rendu.
        $event = \App\Models\OrderEvent::where('order_id', $order->id)
            ->where('event', 'order.released')
            ->first();

        $this->assertSame($admin->id, $event->context['released_by']);
        $this->assertSame('Injoignable depuis ce matin', $event->reason);
    }

    public function test_freeing_requires_a_reason(): void
    {
        [$order, $market] = $this->paidOrderAt('23:00');
        $order->update(['preparateur_id' => $this->createPreparateurFor($market)->id]);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);

        $this->postJson("/api/v1/admin/orders/{$order->id}/release", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_a_basket_already_at_the_depot_cannot_be_freed(): void
    {
        [$order, $market] = $this->paidOrderAt('23:00', ['status' => OrderStatus::READY]);
        $order->update(['preparateur_id' => $this->createPreparateurFor($market)->id]);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);

        // Libérer ferait repartir un retrait déjà fait, et le panier resterait
        // au dépôt sans commande en face.
        $this->postJson("/api/v1/admin/orders/{$order->id}/release", ['reason' => 'test'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'already_ready');
    }

    public function test_freeing_clears_the_stamped_supplier_prices(): void
    {
        [$order, $market] = $this->paidOrderAt('23:00');
        $preparateur = $this->createPreparateurFor($market);
        $order->update(['preparateur_id' => $preparateur->id, 'status' => OrderStatus::PREPARING]);
        $order->items()->update(['actual_unit_price' => 800, 'actual_total_price' => 1600]);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $this->postJson("/api/v1/admin/orders/{$order->id}/release", ['reason' => 'Moto en panne'])
            ->assertOk();

        // Rien n'est sorti de l'étal : facturer ces lignes au fournisseur
        // reviendrait à payer une marchandise jamais retirée.
        $this->assertNull($order->items()->first()->actual_total_price);
    }

    public function test_reassigning_refuses_a_market_that_does_not_serve_the_zone(): void
    {
        [$order] = $this->paidOrderAt('23:00');

        [$otherZone] = $this->createZoneWithSlot();
        [$otherMarket] = $this->createMarketFor($otherZone);
        $outsider = $this->createPreparateurFor($otherMarket);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);

        // L'envoyer là-bas serait lui faire traverser la ville pour rien : il
        // ne verrait même pas la commande sur son propre écran.
        $this->postJson("/api/v1/admin/orders/{$order->id}/reassign", [
            'preparateur_id' => $outsider->id,
        ])->assertStatus(422)->assertJsonPath('error', 'market_does_not_serve_zone');
    }

    public function test_reassigning_hands_the_order_over_and_journals_it(): void
    {
        [$order, $market] = $this->paidOrderAt('23:00');
        $first = $this->createPreparateurFor($market);
        $second = $this->createPreparateurFor($market);
        $order->update(['preparateur_id' => $first->id, 'status' => OrderStatus::ACCEPTED]);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $this->postJson("/api/v1/admin/orders/{$order->id}/reassign", [
            'preparateur_id' => $second->id,
            'reason' => 'Plus proche du marché',
        ])->assertOk();

        $this->assertSame($second->id, $order->fresh()->preparateur_id);

        $event = \App\Models\OrderEvent::where('order_id', $order->id)
            ->where('event', 'order.reassigned')
            ->first();

        $this->assertSame($first->id, $event->context['from_preparateur_id']);
        $this->assertSame($second->id, $event->context['to_preparateur_id']);
    }

    public function test_candidates_only_lists_preparateurs_who_can_actually_serve(): void
    {
        [$order, $market] = $this->paidOrderAt('23:00');
        $eligible = $this->createPreparateurFor($market);

        [$otherZone] = $this->createZoneWithSlot();
        [$otherMarket] = $this->createMarketFor($otherZone);
        $this->createPreparateurFor($otherMarket);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $candidates = $this->getJson("/api/v1/admin/orders/{$order->id}/candidates")->assertOk()->json('data');

        // Proposer les autres reviendrait à offrir un choix qui ne marche pas.
        $this->assertSame([$eligible->id], array_column($candidates, 'id'));
    }

    public function test_cancelling_requires_a_reason_and_warns_the_client(): void
    {
        [$order] = $this->paidOrderAt('23:00');

        Sanctum::actingAs($this->createStaff('admin'), ['*']);

        $this->postJson("/api/v1/admin/orders/{$order->id}/cancel", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->postJson("/api/v1/admin/orders/{$order->id}/cancel", [
            'reason' => 'Rupture chez tous les fournisseurs',
        ])->assertOk();

        $this->assertSame('cancelled', $order->fresh()->status->value);
    }

    public function test_the_control_room_is_closed_to_everyone_else(): void
    {
        [$order] = $this->paidOrderAt('23:00');

        Sanctum::actingAs($this->createClient(), ['*']);

        $this->getJson('/api/v1/admin/orders')->assertStatus(403);
        $this->getJson("/api/v1/admin/orders/{$order->id}")->assertStatus(403);
        $this->postJson("/api/v1/admin/orders/{$order->id}/release", ['reason' => 'x'])->assertStatus(403);
    }
}

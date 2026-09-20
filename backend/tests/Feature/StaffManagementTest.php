<?php

namespace Tests\Feature;

use App\Models\Depot;
use App\Models\Market;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Le personnel : preparateurs et livreurs.
 *
 * Ce que ces tests defendent : un livreur qui rend son tablier ne doit pas
 * garder son acces. Le verrou existait depuis la gestion des clients —
 * `users.status` coupe la connexion, la suspension detruit les jetons — mais
 * rien ne permettait de l'actionner sur quelqu'un du personnel.
 *
 * Et surtout, les deux regles de suspension, qui ne sont PAS symetriques : ce
 * que tient un preparateur et ce que tient un livreur ne sont pas la meme
 * chose. Voir AdminStaffController::setStatus().
 */
class StaffManagementTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    private function admin(): User
    {
        return $this->createStaff('admin');
    }

    /** Un marche equipe d'un depot, pret a tourner. */
    private function market(): array
    {
        [$zone] = $this->createZoneWithSlot();
        [$market, $supplier] = $this->createMarketFor($zone);

        $depot = Depot::create([
            'name' => 'Dépôt Cotonou',
            'address' => 'Rue 12.34',
            'city' => 'Cotonou',
            'landmark' => 'Face à la station',
        ]);
        $market->update(['depot_id' => $depot->id]);

        return [$zone, $market, $supplier];
    }

    /** Une commande payee, en attente d'un preparateur. */
    private function paidOrder($zone): Order
    {
        $client = $this->createClient();
        $slot = $this->createHourlySlot($zone, '23:00');
        $address = $this->createAddressFor($client, $zone);

        Sanctum::actingAs($client, ['*']);
        $orderId = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [['product_id' => $this->createProduct()->id, 'quantity' => 2]],
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($this->admin(), ['*']);
        $this->postJson("/api/v1/admin/orders/{$orderId}/validate-payment", [
            'reference_number' => 'MTN-'.uniqid(),
        ])->assertOk();

        return Order::find($orderId);
    }

    /** Mene une commande jusqu'au depot, prete pour un livreur. */
    private function orderAtDepot($zone, Market $market, $supplier, User $preparateur): Order
    {
        $order = $this->paidOrder($zone);

        Sanctum::actingAs($preparateur, ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/start")->assertOk();

        foreach ($order->items as $item) {
            $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$item->id}", [
                'collection_status' => 'collected',
                'supplier_id' => $supplier->id,
            ])->assertOk();
        }

        $this->markReady($order->id)->assertOk();

        return $order->fresh();
    }

    public function test_the_list_holds_the_staff_and_not_the_clients(): void
    {
        [$zone, $market] = $this->market();
        $preparateur = $this->createPreparateurFor($market);
        $livreur = $this->createStaff('livreur');
        $client = $this->createClient();

        Sanctum::actingAs($this->admin(), ['*']);
        $names = collect($this->getJson('/api/v1/admin/personnel')->assertOk()->json('data'))
            ->pluck('name');

        $this->assertTrue($names->contains($preparateur->name));
        $this->assertTrue($names->contains($livreur->name));

        // Les clients ont leur propre ecran, avec leurs propres chiffres — les
        // melanger ferait un annuaire ou on ne trouve plus personne.
        $this->assertFalse($names->contains($client->name));
    }

    public function test_suspending_a_preparateur_sends_his_orders_back_to_the_market(): void
    {
        [$zone, $market] = $this->market();
        $preparateur = $this->createPreparateurFor($market);

        $order = $this->paidOrder($zone);
        Sanctum::actingAs($preparateur, ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();

        Sanctum::actingAs($this->admin(), ['*']);
        $this->patchJson("/api/v1/admin/personnel/{$preparateur->id}/status", [
            'suspended' => true,
            'reason' => 'A rendu son tablier.',
        ])->assertOk();

        /*
         * Le coeur de la regle : suspendre sans liberer bloquerait la commande
         * pour toujours — preparateur_id reste pose, personne d'autre ne peut
         * la prendre, et le client attend une commande que plus personne ne
         * traite.
         */
        $order->refresh();
        $this->assertSame('pending', $order->status->value);
        $this->assertNull($order->preparateur_id);
        $this->assertSame('suspended', $preparateur->fresh()->status);
    }

    public function test_the_suspension_motive_follows_the_order_into_the_journal(): void
    {
        [$zone, $market] = $this->market();
        $preparateur = $this->createPreparateurFor($market);

        $order = $this->paidOrder($zone);
        Sanctum::actingAs($preparateur, ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();

        Sanctum::actingAs($this->admin(), ['*']);
        $this->patchJson("/api/v1/admin/personnel/{$preparateur->id}/status", [
            'suspended' => true,
            'reason' => 'Ne répond plus depuis le 12.',
        ])->assertOk();

        // Six mois plus tard, « pourquoi cette commande est-elle repartie au
        // marche » doit avoir une reponse sans avoir a deviner.
        $event = OrderEvent::where('order_id', $order->id)->where('event', 'order.released')->first();

        $this->assertNotNull($event);
        $this->assertStringContainsString('Ne répond plus depuis le 12.', $event->reason);
        $this->assertStringContainsString('Compte suspendu', $event->reason);
    }

    public function test_a_suspended_preparateur_is_logged_out_everywhere(): void
    {
        [$zone, $market] = $this->market();
        $preparateur = $this->createPreparateurFor($market);

        $preparateur->createToken('téléphone');
        $this->assertSame(1, $preparateur->tokens()->count());

        Sanctum::actingAs($this->admin(), ['*']);
        $this->patchJson("/api/v1/admin/personnel/{$preparateur->id}/status", [
            'suspended' => true,
            'reason' => 'Parti sans prévenir.',
        ])->assertOk();

        // Les jetons Sanctum vivent trente jours : bloquer la seule connexion
        // aurait laisse le compte travailler pendant tout ce temps.
        $this->assertSame(0, $preparateur->tokens()->count());

        // Et le verrou ne tient pas qu'au retrait des jetons : le middleware
        // `active` refuse le compte a chaque requete, quelle que soit la cle
        // presentee.
        Sanctum::actingAs($preparateur->fresh(), ['*']);
        $this->getJson('/api/v1/preparateur/orders')
            ->assertStatus(403)
            ->assertJsonPath('error', 'account_suspended');
    }

    public function test_a_livreur_carrying_a_basket_cannot_be_suspended(): void
    {
        [$zone, $market, $supplier] = $this->market();
        $preparateur = $this->createPreparateurFor($market);
        $livreur = $this->createStaff('livreur');

        $order = $this->orderAtDepot($zone, $market, $supplier, $preparateur);

        Sanctum::actingAs($livreur, ['*']);
        $this->postJson("/api/v1/deliveries/{$order->id}/accept")->assertOk();

        /*
         * La marchandise est physiquement dans sa sacoche, et rien ne permet
         * aujourd'hui de rendre une course entamee. Lui couper l'application
         * n'amenerait pas le panier chez le client — ca supprimerait seulement
         * le seul moyen de savoir ou il est.
         */
        Sanctum::actingAs($this->admin(), ['*']);
        $this->patchJson("/api/v1/admin/personnel/{$livreur->id}/status", [
            'suspended' => true,
            'reason' => 'Soupçon de vol.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'delivery_in_progress')
            // Le refus NOMME la commande : sans le numero, l'administrateur
            // doit fouiller la salle de controle pour trouver laquelle bloque.
            ->assertJsonPath('orders.0', $order->order_number);

        $this->assertSame('active', $livreur->fresh()->status);
    }

    public function test_the_admin_has_a_way_out_when_a_course_never_comes_back(): void
    {
        [$zone, $market, $supplier] = $this->market();
        $preparateur = $this->createPreparateurFor($market);
        $livreur = $this->createStaff('livreur');

        $order = $this->orderAtDepot($zone, $market, $supplier, $preparateur);

        Sanctum::actingAs($livreur, ['*']);
        $deliveryId = $this->postJson("/api/v1/deliveries/{$order->id}/accept")->assertOk()->json('data.id');
        $this->patchJson("/api/v1/deliveries/{$deliveryId}/start")->assertOk();

        Sanctum::actingAs($this->admin(), ['*']);

        /*
         * L'impasse que la regle de suspension aurait creee sans cette issue :
         * une commande partie en livraison n'est ni liberable — le retrait est
         * deja fait — ni annulable, `canBeCancelled()` excluant IN_DELIVERY.
         * Les deux refus sont bons quand la course se deroule ; ils enfermaient
         * tout quand elle ne se deroule pas.
         */
        $this->postJson("/api/v1/admin/orders/{$order->id}/release", ['reason' => 'x'])
            ->assertStatus(422);
        $this->postJson("/api/v1/admin/orders/{$order->id}/cancel", ['reason' => 'x'])
            ->assertStatus(422);

        $this->postJson("/api/v1/admin/orders/{$order->id}/abandon-delivery", [
            'reason' => 'Livreur injoignable depuis 3 h.',
        ])->assertOk();

        // La commande est close et la course marquee echouee. On ne la remet
        // pas « prete » : le panier n'est plus au depot, et un autre livreur
        // irait chercher une marchandise qui n'y est pas.
        $this->assertSame('cancelled', $order->fresh()->status->value);
        $this->assertSame('failed', \App\Models\Delivery::find($deliveryId)->status->value);

        // Et le compte peut enfin etre suspendu.
        $this->patchJson("/api/v1/admin/personnel/{$livreur->id}/status", [
            'suspended' => true,
            'reason' => 'Parti avec un panier.',
        ])->assertOk();

        $this->assertSame('suspended', $livreur->fresh()->status);
    }

    public function test_abandoning_a_course_demands_a_reason_and_leaves_a_trace(): void
    {
        [$zone, $market, $supplier] = $this->market();
        $preparateur = $this->createPreparateurFor($market);
        $livreur = $this->createStaff('livreur');

        $order = $this->orderAtDepot($zone, $market, $supplier, $preparateur);

        Sanctum::actingAs($livreur, ['*']);
        $deliveryId = $this->postJson("/api/v1/deliveries/{$order->id}/accept")->assertOk()->json('data.id');
        $this->patchJson("/api/v1/deliveries/{$deliveryId}/start")->assertOk();

        Sanctum::actingAs($this->admin(), ['*']);

        // Le client est remboursé et prévenu : la décision doit être motivée.
        $this->postJson("/api/v1/admin/orders/{$order->id}/abandon-delivery", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->postJson("/api/v1/admin/orders/{$order->id}/abandon-delivery", [
            'reason' => 'Moto volée au carrefour.',
        ])->assertOk();

        $event = OrderEvent::where('order_id', $order->id)->where('event', 'delivery.abandoned')->first();

        $this->assertNotNull($event);
        $this->assertSame('Moto volée au carrefour.', $event->reason);
        $this->assertSame($livreur->id, $event->context['livreur_id']);
    }

    public function test_a_course_nobody_took_is_not_abandoned_but_released(): void
    {
        [$zone, $market, $supplier] = $this->market();
        $preparateur = $this->createPreparateurFor($market);

        $order = $this->orderAtDepot($zone, $market, $supplier, $preparateur);

        // Rembourser un client parce qu'aucun livreur n'est encore passe serait
        // une perte seche pour rien : le panier est au depot, il attend.
        Sanctum::actingAs($this->admin(), ['*']);
        $this->postJson("/api/v1/admin/orders/{$order->id}/abandon-delivery", [
            'reason' => 'Personne ne vient.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'not_in_delivery');
    }

    public function test_the_same_livreur_can_be_suspended_once_the_basket_is_delivered(): void
    {
        [$zone, $market, $supplier] = $this->market();
        $preparateur = $this->createPreparateurFor($market);
        $livreur = $this->createStaff('livreur');

        $order = $this->orderAtDepot($zone, $market, $supplier, $preparateur);

        Sanctum::actingAs($livreur, ['*']);
        $deliveryId = $this->postJson("/api/v1/deliveries/{$order->id}/accept")->assertOk()->json('data.id');
        $this->patchJson("/api/v1/deliveries/{$deliveryId}/start")->assertOk();
        $this->confirmDelivery($deliveryId)->assertOk();

        // Le panier est remis : plus rien ne retient le compte.
        Sanctum::actingAs($this->admin(), ['*']);
        $this->patchJson("/api/v1/admin/personnel/{$livreur->id}/status", [
            'suspended' => true,
            'reason' => 'Fin de collaboration.',
        ])->assertOk();

        $this->assertSame('suspended', $livreur->fresh()->status);
    }

    public function test_a_basket_already_at_the_depot_does_not_hold_back_its_preparateur(): void
    {
        [$zone, $market, $supplier] = $this->market();
        $preparateur = $this->createPreparateurFor($market);

        $order = $this->orderAtDepot($zone, $market, $supplier, $preparateur);

        /*
         * Son travail sur cette commande est fini : le panier est au depot, il
         * appartient desormais au livreur. La liberer ferait repartir un
         * retrait deja fait — et refuser la suspension pour elle retiendrait le
         * compte sans raison.
         */
        Sanctum::actingAs($this->admin(), ['*']);
        $this->patchJson("/api/v1/admin/personnel/{$preparateur->id}/status", [
            'suspended' => true,
            'reason' => 'Fin de contrat.',
        ])->assertOk();

        $this->assertSame('ready', $order->fresh()->status->value);
        $this->assertSame($preparateur->id, $order->fresh()->preparateur_id);
    }

    public function test_suspending_demands_a_reason(): void
    {
        [$zone, $market] = $this->market();
        $preparateur = $this->createPreparateurFor($market);

        Sanctum::actingAs($this->admin(), ['*']);
        $this->patchJson("/api/v1/admin/personnel/{$preparateur->id}/status", ['suspended' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->assertSame('active', $preparateur->fresh()->status);
    }

    public function test_restoring_access_puts_someone_back_to_work(): void
    {
        [$zone, $market] = $this->market();
        $preparateur = $this->createPreparateurFor($market);

        Sanctum::actingAs($this->admin(), ['*']);
        $this->patchJson("/api/v1/admin/personnel/{$preparateur->id}/status", [
            'suspended' => true,
            'reason' => 'Malentendu.',
        ])->assertOk();

        $this->patchJson("/api/v1/admin/personnel/{$preparateur->id}/status", ['suspended' => false])
            ->assertOk();

        $this->assertSame('active', $preparateur->fresh()->status);
    }

    public function test_the_dormant_filter_finds_those_who_stopped_coming(): void
    {
        [$zone, $market] = $this->market();

        $gone = $this->createPreparateurFor($market);
        $gone->forceFill(['created_at' => now()->subDays(60)])->save();

        $fresh = $this->createPreparateurFor($market);
        $fresh->forceFill(['created_at' => now()->subDays(60)])->save();

        // Celui-ci a travaille hier : il est bien la.
        $order = $this->paidOrder($zone);
        Sanctum::actingAs($fresh, ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();

        /*
         * Le signal qui repond au probleme d'origine : personne ne vient
         * declarer qu'il arrete. Il cesse simplement de venir, et son acces
         * reste ouvert.
         */
        Sanctum::actingAs($this->admin(), ['*']);
        $names = collect(
            $this->getJson('/api/v1/admin/personnel?filter=dormant')->assertOk()->json('data')
        )->pluck('name');

        $this->assertTrue($names->contains($gone->name));
        $this->assertFalse($names->contains($fresh->name));
    }

    public function test_a_brand_new_account_is_not_reported_as_gone(): void
    {
        [$zone, $market] = $this->market();

        // Embauche avant-hier, pas encore de passage : le signaler comme parti
        // serait absurde, et userait le filtre jusqu'a ce qu'on l'ignore.
        $this->createPreparateurFor($market);

        Sanctum::actingAs($this->admin(), ['*']);
        $this->getJson('/api/v1/admin/personnel?filter=dormant')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_client_cannot_be_suspended_through_this_screen(): void
    {
        $client = $this->createClient();

        // Les deux ecrans ont des regles differentes — un client n'a ni
        // commande en main ni panier dans une sacoche. Les croiser ferait
        // appliquer la mauvaise regle a la mauvaise personne.
        Sanctum::actingAs($this->admin(), ['*']);
        $this->patchJson("/api/v1/admin/personnel/{$client->id}/status", [
            'suspended' => true,
            'reason' => 'Test.',
        ])->assertNotFound();

        $this->assertSame('active', $client->fresh()->status);
    }

    public function test_the_screen_is_closed_to_everyone_but_an_admin(): void
    {
        [$zone, $market] = $this->market();

        Sanctum::actingAs($this->createPreparateurFor($market), ['*']);
        $this->getJson('/api/v1/admin/personnel')->assertForbidden();

        Sanctum::actingAs($this->createClient(), ['*']);
        $this->getJson('/api/v1/admin/personnel')->assertForbidden();
    }
}

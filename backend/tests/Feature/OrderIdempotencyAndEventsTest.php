<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class OrderIdempotencyAndEventsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    private array $payload = [];

    /** Conservee : les preparateurs doivent etre rattaches a un marche qui dessert cette zone. */
    private $zone;

    private function prepare(): void
    {
        $client = $this->createClient();
        [$this->zone] = $this->createZoneWithSlot();
        $zone = $this->zone;
        $slot = $this->createHourlySlot($zone, '23:00');
        $address = $this->createAddressFor($client, $zone);
        $product = $this->createProduct();

        Sanctum::actingAs($client, ['*']);

        $this->payload = [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [
                ['type' => 'product', 'product_id' => $product->id, 'quantity' => 2],
            ],
        ];
    }

    public function test_the_same_idempotency_key_returns_the_same_order(): void
    {
        $this->prepare();
        $headers = ['Idempotency-Key' => 'commande-abc-123'];

        $first = $this->postJson('/api/v1/orders', $this->payload, $headers)->assertCreated();

        // Le client n'a pas vu la reponse et retouche le bouton.
        $second = $this->postJson('/api/v1/orders', $this->payload, $headers)->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Order::count());
    }

    public function test_key_order_in_the_payload_does_not_matter(): void
    {
        $this->prepare();
        $headers = ['Idempotency-Key' => 'commande-abc-123'];

        $this->postJson('/api/v1/orders', $this->payload, $headers)->assertCreated();

        // Meme contenu, champs dans un autre ordre : c'est la meme requete.
        $reordered = array_reverse($this->payload, preserve_keys: true);
        $this->postJson('/api/v1/orders', $reordered, $headers)->assertOk();

        $this->assertSame(1, Order::count());
    }

    public function test_reusing_a_key_for_a_different_order_is_refused(): void
    {
        $this->prepare();
        $headers = ['Idempotency-Key' => 'commande-abc-123'];

        $this->postJson('/api/v1/orders', $this->payload, $headers)->assertCreated();

        $different = $this->payload;
        $different['items'][0]['quantity'] = 9;

        $this->postJson('/api/v1/orders', $different, $headers)
            ->assertStatus(409)
            ->assertJsonPath('error', 'idempotency_key_reused');
    }

    public function test_without_a_key_two_calls_create_two_orders(): void
    {
        $this->prepare();

        // Comportement d'origine, conserve : la protection est explicite.
        $this->postJson('/api/v1/orders', $this->payload)->assertCreated();
        $this->postJson('/api/v1/orders', $this->payload)->assertCreated();

        $this->assertSame(2, Order::count());
    }

    public function test_creating_an_order_writes_a_journal_entry(): void
    {
        $this->prepare();

        $response = $this->postJson('/api/v1/orders', $this->payload)->assertCreated();
        $orderId = $response->json('data.id');

        $this->assertDatabaseHas('order_events', [
            'order_id' => $orderId,
            'event' => 'order.created',
            'to_status' => 'pending',
        ]);
    }

    public function test_every_status_change_is_journalled_with_its_actor(): void
    {
        $this->prepare();
        $orderId = $this->postJson('/api/v1/orders', $this->payload)->json('data.id');

        $admin = $this->createStaff('admin');
        Sanctum::actingAs($admin, ['*']);
        $this->postJson("/api/v1/admin/orders/{$orderId}/validate-payment", [
            'reference_number' => 'MTN-99',
        ])->assertOk();

        [$market] = $this->createMarketFor($this->zone);
        $preparateur = $this->createPreparateurFor($market);
        Sanctum::actingAs($preparateur, ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$orderId}/accept")->assertOk();

        $event = OrderEvent::where('order_id', $orderId)
            ->where('to_status', 'accepted')
            ->firstOrFail();

        // Les colonnes accepted_at disent QUAND. Le journal dit QUI.
        $this->assertSame($preparateur->id, $event->actor_id);
        $this->assertSame('preparateur', $event->actor_role);
        $this->assertSame('pending', $event->from_status);
    }

    public function test_a_cancellation_keeps_its_reason(): void
    {
        $this->prepare();
        $orderId = $this->postJson('/api/v1/orders', $this->payload)->json('data.id');

        $this->deleteJson("/api/v1/orders/{$orderId}", ['reason' => 'Je serai absent'])->assertOk();

        $event = OrderEvent::where('order_id', $orderId)
            ->where('to_status', 'cancelled')
            ->firstOrFail();

        $this->assertSame('Je serai absent', $event->reason);
    }
}

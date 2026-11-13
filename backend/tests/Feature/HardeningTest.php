<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    private function readyOrder(): Order
    {
        $client = $this->createClient();
        [$zone] = $this->createZoneWithSlot();

        return Order::create([
            'order_number' => Order::generateOrderNumber(),
            'client_id' => $client->id,
            'delivery_address_id' => $this->createAddressFor($client, $zone)->id,
            'delivery_date' => now()->toDateString(),
            'status' => OrderStatus::READY,
            'subtotal' => 1000,
            'total' => 1600,
            'payment_status' => 'completed',
        ]);
    }

    public function test_a_second_livreur_cannot_take_an_order_already_taken(): void
    {
        $order = $this->readyOrder();

        Sanctum::actingAs($this->createStaff('livreur'), ['*']);
        $this->postJson("/api/v1/deliveries/{$order->id}/accept")->assertOk();

        // La commande reste « prete » jusqu'au depart : le second livreur doit
        // recevoir un refus explicite, pas une erreur SQL.
        Sanctum::actingAs($this->createStaff('livreur'), ['*']);
        $this->postJson("/api/v1/deliveries/{$order->id}/accept")
            ->assertStatus(400)
            ->assertJsonPath('message', 'Cette commande a déjà été prise par un autre livreur');

        $this->assertDatabaseCount('deliveries', 1);
    }

    public function test_a_delivery_must_be_started_before_being_confirmed_and_only_once(): void
    {
        $order = $this->readyOrder();
        Sanctum::actingAs($this->createStaff('livreur'), ['*']);
        $deliveryId = $this->postJson("/api/v1/deliveries/{$order->id}/accept")->json('data.id');

        $this->postJson("/api/v1/deliveries/{$deliveryId}/confirm")->assertStatus(400);

        $this->patchJson("/api/v1/deliveries/{$deliveryId}/start")->assertOk();
        $this->postJson("/api/v1/deliveries/{$deliveryId}/confirm")->assertOk();

        $this->postJson("/api/v1/deliveries/{$deliveryId}/confirm")->assertStatus(400);
        $this->patchJson("/api/v1/deliveries/{$deliveryId}/start")->assertStatus(400);
    }

    public function test_admin_cannot_set_an_unknown_order_status(): void
    {
        $order = $this->readyOrder();
        Sanctum::actingAs($this->createStaff('admin'), ['*']);

        $this->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'n_importe_quoi'])->assertStatus(422);
        $this->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'cancelled'])->assertOk();
    }

    public function test_search_does_not_bypass_the_availability_filter(): void
    {
        $this->createProduct(['name' => 'Tomates fraîches', 'is_available' => true]);
        $this->createProduct(['name' => 'Piment', 'description' => 'Idéal avec les tomates', 'is_available' => false]);

        $this->getJson('/api/v1/products?search=tomates&available=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Tomates fraîches');
    }

    public function test_page_size_is_capped(): void
    {
        $this->getJson('/api/v1/products?limit=100000')
            ->assertOk()
            ->assertJsonPath('pagination.per_page', 100);
    }

    public function test_a_promo_code_is_recorded_and_respects_the_per_user_limit(): void
    {
        $client = $this->createClient();
        [$zone, $slot] = $this->createZoneWithSlot(fee: 500);
        $address = $this->createAddressFor($client, $zone);
        $product = $this->createProduct(['base_price' => 5000]);
        $promo = Promotion::create([
            'code' => 'BIENVENUE',
            'discount_type' => 'fixed_amount',
            'discount_value' => 1000,
            'max_usage_per_user' => 1,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'is_active' => true,
        ]);

        Sanctum::actingAs($client, ['*']);
        $order = fn () => $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'promo_code_id' => $promo->id,
            'items' => [['type' => 'product', 'product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertEquals(1000, (float) $order()->json('data.promo_discount'));
        $this->assertEquals(0, (float) $order()->json('data.promo_discount'));

        $this->assertDatabaseCount('promotion_usages', 1);
        $this->assertSame(1, $promo->fresh()->usage_count);
    }
}

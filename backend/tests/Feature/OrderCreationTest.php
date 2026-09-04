<?php

namespace Tests\Feature;

use App\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_order_delivery_fee_is_calculated_from_the_address_zone(): void
    {
        $client = $this->createClient();
        [$zone, $slot] = $this->createZoneWithSlot(fee: 1200);
        $address = $this->createAddressFor($client, $zone);
        $product = $this->createProduct(['base_price' => 2000]);

        Sanctum::actingAs($client, ['*']);

        $response = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [
                ['type' => 'product', 'product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated();

        $order = $response->json('data');
        $this->assertEquals(1200, (float) $order['delivery_fee']);
        $this->assertEquals(4000, (float) $order['subtotal']);
        $this->assertEquals('pending', $order['payment_status']);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order['id'],
            'payment_method' => 'manual',
            'status' => 'pending',
        ]);
    }

    public function test_delivery_fee_falls_back_to_a_default_when_address_has_no_zone(): void
    {
        $client = $this->createClient();
        [, $slot] = $this->createZoneWithSlot();
        $address = Address::create([
            'user_id' => $client->id,
            'label' => 'Maison',
            'quartier' => 'Sans zone',
            'is_default' => true,
            'is_active' => true,
        ]);
        $product = $this->createProduct();

        Sanctum::actingAs($client, ['*']);

        $response = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [
                ['type' => 'product', 'product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated();
        $this->assertEquals(500, (float) $response->json('data.delivery_fee'));
    }

    public function test_malformed_items_are_rejected(): void
    {
        $client = $this->createClient();
        [$zone, $slot] = $this->createZoneWithSlot();
        $address = $this->createAddressFor($client, $zone);

        Sanctum::actingAs($client, ['*']);

        $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [
                ['type' => 'product'], // pas de product_id ni quantity
            ],
        ])->assertStatus(422);
    }
}

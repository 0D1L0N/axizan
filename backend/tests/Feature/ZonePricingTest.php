<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ZonePricingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    private function orderFor(array $zoneSettings, int $unitPrice, int $quantity = 1, float $slotFee = 0)
    {
        $client = $this->createClient();
        [$zone, $slot] = $this->createZoneWithSlot(fee: 800);
        $zone->update($zoneSettings);
        $slot->update(['additional_fee' => $slotFee]);
        $address = $this->createAddressFor($client, $zone);
        $product = $this->createProduct(['base_price' => $unitPrice]);

        Sanctum::actingAs($client, ['*']);

        return $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [
                ['type' => 'product', 'product_id' => $product->id, 'quantity' => $quantity],
            ],
        ]);
    }

    public function test_slot_additional_fee_is_added_to_the_zone_fee(): void
    {
        $response = $this->orderFor([], unitPrice: 2000, slotFee: 300);

        $response->assertCreated();
        $this->assertEquals(1100, (float) $response->json('data.delivery_fee'));
    }

    public function test_delivery_is_free_above_the_zone_threshold(): void
    {
        $response = $this->orderFor(['free_delivery_threshold' => 10000], unitPrice: 5000, quantity: 2);

        $response->assertCreated();
        $this->assertEquals(0, (float) $response->json('data.delivery_fee'));
    }

    public function test_small_order_fee_is_added_below_the_zone_minimum(): void
    {
        $response = $this->orderFor([
            'min_order_amount' => 3000,
            'small_order_fee' => 400,
            'small_order_action' => 'apply_fee',
        ], unitPrice: 1000);

        $response->assertCreated();
        $this->assertEquals(1200, (float) $response->json('data.delivery_fee'));
    }

    public function test_order_below_the_zone_minimum_is_refused_when_the_zone_blocks(): void
    {
        $response = $this->orderFor([
            'min_order_amount' => 3000,
            'small_order_action' => 'block',
        ], unitPrice: 1000);

        $response->assertStatus(400);
        $this->assertDatabaseCount('orders', 0);
    }
}

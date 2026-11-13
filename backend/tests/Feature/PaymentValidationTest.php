<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class PaymentValidationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    private function createPendingOrder()
    {
        $client = $this->createClient();
        [$zone, $slot] = $this->createZoneWithSlot();
        $address = $this->createAddressFor($client, $zone);
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

        return $response->json('data.id');
    }

    public function test_a_non_admin_cannot_validate_a_payment(): void
    {
        $orderId = $this->createPendingOrder();

        $client = $this->createClient();
        Sanctum::actingAs($client, ['*']);

        $this->postJson("/api/v1/admin/orders/{$orderId}/validate-payment", ['reference_number' => 'MTN-TEST-0001'])
            ->assertStatus(403);
    }

    public function test_an_admin_can_validate_a_payment(): void
    {
        $orderId = $this->createPendingOrder();

        $admin = $this->createStaff('admin');
        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/v1/admin/orders/{$orderId}/validate-payment", ['reference_number' => 'MTN-TEST-0001'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'payment_status' => 'completed',
        ]);
    }
}

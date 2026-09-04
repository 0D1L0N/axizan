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

    public function test_validating_without_a_reference_is_refused(): void
    {
        $orderId = $this->createPendingOrder();

        Sanctum::actingAs($this->createStaff('admin'), ['*']);

        // Sans reference, une contestation « j'ai paye » / « non » ne peut plus
        // etre tranchee : le paiement n'est rattache a aucune trace.
        $this->postJson("/api/v1/admin/orders/{$orderId}/validate-payment", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reference_number');

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'pending']);
    }

    public function test_a_payment_cannot_be_validated_twice(): void
    {
        $orderId = $this->createPendingOrder();

        Sanctum::actingAs($this->createStaff('admin'), ['*']);

        $this->postJson("/api/v1/admin/orders/{$orderId}/validate-payment", ['reference_number' => 'MTN-1'])
            ->assertOk();

        $this->postJson("/api/v1/admin/orders/{$orderId}/validate-payment", ['reference_number' => 'MTN-2'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'already_validated');
    }

    public function test_the_queue_puts_declared_references_first(): void
    {
        $silent = $this->createPendingOrder();
        $declared = $this->createPendingOrder();

        // Le client a envoye l'argent et donne sa reference : quelqu'un attend
        // derriere, sa commande doit remonter avant les autres.
        \App\Models\Order::find($declared)->payment->update([
            'payer_declared_reference' => 'MP260904.1432.A54321',
            'payer_declared_at' => now(),
        ]);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);

        $ids = collect($this->getJson('/api/v1/admin/payments/pending')->assertOk()->json('data'))
            ->pluck('id')
            ->all();

        $this->assertSame([$declared, $silent], $ids);
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_full_order_journey_from_creation_to_completion(): void
    {
        $client = $this->createClient();
        $admin = $this->createStaff('admin');
        $livreur = $this->createStaff('livreur');

        [$zone, $slot] = $this->createZoneWithSlot(fee: 900);

        // Les préparateurs sont rattachés à un marché qui dessert cette zone :
        // sans marché, ils ne voient et n'acceptent plus rien.
        [$market, $supplier] = $this->createMarketFor($zone);
        $preparateurA = $this->createPreparateurFor($market);
        $preparateurB = $this->createPreparateurFor($market);

        $address = $this->createAddressFor($client, $zone);
        $product = $this->createProduct(['base_price' => 1500]);

        // 1. Le client passe commande
        Sanctum::actingAs($client, ['*']);
        $orderId = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [
                ['type' => 'product', 'product_id' => $product->id, 'quantity' => 2],
            ],
        ])->assertCreated()->json('data.id');

        // Le préparateur ne peut pas encore accepter : paiement non validé
        Sanctum::actingAs($preparateurA, ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$orderId}/accept")
            ->assertStatus(400);

        // 2. L'admin valide le paiement
        Sanctum::actingAs($admin, ['*']);
        $this->postJson("/api/v1/admin/orders/{$orderId}/validate-payment", ['reference_number' => 'MTN-TEST-0001'])->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'completed']);

        // 3. Le préparateur A accepte, un préparateur B ne peut pas agir dessus
        Sanctum::actingAs($preparateurA, ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$orderId}/accept")->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'accepted', 'preparateur_id' => $preparateurA->id]);

        Sanctum::actingAs($preparateurB, ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$orderId}/start")->assertStatus(404);

        Sanctum::actingAs($preparateurA, ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$orderId}/start")->assertOk();

        // Chaque ligne doit être renseignée avec son fournisseur avant que la
        // commande puisse partir : c'est ce qui rendra le point avec les
        // partenaires possible.
        foreach (\App\Models\OrderItem::where('order_id', $orderId)->get() as $item) {
            $this->patchJson("/api/v1/preparateur/orders/{$orderId}/items/{$item->id}", [
                'collection_status' => 'collected',
                'supplier_id' => $supplier->id,
            ])->assertOk();
        }

        $this->markReady($orderId)->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'ready']);

        // 4. Le livreur accepte, démarre puis confirme la livraison
        Sanctum::actingAs($livreur, ['*']);
        $deliveryId = $this->postJson("/api/v1/deliveries/{$orderId}/accept")
            ->assertOk()
            ->json('data.id');

        $this->patchJson("/api/v1/deliveries/{$deliveryId}/start")->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'in_delivery']);

        $this->confirmDelivery($deliveryId, [
            'delivery_notes' => 'Remis en main propre',
        ])->assertOk();

        // 5. La commande est complétée automatiquement (paiement déjà validé en amont)
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'completed']);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Delivery;
use App\Models\DeliveryZone;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * La voie d'essai : le parcours complet, sans argent.
 *
 * Les cles FedaPay sont des cles LIVE : la moindre commande debite un vrai
 * compte. Un article a zero franc dans une zone a zero franc donne un total
 * nul — et une commande a zero franc est payee d'avance, il n'y a rien a
 * encaisser.
 *
 * Ce test parcourt tout : commande, paiement acquitte, retrait, remplacement
 * refuse, depot, livraison, facture.
 */
class FreeTestLaneTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_a_zero_total_order_is_settled_on_the_spot(): void
    {
        $zone = DeliveryZone::create(['name' => 'Zone de test', 'base_delivery_fee' => 0, 'is_active' => true]);
        $slot = $this->createHourlySlot($zone, '23:00');

        $client = $this->createClient();
        $address = $this->createAddressFor($client, $zone);
        $product = $this->createProduct(['base_price' => 0]);

        Sanctum::actingAs($client, ['*']);
        $order = Order::find($this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [['type' => 'product', 'product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id'));

        // Laisser une commande a zero franc attendre un paiement bloquerait le
        // client devant un ecran lui demandant de regler zero.
        $this->assertSame(0, (int) round((float) $order->total));
        $this->assertSame('completed', $order->payment_status);
        $this->assertSame(PaymentStatus::COMPLETED, $order->payment->status);
        $this->assertTrue($order->canBeAccepted());
    }

    public function test_a_paying_order_still_waits_for_its_payment(): void
    {
        [$zone, $slot] = $this->createZoneWithSlot(800);
        $client = $this->createClient();
        $address = $this->createAddressFor($client, $zone);
        $product = $this->createProduct(['base_price' => 1000]);

        Sanctum::actingAs($client, ['*']);
        $order = Order::find($this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [['type' => 'product', 'product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id'));

        // Le garde-fou : seul un total REELLEMENT nul est acquitte.
        $this->assertSame('pending', $order->payment_status);
        $this->assertFalse($order->canBeAccepted());
    }

    public function test_the_whole_journey_runs_without_money(): void
    {
        $zone = DeliveryZone::create(['name' => 'Zone de test', 'base_delivery_fee' => 0, 'is_active' => true]);
        [$market, $supplier] = $this->createMarketFor($zone);
        $slot = $this->createHourlySlot($zone, '23:00');

        $depot = \App\Models\Depot::create([
            'name' => 'Dépôt de test', 'address' => 'Akassato', 'city' => 'Abomey-Calavi', 'is_active' => true,
        ]);
        $market->update(['depot_id' => $depot->id]);

        $preparateur = $this->createPreparateurFor($market);
        $livreur = $this->createStaff('livreur');
        $client = $this->createClient();
        $address = $this->createAddressFor($client, $zone);

        $gombo = $this->createProduct(['name' => 'Gombo test', 'base_price' => 0]);
        $tomate = $this->createProduct(['name' => 'Tomate test', 'base_price' => 0]);

        // ---- 1. Le client commande ----
        Sanctum::actingAs($client, ['*']);
        $order = Order::find($this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [
                ['type' => 'product', 'product_id' => $gombo->id, 'quantity' => 2],
                ['type' => 'product', 'product_id' => $tomate->id, 'quantity' => 1],
            ],
        ])->assertCreated()->json('data.id'));

        $this->assertSame('completed', $order->payment_status);

        // ---- 2. Le préparateur prend et démarre ----
        Sanctum::actingAs($preparateur, ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/start")->assertOk();

        $items = $order->fresh()->items;
        $first = $items->first();
        $second = $items->last();

        // ---- 3. Un article pris, un remplacé ----
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$first->id}", [
            'collection_status' => 'collected',
            'supplier_id' => $supplier->id,
        ])->assertOk();

        $substitute = $this->createProduct(['name' => 'Tomate cerise test', 'base_price' => 0]);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$second->id}", [
            'collection_status' => 'replaced',
            'supplier_id' => $supplier->id,
            'replacement_product_id' => $substitute->id,
        ])->assertOk();

        // ---- 4. Le client refuse le remplacement, préparateur encore à l'étal ----
        Sanctum::actingAs($client, ['*']);
        $this->postJson("/api/v1/orders/{$order->id}/items/{$second->id}/substitution", [
            'response' => 'refused',
        ])->assertOk();

        $this->assertSame('removed', $second->fresh()->collection_status);

        // ---- 5. Dépôt, avec la photo obligatoire ----
        Sanctum::actingAs($preparateur, ['*']);
        $this->markReady($order->id)->assertOk();
        $this->assertSame('ready', $order->fresh()->status->value);

        // ---- 6. Le livreur prend, part, remet ----
        Sanctum::actingAs($livreur, ['*']);
        $deliveryId = $this->postJson("/api/v1/deliveries/{$order->id}/accept")->assertOk()->json('data.id');
        $this->patchJson("/api/v1/deliveries/{$deliveryId}/start")->assertOk();
        $this->postJson("/api/v1/deliveries/{$deliveryId}/location", [
            'latitude' => 6.37, 'longitude' => 2.42,
        ])->assertOk();
        $this->confirmDelivery($deliveryId)->assertOk();

        $this->assertSame('completed', $order->fresh()->status->value);
        $this->assertNotNull(Delivery::find($deliveryId)->proof_photo_url);

        // ---- 7. La facture, avec l'article refusé signalé ----
        Sanctum::actingAs($client, ['*']);
        $invoice = $this->getJson("/api/v1/orders/{$order->id}/invoice")->assertOk()->json('data');

        $this->assertSame(0, $invoice['totals']['total']);

        $refused = collect($invoice['lines'])->firstWhere('status', 'removed');
        $this->assertNotNull($refused);
        $this->assertFalse($refused['billed']);
    }
}

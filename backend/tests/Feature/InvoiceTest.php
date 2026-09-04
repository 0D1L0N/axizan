<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Facture remise au client apres livraison.
 *
 * Elle doit dire ce qui a ete LIVRE, pas seulement ce qui a ete commande :
 * un article retire par le preparateur a bien ete paye, et le passer sous
 * silence reviendrait a facturer une marchandise jamais remise.
 */
class InvoiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    /** @return array{0: Order, 1: \App\Models\User} */
    private function deliveredOrder(array $productOverrides = []): array
    {
        [$zone, $slot] = $this->createZoneWithSlot(800);
        [$market, $supplier] = $this->createMarketFor($zone);
        $preparateur = $this->createPreparateurFor($market);

        $client = $this->createClient(['name' => 'Awa Hounkpatin']);
        $address = $this->createAddressFor($client, $zone);
        $product = $this->createProduct(array_merge(['name' => 'Tomate fraîche', 'base_price' => 1500], $productOverrides));

        Sanctum::actingAs($client, ['*']);
        $orderId = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [['type' => 'product', 'product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $this->postJson("/api/v1/admin/orders/{$orderId}/validate-payment", [
            'reference_number' => 'MP260904.1432.A54321',
            'payment_provider' => 'mtn',
        ])->assertOk();

        Sanctum::actingAs($preparateur, ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$orderId}/accept")->assertOk();
        $this->patchJson("/api/v1/preparateur/orders/{$orderId}/start")->assertOk();

        foreach (Order::find($orderId)->items as $item) {
            $this->patchJson("/api/v1/preparateur/orders/{$orderId}/items/{$item->id}", [
                'collection_status' => 'collected',
                'supplier_id' => $supplier->id,
            ])->assertOk();
        }

        return [Order::find($orderId), $client];
    }

    private function deliver(Order $order): void
    {
        Sanctum::actingAs($this->createStaff('livreur'), ['*']);
        $deliveryId = $this->postJson("/api/v1/deliveries/{$order->id}/accept")->json('data.id');
        $this->patchJson("/api/v1/deliveries/{$deliveryId}/start")->assertOk();
        $this->confirmDelivery($deliveryId)->assertOk();
    }

    public function test_no_invoice_before_the_goods_are_handed_over(): void
    {
        [$order, $client] = $this->deliveredOrder();

        Sanctum::actingAs($client, ['*']);

        // Rien a facturer tant que le panier peut encore changer au marché.
        $this->getJson("/api/v1/orders/{$order->id}/invoice")
            ->assertStatus(409)
            ->assertJsonPath('error', 'not_delivered');
    }

    public function test_the_client_gets_an_invoice_once_delivered(): void
    {
        [$order, $client] = $this->deliveredOrder();
        Sanctum::actingAs($order->preparateur, ['*']);
        $this->markReady($order->id)->assertOk();
        $this->deliver($order);

        Sanctum::actingAs($client, ['*']);
        $invoice = $this->getJson("/api/v1/orders/{$order->id}/invoice")->assertOk()->json('data');

        // Le numero se lit des deux cotes sans table de correspondance.
        $this->assertSame(str_replace('AXZ-', 'FA-', $order->order_number), $invoice['number']);
        $this->assertSame('Awa Hounkpatin', $invoice['buyer']['name']);

        // 2 × 1500 = 3000, +800 de livraison, +10 % de taxe sur le sous-total.
        $this->assertSame(3000, $invoice['totals']['subtotal']);
        $this->assertSame(800, $invoice['totals']['delivery_fee']);
        $this->assertSame(300, $invoice['totals']['tax']);
        $this->assertSame(4100, $invoice['totals']['total']);
        $this->assertSame(0, $invoice['totals']['undelivered']);

        // La reference constatee par Axizan, pas celle declaree par le client.
        $this->assertSame('MP260904.1432.A54321', $invoice['payment']['reference']);

        $this->assertSame('Tomate fraîche', $invoice['lines'][0]['name']);
        $this->assertTrue($invoice['lines'][0]['billed']);
    }

    public function test_an_item_removed_at_the_market_is_flagged_as_owed(): void
    {
        [$order, $client] = $this->deliveredOrder();

        Sanctum::actingAs($order->preparateur, ['*']);
        $item = $order->items->first();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$item->id}", [
            'collection_status' => 'removed',
        ])->assertOk();

        $this->markReady($order->id)->assertOk();
        $this->deliver($order);

        Sanctum::actingAs($client, ['*']);
        $invoice = $this->getJson("/api/v1/orders/{$order->id}/invoice")->assertOk()->json('data');

        // Le client a payé cet article : la facture le dit au lieu de
        // l'effacer, sinon on facturerait une marchandise jamais remise.
        $this->assertSame('removed', $invoice['lines'][0]['status']);
        $this->assertFalse($invoice['lines'][0]['billed']);
        $this->assertSame(3000, $invoice['totals']['undelivered']);
    }

    public function test_a_replaced_item_names_what_was_actually_delivered(): void
    {
        [$order, $client] = $this->deliveredOrder();
        $substitute = $this->createProduct(['name' => 'Tomate cerise']);

        Sanctum::actingAs($order->preparateur, ['*']);
        $item = $order->items->first();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$item->id}", [
            'collection_status' => 'replaced',
            'supplier_id' => $item->supplier_id,
            'replacement_product_id' => $substitute->id,
        ])->assertOk();

        $this->markReady($order->id)->assertOk();
        $this->deliver($order);

        Sanctum::actingAs($client, ['*']);
        $line = $this->getJson("/api/v1/orders/{$order->id}/invoice")->assertOk()->json('data.lines.0');

        $this->assertSame('Tomate fraîche', $line['name']);
        $this->assertSame('Tomate cerise', $line['delivered_as']);
        $this->assertTrue($line['billed']);
    }

    public function test_the_invoice_carries_both_proofs(): void
    {
        [$order, $client] = $this->deliveredOrder();
        Sanctum::actingAs($order->preparateur, ['*']);
        $this->markReady($order->id)->assertOk();
        $this->deliver($order);

        Sanctum::actingAs($client, ['*']);
        $delivery = $this->getJson("/api/v1/orders/{$order->id}/invoice")->assertOk()->json('data.delivery');

        // Photo du panier et photo de remise : deux moments distincts, tous
        // deux opposables si la commande est contestée.
        $this->assertNotNull($delivery['prep_photo_url']);
        $this->assertNotNull($delivery['proof_photo_url']);
    }

    public function test_another_client_cannot_read_the_invoice(): void
    {
        [$order] = $this->deliveredOrder();
        Sanctum::actingAs($order->preparateur, ['*']);
        $this->markReady($order->id)->assertOk();
        $this->deliver($order);

        Sanctum::actingAs($this->createClient(), ['*']);

        $this->getJson("/api/v1/orders/{$order->id}/invoice")->assertNotFound();
    }
}

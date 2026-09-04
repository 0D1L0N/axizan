<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Depot;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Remise au depot, suivi du livreur et photos.
 *
 * Repartition des roles : le preparateur ne fait que preparer et depose au
 * depot ; le livreur vient chercher, puis partage sa position jusqu'a la
 * remise.
 */
class DeliveryTrackingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    /** Commande deposee au depot par un preparateur, prete pour le livreur. */
    private function readyOrder(?Depot &$depot = null): Order
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

        $client = $this->createClient();
        $slot = $this->createHourlySlot($zone, '23:00');
        $address = $this->createAddressFor($client, $zone);
        $address->update(['latitude' => 6.3654, 'longitude' => 2.4183]);

        Sanctum::actingAs($client, ['*']);
        $orderId = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [['type' => 'product', 'product_id' => $this->createProduct()->id, 'quantity' => 2]],
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $this->postJson("/api/v1/admin/orders/{$orderId}/validate-payment", [
            'reference_number' => 'MTN-1',
        ])->assertOk();

        Sanctum::actingAs($this->createPreparateurFor($market), ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$orderId}/accept")->assertOk();
        $this->patchJson("/api/v1/preparateur/orders/{$orderId}/start")->assertOk();

        $order = Order::find($orderId);
        foreach ($order->items as $item) {
            $this->patchJson("/api/v1/preparateur/orders/{$orderId}/items/{$item->id}", [
                'collection_status' => 'collected',
                'supplier_id' => $supplier->id,
            ])->assertOk();
        }

        return $order;
    }

    public function test_the_preparateur_can_attach_a_basket_photo(): void
    {
        Storage::fake('public');
        $order = $this->readyOrder();

        $this->postJson("/api/v1/preparateur/orders/{$order->id}/ready", [
            '_method' => 'PATCH',
            // create() plutot que image() : l'extension GD n'est pas installee,
            // et l'application ne fait que stocker le fichier — elle ne le
            // retaille pas. Inutile de l'ajouter a l'image de production.
            'photo' => UploadedFile::fake()->create('panier.jpg', 120, 'image/jpeg'),
        ])->assertOk();

        // Le client paie d'avance un retrait qu'il ne voit pas faire : cette
        // image est ce qui lui est montre.
        $this->assertNotNull($order->fresh()->prep_photo_url);
    }

    public function test_the_basket_photo_must_be_an_image(): void
    {
        Storage::fake('public');
        $order = $this->readyOrder();

        $this->postJson("/api/v1/preparateur/orders/{$order->id}/ready", [
            '_method' => 'PATCH',
            'photo' => UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('photo');
    }

    public function test_the_livreur_sees_where_to_collect(): void
    {
        $order = $this->readyOrder($depot);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/ready")->assertOk();

        Sanctum::actingAs($this->createStaff('livreur'), ['*']);
        $listed = $this->getJson('/api/v1/deliveries/ready-orders')->assertOk()->json('data.0');

        // Sans le depot il saurait qu'une commande l'attend, mais pas ou.
        $this->assertSame($depot->name, $listed['preparateur']['market']['depot']['name']);
    }

    public function test_starting_a_delivery_records_the_pickup_time(): void
    {
        $order = $this->readyOrder();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/ready")->assertOk();

        $livreur = $this->createStaff('livreur');
        Sanctum::actingAs($livreur, ['*']);

        $deliveryId = $this->postJson("/api/v1/deliveries/{$order->id}/accept")->assertOk()->json('data.id');
        $this->patchJson("/api/v1/deliveries/{$deliveryId}/start")->assertOk();

        $delivery = Delivery::find($deliveryId);

        // Un seul geste, deux faits : il prend le panier et il part.
        $this->assertSame('in_transit', $delivery->status->value);
        $this->assertNotNull($delivery->pickup_time);
        $this->assertSame('in_delivery', $order->fresh()->status->value);
    }

    public function test_the_client_sees_the_livreur_position_while_in_transit(): void
    {
        $order = $this->readyOrder();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/ready")->assertOk();

        $livreur = $this->createStaff('livreur');
        Sanctum::actingAs($livreur, ['*']);
        $deliveryId = $this->postJson("/api/v1/deliveries/{$order->id}/accept")->json('data.id');
        $this->patchJson("/api/v1/deliveries/{$deliveryId}/start")->assertOk();

        $this->postJson("/api/v1/deliveries/{$deliveryId}/location", [
            'latitude' => 6.3700,
            'longitude' => 2.4200,
        ])->assertOk();

        Sanctum::actingAs($order->client, ['*']);
        $payload = $this->getJson("/api/v1/orders/{$order->id}")->assertOk()->json('data');

        $this->assertEqualsWithDelta(6.37, $payload['delivery']['current_latitude'], 0.001);
        $this->assertNotNull($payload['delivery']['last_location_update']);
        // Appeler celui qui arrive est normal.
        $this->assertSame($livreur->phone, $payload['delivery']['livreur']['phone']);
    }

    public function test_the_livreur_phone_is_hidden_once_the_run_is_over(): void
    {
        $order = $this->readyOrder();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/ready")->assertOk();

        $livreur = $this->createStaff('livreur');
        Sanctum::actingAs($livreur, ['*']);
        $deliveryId = $this->postJson("/api/v1/deliveries/{$order->id}/accept")->json('data.id');
        $this->patchJson("/api/v1/deliveries/{$deliveryId}/start")->assertOk();
        $this->postJson("/api/v1/deliveries/{$deliveryId}/confirm", [
            'notes' => 'Remis en main propre',
        ])->assertOk();

        Sanctum::actingAs($order->client, ['*']);
        $payload = $this->getJson("/api/v1/orders/{$order->id}")->assertOk()->json('data');

        // Garder le numero personnel du livreur apres la course ne l'est pas.
        $this->assertArrayNotHasKey('phone', $payload['delivery']['livreur']);
    }

    public function test_another_livreur_cannot_push_a_position(): void
    {
        $order = $this->readyOrder();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/ready")->assertOk();

        Sanctum::actingAs($this->createStaff('livreur'), ['*']);
        $deliveryId = $this->postJson("/api/v1/deliveries/{$order->id}/accept")->json('data.id');

        Sanctum::actingAs($this->createStaff('livreur'), ['*']);
        $this->postJson("/api/v1/deliveries/{$deliveryId}/location", [
            'latitude' => 6.3,
            'longitude' => 2.4,
        ])->assertNotFound();
    }
}

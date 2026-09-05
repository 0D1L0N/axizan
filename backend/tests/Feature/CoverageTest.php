<?php

namespace Tests\Feature;

use App\Models\DeliverySlot;
use App\Models\DeliveryZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Couverture : le Littoral et l'Atlantique.
 *
 * Une commune retiree de la couverture ne s'efface pas — des adresses, des
 * commandes et des factures pointent dessus. Elle se DESACTIVE : elle
 * disparait des choix offerts, et l'historique reste lisible.
 *
 * Ce test verrouille la consequence qui compte : une zone inactive ne doit
 * plus etre proposee ni reservable.
 */
class CoverageTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_only_active_zones_are_offered(): void
    {
        DeliveryZone::create(['name' => 'Ouidah', 'base_delivery_fee' => 1800, 'is_active' => true]);
        DeliveryZone::create(['name' => 'Porto-Novo', 'base_delivery_fee' => 1200, 'is_active' => false]);

        $names = array_column($this->getJson('/api/v1/delivery-zones')->assertOk()->json('data'), 'name');

        $this->assertSame(['Ouidah'], $names);
    }

    public function test_the_slots_of_a_retired_commune_are_not_bookable(): void
    {
        $zone = DeliveryZone::create(['name' => 'Porto-Novo', 'base_delivery_fee' => 1200, 'is_active' => false]);

        // Desactiver la zone ne suffit pas : l'API des creneaux interroge
        // `delivery_slots.is_active`. Les deux doivent tomber ensemble.
        $slot = DeliverySlot::create([
            'zone_id' => $zone->id,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'display_label' => '09:00 - 10:00',
            'max_orders_per_slot' => 20,
            'is_active' => false,
        ]);

        $offered = $this->getJson("/api/v1/delivery-slots?zone_id={$zone->id}")->assertOk()->json('data');

        $this->assertSame([], array_column($offered, 'id'));

        // Et l'API refuse la reservation directe, pas seulement l'affichage.
        $client = $this->createClient();
        $address = $this->createAddressFor($client, $zone);
        $product = $this->createProduct();

        Sanctum::actingAs($client, ['*']);
        $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [['type' => 'product', 'product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonPath('error', 'slot_unavailable');
    }

    public function test_an_order_placed_before_the_retirement_stays_readable(): void
    {
        [$zone, $slot] = $this->createZoneWithSlot(1200);
        $client = $this->createClient();
        $address = $this->createAddressFor($client, $zone);
        $product = $this->createProduct();

        Sanctum::actingAs($client, ['*']);
        $orderId = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [['type' => 'product', 'product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');

        // La commune quitte la couverture APRES la commande.
        $zone->update(['is_active' => false]);

        // Effacer la zone aurait troue l'historique : la commande, son adresse
        // et sa future facture pointent dessus.
        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.delivery_address.delivery_zone_id', $zone->id);
    }

    public function test_the_delivery_fee_follows_the_commune(): void
    {
        // Le tarif suit la distance au depot : Ze est a l'autre bout de
        // l'Atlantique, Cotonou l'abrite.
        $far = DeliveryZone::create(['name' => 'Zè', 'base_delivery_fee' => 2500, 'is_active' => true]);
        $slot = $this->createHourlySlot($far, '23:00');

        $client = $this->createClient();
        $address = $this->createAddressFor($client, $far);
        $product = $this->createProduct(['base_price' => 1000]);

        Sanctum::actingAs($client, ['*']);
        $order = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [['type' => 'product', 'product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('data');

        $this->assertSame('2500.00', $order['delivery_fee']);
    }
}

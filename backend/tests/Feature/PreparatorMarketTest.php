<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Depot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Espace preparateur.
 *
 * Modele : Axizan a des contrats fournisseurs, le preparateur se sert sans
 * payer. Il n'enregistre donc aucun prix — mais il DOIT enregistrer chez qui
 * il a pris chaque article, sans quoi le point avec les partenaires est
 * impossible et l'information ne se reconstitue pas.
 */
class PreparatorMarketTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    /** Cree une commande payee, prete a etre prise par un preparateur. */
    private function paidOrder($client, $zone, array $products): Order
    {
        $slot = $this->createHourlySlot($zone, '23:00');
        $address = $this->createAddressFor($client, $zone);

        Sanctum::actingAs($client, ['*']);
        $response = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => array_map(
                fn ($p) => ['type' => 'product', 'product_id' => $p->id, 'quantity' => 2],
                $products
            ),
        ])->assertCreated();

        $order = Order::find($response->json('data.id'));

        $admin = $this->createStaff('admin');
        Sanctum::actingAs($admin, ['*']);
        $this->postJson("/api/v1/admin/orders/{$order->id}/validate-payment", [
            'reference_number' => 'MTN-TEST',
        ])->assertOk();

        return $order->fresh();
    }

    public function test_a_preparateur_only_sees_orders_of_his_market(): void
    {
        [$zoneA] = $this->createZoneWithSlot();
        [$zoneB] = $this->createZoneWithSlot();
        [$marketA] = $this->createMarketFor($zoneA);
        [$marketB] = $this->createMarketFor($zoneB);

        $this->paidOrder($this->createClient(), $zoneA, [$this->createProduct()]);
        $this->paidOrder($this->createClient(), $zoneB, [$this->createProduct()]);

        Sanctum::actingAs($this->createPreparateurFor($marketA), ['*']);
        $response = $this->getJson('/api/v1/preparateur/orders')->assertOk();

        // Un preparateur de Porto-Novo ne doit pas voir Cotonou : il ne peut
        // pas l'honorer.
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($marketA->name, $response->json('meta.market.name'));
    }

    public function test_a_preparateur_without_a_market_is_told_so(): void
    {
        Sanctum::actingAs($this->createStaff('preparateur'), ['*']);

        $this->getJson('/api/v1/preparateur/orders')
            ->assertStatus(409)
            ->assertJsonPath('error', 'no_market');
    }

    public function test_accepting_an_order_outside_your_market_is_refused(): void
    {
        [$zoneA] = $this->createZoneWithSlot();
        [$zoneB] = $this->createZoneWithSlot();
        [$marketA] = $this->createMarketFor($zoneA);
        $this->createMarketFor($zoneB);

        $order = $this->paidOrder($this->createClient(), $zoneB, [$this->createProduct()]);

        Sanctum::actingAs($this->createPreparateurFor($marketA), ['*']);

        // Sans ce controle, un identifiant devine suffirait a prendre la
        // commande d'un autre marche.
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")
            ->assertStatus(403)
            ->assertJsonPath('error', 'outside_market');
    }

    public function test_suppliers_are_scoped_to_the_market(): void
    {
        [$zoneA] = $this->createZoneWithSlot();
        [$zoneB] = $this->createZoneWithSlot();
        [$marketA] = $this->createMarketFor($zoneA);
        [$marketB] = $this->createMarketFor($zoneB);

        Sanctum::actingAs($this->createPreparateurFor($marketA), ['*']);
        $suppliers = $this->getJson('/api/v1/preparateur/suppliers')->assertOk()->json('data');

        $this->assertCount(1, $suppliers);
        $this->assertSame($marketA->id, Supplier::find($suppliers[0]['id'])->market_id);
    }

    public function test_recording_a_collected_item_stores_the_supplier(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market, $supplier] = $this->createMarketFor($zone);
        $order = $this->paidOrder($this->createClient(), $zone, [$this->createProduct()]);
        $item = $order->items()->first();

        Sanctum::actingAs($this->createPreparateurFor($market), ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();

        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$item->id}", [
            'collection_status' => 'collected',
            'supplier_id' => $supplier->id,
        ])->assertOk();

        $item->refresh();
        $this->assertSame('collected', $item->collection_status);
        $this->assertSame($supplier->id, $item->supplier_id);
        $this->assertNotNull($item->collected_at);
    }

    public function test_a_supplier_from_another_market_is_refused(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$zoneB] = $this->createZoneWithSlot();
        [$market] = $this->createMarketFor($zone);
        [, $foreignSupplier] = $this->createMarketFor($zoneB);

        $order = $this->paidOrder($this->createClient(), $zone, [$this->createProduct()]);
        $item = $order->items()->first();

        Sanctum::actingAs($this->createPreparateurFor($market), ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();

        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$item->id}", [
            'collection_status' => 'collected',
            'supplier_id' => $foreignSupplier->id,
        ])->assertStatus(422)->assertJsonPath('error', 'supplier_outside_market');
    }

    public function test_a_replacement_records_the_product_actually_taken(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market, $supplier] = $this->createMarketFor($zone);
        $original = $this->createProduct();
        $replacement = $this->createProduct();

        $order = $this->paidOrder($this->createClient(), $zone, [$original]);
        $item = $order->items()->first();

        Sanctum::actingAs($this->createPreparateurFor($market), ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();

        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$item->id}", [
            'collection_status' => 'replaced',
            'supplier_id' => $supplier->id,
            'replacement_product_id' => $replacement->id,
        ])->assertOk();

        $item->refresh();
        $this->assertSame($replacement->id, $item->replacement_product_id);
        // Le client verra ce qu'il recoit vraiment.
        $this->assertSame($replacement->id, $item->effectiveProduct()->id);
    }

    public function test_a_replacement_needs_a_product(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market, $supplier] = $this->createMarketFor($zone);
        $order = $this->paidOrder($this->createClient(), $zone, [$this->createProduct()]);
        $item = $order->items()->first();

        Sanctum::actingAs($this->createPreparateurFor($market), ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();

        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$item->id}", [
            'collection_status' => 'replaced',
            'supplier_id' => $supplier->id,
        ])->assertStatus(422)->assertJsonPath('error', 'replacement_required');
    }

    public function test_a_client_who_asked_for_removal_is_not_overridden(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market, $supplier] = $this->createMarketFor($zone);
        $order = $this->paidOrder($this->createClient(), $zone, [$this->createProduct()]);

        $item = $order->items()->first();
        $item->update(['substitution_preference' => 'remove']);

        Sanctum::actingAs($this->createPreparateurFor($market), ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();

        // La consigne du client prime sur le choix du preparateur.
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$item->id}", [
            'collection_status' => 'replaced',
            'supplier_id' => $supplier->id,
            'replacement_product_id' => $this->createProduct()->id,
        ])->assertStatus(422)->assertJsonPath('error', 'replacement_refused');
    }

    public function test_an_order_cannot_be_ready_with_untreated_items(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market, $supplier] = $this->createMarketFor($zone);
        $order = $this->paidOrder($this->createClient(), $zone, [$this->createProduct(), $this->createProduct()]);

        Sanctum::actingAs($this->createPreparateurFor($market), ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/start")->assertOk();

        $first = $order->items()->first();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$first->id}", [
            'collection_status' => 'collected',
            'supplier_id' => $supplier->id,
        ])->assertOk();

        $this->markReady($order->id)
            ->assertStatus(422)
            ->assertJsonPath('error', 'items_pending');
    }

    public function test_a_collected_item_without_a_supplier_blocks_ready(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market] = $this->createMarketFor($zone);
        $order = $this->paidOrder($this->createClient(), $zone, [$this->createProduct()]);
        $item = $order->items()->first();

        Sanctum::actingAs($this->createPreparateurFor($market), ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/start")->assertOk();

        $item->update(['collection_status' => 'collected', 'collected_at' => now()]);

        // Sans fournisseur, le point avec les partenaires devient impossible.
        $this->markReady($order->id)
            ->assertStatus(422)
            ->assertJsonPath('error', 'supplier_missing');
    }

    public function test_a_removed_item_is_journalled_for_the_admin(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market, $supplier] = $this->createMarketFor($zone);
        $order = $this->paidOrder($this->createClient(), $zone, [$this->createProduct(), $this->createProduct()]);

        Sanctum::actingAs($this->createPreparateurFor($market), ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/start")->assertOk();

        $items = $order->items()->get();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$items[0]->id}", [
            'collection_status' => 'collected',
            'supplier_id' => $supplier->id,
        ])->assertOk();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$items[1]->id}", [
            'collection_status' => 'removed',
        ])->assertOk();

        $response = $this->markReady($order->id)->assertOk();

        $this->assertSame(1, $response->json('meta.removed_count'));
        // Le client a paye cet article : l'admin doit pouvoir trancher le
        // remboursement. Le montant n'est pas ajuste automatiquement.
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event' => 'items.removed',
        ]);
    }

    public function test_a_released_order_returns_to_the_market_pool(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market, $supplier] = $this->createMarketFor($zone);
        $order = $this->paidOrder($this->createClient(), $zone, [$this->createProduct(), $this->createProduct()]);

        $preparateur = $this->createPreparateurFor($market);
        Sanctum::actingAs($preparateur, ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/start")->assertOk();

        $item = $order->items()->first();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$item->id}", [
            'collection_status' => 'collected',
            'supplier_id' => $supplier->id,
        ])->assertOk();

        $response = $this->patchJson("/api/v1/preparateur/orders/{$order->id}/release", [
            'reason' => 'Moto en panne',
        ])->assertOk();

        $order->refresh();
        $this->assertSame('pending', $order->status->value);
        $this->assertNull($order->preparateur_id);
        $this->assertSame(1, $response->json('meta.items_reset'));

        // Les lignes repartent vierges : ce qui a ete recupere est chez le
        // precedent, le laisser coche ferait croire le panier a moitie pret.
        foreach ($order->items()->get() as $line) {
            $this->assertSame('pending', $line->collection_status);
            $this->assertNull($line->supplier_id);
        }

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event' => 'order.released',
            'reason' => 'Moto en panne',
        ]);
    }

    public function test_releasing_requires_a_reason(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market] = $this->createMarketFor($zone);
        $order = $this->paidOrder($this->createClient(), $zone, [$this->createProduct()]);

        Sanctum::actingAs($this->createPreparateurFor($market), ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();

        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/release")
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_a_released_order_can_be_taken_by_another_preparateur(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market] = $this->createMarketFor($zone);
        $order = $this->paidOrder($this->createClient(), $zone, [$this->createProduct()]);

        Sanctum::actingAs($this->createPreparateurFor($market), ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/release", ['reason' => 'Malade'])->assertOk();

        $second = $this->createPreparateurFor($market);
        Sanctum::actingAs($second, ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();

        $this->assertSame($second->id, $order->fresh()->preparateur_id);
    }

    public function test_suppliers_are_suggested_from_the_product_category(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market, $supplier] = $this->createMarketFor($zone);

        // La specialite de l'etal porte le nom d'une categorie produit :
        // c'est ce rapprochement qui evite un choix par ligne.
        $category = Category::create(['name' => $supplier->speciality]);
        $product = $this->createProduct();
        $product->update(['category_id' => $category->id]);

        $order = $this->paidOrder($this->createClient(), $zone, [$product]);

        Sanctum::actingAs($this->createPreparateurFor($market), ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();

        $item = $this->getJson('/api/v1/preparateur/orders/mine')->assertOk()->json('data.0.items.0');

        $this->assertSame($supplier->id, $item['suggested_supplier_id']);
    }

    public function test_the_depot_is_given_to_the_preparateur(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market] = $this->createMarketFor($zone);
        $depot = Depot::create([
            'name' => 'Dépôt Cotonou',
            'address' => 'Rue 12.34',
            'city' => 'Cotonou',
        ]);
        $market->update(['depot_id' => $depot->id]);

        Sanctum::actingAs($this->createPreparateurFor($market), ['*']);

        // Il doit savoir ou deposer avant de quitter le marche.
        $this->getJson('/api/v1/preparateur/orders/mine')
            ->assertOk()
            ->assertJsonPath('meta.depot.name', 'Dépôt Cotonou');
    }

    public function test_another_preparateur_cannot_touch_your_items(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market, $supplier] = $this->createMarketFor($zone);
        $order = $this->paidOrder($this->createClient(), $zone, [$this->createProduct()]);
        $item = $order->items()->first();

        Sanctum::actingAs($this->createPreparateurFor($market), ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/accept")->assertOk();

        Sanctum::actingAs($this->createPreparateurFor($market), ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$item->id}", [
            'collection_status' => 'collected',
            'supplier_id' => $supplier->id,
        ])->assertNotFound();
    }
}

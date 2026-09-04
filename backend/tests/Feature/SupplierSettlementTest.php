<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Le point avec les fournisseurs partenaires.
 *
 * Axizan ne paie rien au marche : le preparateur se sert, le reglement vient
 * apres. Ces tests verrouillent les deux regles qui font qu'un point tient :
 * une ligne deja reglee ne revient jamais, et on ne solde pas une dette dont
 * une partie n'est pas chiffree.
 */
class SupplierSettlementTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    /**
     * Une commande dont toutes les lignes ont ete retirees chez $supplier.
     *
     * @return array{0: Order, 1: Supplier, 2: Product, 3: \App\Models\User}
     */
    private function collectedOrder(float $quantity = 2, ?array $tariff = null): array
    {
        [$zone, $slot] = $this->createZoneWithSlot();
        [$market, $supplier] = $this->createMarketFor($zone);
        $preparateur = $this->createPreparateurFor($market);

        $client = $this->createClient();
        $address = $this->createAddressFor($client, $zone);
        $product = $this->createProduct(['name' => 'Tomate fraîche']);

        if ($tariff !== null) {
            SupplierPrice::create([
                'supplier_id' => $supplier->id,
                'product_id' => $product->id,
                'unit_price' => $tariff['unit_price'],
            ]);
        }

        Sanctum::actingAs($client, ['*']);
        $orderId = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [['type' => 'product', 'product_id' => $product->id, 'quantity' => $quantity]],
        ])->assertCreated()->json('data.id');

        $order = Order::find($orderId);
        $order->update(['preparateur_id' => $preparateur->id, 'status' => 'preparing']);

        Sanctum::actingAs($preparateur, ['*']);
        foreach ($order->items as $item) {
            $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$item->id}", [
                'collection_status' => 'collected',
                'supplier_id' => $supplier->id,
            ])->assertOk();
        }

        return [$order->fresh(), $supplier, $product, $preparateur];
    }

    public function test_collecting_stamps_the_agreed_price_on_the_line(): void
    {
        [$order] = $this->collectedOrder(quantity: 3, tariff: ['unit_price' => 800]);

        $item = $order->items->first();

        // Le prix est FIGE au retrait : renegocier plus tard ne doit pas faire
        // bouger un point deja regle.
        $this->assertSame('800.00', $item->actual_unit_price);
        $this->assertSame('2400.00', $item->actual_total_price);
    }

    public function test_a_line_without_an_agreed_price_is_left_unpriced(): void
    {
        [$order] = $this->collectedOrder();

        // Zero serait un mensonge : la dette existe, on ne sait juste pas
        // encore la chiffrer.
        $this->assertNull($order->items->first()->actual_total_price);
    }

    public function test_the_admin_sees_what_is_owed_to_each_supplier(): void
    {
        [, $supplier] = $this->collectedOrder(quantity: 3, tariff: ['unit_price' => 800]);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $response = $this->getJson('/api/v1/admin/suppliers')->assertOk();

        $this->assertSame($supplier->id, $response->json('data.0.id'));
        $this->assertSame(2400, $response->json('data.0.amount'));
        $this->assertSame(2400, $response->json('meta.total_due'));
        $this->assertSame(0, $response->json('meta.blocked_count'));
    }

    public function test_a_supplier_missing_a_tariff_is_flagged_as_blocking(): void
    {
        $this->collectedOrder();

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $response = $this->getJson('/api/v1/admin/suppliers')->assertOk();

        $this->assertSame(1, $response->json('data.0.untariffed_count'));
        $this->assertSame(1, $response->json('meta.blocked_count'));
    }

    public function test_the_statement_groups_by_article(): void
    {
        [, $supplier] = $this->collectedOrder(quantity: 3, tariff: ['unit_price' => 800]);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $line = $this->getJson("/api/v1/admin/suppliers/{$supplier->id}/statement")
            ->assertOk()
            ->json('data.lines.0');

        $this->assertSame('Tomate fraîche', $line['name']);
        $this->assertEqualsWithDelta(3, $line['quantity'], 0.001);
        $this->assertSame(800, $line['unit_price']);
        $this->assertSame(2400, $line['total']);
        $this->assertTrue($line['tariffed']);
    }

    public function test_settling_marks_the_lines_and_empties_the_statement(): void
    {
        [$order, $supplier] = $this->collectedOrder(quantity: 3, tariff: ['unit_price' => 800]);

        $admin = $this->createStaff('admin');
        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/v1/admin/suppliers/{$supplier->id}/settle", [
            'method' => 'mtn',
            'reference' => 'MP260905.0900.X1',
        ])->assertCreated()->assertJsonPath('data.amount', '2400.00');

        $this->assertNotNull($order->items->first()->fresh()->settlement_id);

        // Le releve repart de zero : ce qui est regle ne se represente pas.
        $this->assertSame(
            0,
            $this->getJson("/api/v1/admin/suppliers/{$supplier->id}/statement")->json('data.amount')
        );
    }

    public function test_the_same_lines_can_never_be_settled_twice(): void
    {
        [, $supplier] = $this->collectedOrder(quantity: 3, tariff: ['unit_price' => 800]);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $this->postJson("/api/v1/admin/suppliers/{$supplier->id}/settle")->assertCreated();

        // Le double paiement est empeche par le schema, pas par la vigilance
        // de celui qui fait les comptes.
        $this->postJson("/api/v1/admin/suppliers/{$supplier->id}/settle")
            ->assertStatus(422)
            ->assertJsonPath('error', 'nothing_to_settle');

        $this->assertSame(1, \App\Models\SupplierSettlement::count());
    }

    public function test_settling_is_refused_while_a_line_has_no_tariff(): void
    {
        [, $supplier] = $this->collectedOrder();

        Sanctum::actingAs($this->createStaff('admin'), ['*']);

        // Solder une dette dont on ignore une partie, c'est perdre l'ecart
        // pour toujours : la ligne serait marquee reglee sans avoir ete payee.
        $this->postJson("/api/v1/admin/suppliers/{$supplier->id}/settle")
            ->assertStatus(422)
            ->assertJsonPath('error', 'untariffed_items');

        $this->assertSame(0, \App\Models\SupplierSettlement::count());
    }

    public function test_setting_a_tariff_reprices_the_lines_already_collected(): void
    {
        [$order, $supplier, $product] = $this->collectedOrder(quantity: 4);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);

        // Renseigner un tarif oublié doit débloquer le règlement tout seul :
        // sinon l'administrateur devrait deviner qu'il faut aussi recalculer.
        $this->putJson("/api/v1/admin/suppliers/{$supplier->id}/prices", [
            'prices' => [['product_id' => $product->id, 'unit_price' => 750]],
        ])->assertOk()->assertJsonPath('meta.repriced_items', 1);

        $this->assertSame('3000.00', $order->items->first()->fresh()->actual_total_price);

        $this->postJson("/api/v1/admin/suppliers/{$supplier->id}/settle")->assertCreated();
    }

    public function test_a_settled_line_is_never_repriced(): void
    {
        [$order, $supplier, $product] = $this->collectedOrder(quantity: 2, tariff: ['unit_price' => 800]);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $this->postJson("/api/v1/admin/suppliers/{$supplier->id}/settle")->assertCreated();

        $this->putJson("/api/v1/admin/suppliers/{$supplier->id}/prices", [
            'prices' => [['product_id' => $product->id, 'unit_price' => 2000]],
        ])->assertOk()->assertJsonPath('meta.repriced_items', 0);

        // Renegocier un tarif ne réécrit pas l'histoire comptable.
        $this->assertSame('1600.00', $order->items->first()->fresh()->actual_total_price);
    }

    public function test_the_prices_screen_lists_what_blocks_the_settlement(): void
    {
        [, $supplier, $product] = $this->collectedOrder();

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $data = $this->getJson("/api/v1/admin/suppliers/{$supplier->id}/prices")->assertOk()->json('data');

        // Chercher l'article à tarifer dans tout le catalogue serait absurde :
        // l'écran remonte ceux qui ont déjà été retirés chez ce fournisseur.
        $this->assertSame([], $data['agreed']);
        $this->assertSame($product->id, $data['missing'][0]['product_id']);
    }

    public function test_a_replacement_is_charged_as_the_product_actually_taken(): void
    {
        [$order, $supplier, $product] = $this->collectedOrder(quantity: 2, tariff: ['unit_price' => 800]);
        $substitute = $this->createProduct(['name' => 'Tomate cerise']);

        SupplierPrice::create([
            'supplier_id' => $supplier->id,
            'product_id' => $substitute->id,
            'unit_price' => 1200,
        ]);

        Sanctum::actingAs($order->preparateur, ['*']);
        $item = $order->items->first();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$item->id}", [
            'collection_status' => 'replaced',
            'supplier_id' => $supplier->id,
            'replacement_product_id' => $substitute->id,
        ])->assertOk();

        // C'est la tomate cerise qui est sortie de l'étal, pas la tomate
        // fraîche : facturer l'article commandé serait payer le mauvais prix.
        $this->assertSame('2400.00', $item->fresh()->actual_total_price);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $line = $this->getJson("/api/v1/admin/suppliers/{$supplier->id}/statement")->json('data.lines.0');

        $this->assertSame('Tomate cerise', $line['name']);
    }

    public function test_a_non_admin_sees_nothing_of_the_settlement(): void
    {
        [, $supplier] = $this->collectedOrder();

        Sanctum::actingAs($this->createClient(), ['*']);

        $this->getJson('/api/v1/admin/suppliers')->assertStatus(403);
        $this->getJson("/api/v1/admin/suppliers/{$supplier->id}/statement")->assertStatus(403);
        $this->postJson("/api/v1/admin/suppliers/{$supplier->id}/settle")->assertStatus(403);
    }
}

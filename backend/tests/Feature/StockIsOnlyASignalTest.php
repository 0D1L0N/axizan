<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Axizan ne detient aucun stock.
 *
 * Les articles sont retires chez des fournisseurs partenaires au moment de la
 * commande. `stock_quantity` n'est donc qu'un signal indicatif ; le seul
 * verrou est `is_available`, que l'administration bascule quand un produit
 * disparait du marche national.
 */
class StockIsOnlyASignalTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    private function orderQuantity($product, float $quantity)
    {
        [$zone] = $this->createZoneWithSlot();
        $client = $this->createClient();
        $slot = $this->createHourlySlot($zone, '23:00');
        $address = $this->createAddressFor($client, $zone);

        Sanctum::actingAs($client, ['*']);

        return $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [['type' => 'product', 'product_id' => $product->id, 'quantity' => $quantity]],
        ]);
    }

    public function test_ordering_far_above_the_stock_figure_is_accepted(): void
    {
        // 45 kg affichés, 200 commandés : le fournisseur en a des sacs.
        $product = $this->createProduct(['stock_quantity' => 45]);

        $this->orderQuantity($product, 200)->assertCreated();

        $this->assertSame(1, Order::count());
    }

    public function test_a_zero_stock_product_can_still_be_ordered(): void
    {
        // Un compteur à zéro ne veut rien dire : rien n'est décrémenté.
        $product = $this->createProduct(['stock_quantity' => 0]);

        $this->orderQuantity($product, 3)->assertCreated();
    }

    public function test_an_unavailable_product_is_refused(): void
    {
        // C'est le seul verrou : l'article n'est plus trouvable nulle part.
        $product = $this->createProduct(['is_available' => false, 'stock_quantity' => 999]);

        $this->orderQuantity($product, 1)
            ->assertStatus(400)
            ->assertJsonPath('error', 'order_creation_failed');

        $this->assertSame(0, Order::count());
    }

    public function test_ordering_never_decrements_the_stock_figure(): void
    {
        $product = $this->createProduct(['stock_quantity' => 50]);

        $this->orderQuantity($product, 10)->assertCreated();

        // Le chiffre reste tel quel : ce n'est pas un inventaire.
        $this->assertSame('50.000', $product->fresh()->stock_quantity);
    }
}

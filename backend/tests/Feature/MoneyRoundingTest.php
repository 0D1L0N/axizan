<?php

namespace Tests\Feature;

use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Le franc CFA n'a pas de subdivision : aucun montant stocké ou affiché ne
 * doit comporter de décimale.
 */
class MoneyRoundingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_percent_of_rounds_to_whole_francs(): void
    {
        // 10 % de 1275 = 127,5 — un montant qui n'existe pas.
        $this->assertSame(128, Money::percentOf(1275, 10));
        $this->assertSame(0, Money::percentOf(0, 10));
        $this->assertSame(125, Money::percentOf(1250, 10));
    }

    public function test_line_total_handles_fractional_quantities(): void
    {
        // 1,5 kg d'ignames a 1833 F/kg = 2749,5
        $this->assertSame(2750, Money::lineTotal(1833, 1.5));
        $this->assertSame(900, Money::lineTotal(600, 1.5));
    }

    public function test_an_order_never_stores_a_fractional_amount(): void
    {
        $client = $this->createClient();
        [$zone] = $this->createZoneWithSlot(fee: 800);
        $slot = $this->createHourlySlot($zone, '23:00');
        $address = $this->createAddressFor($client, $zone);

        // Prix et quantite choisis pour tomber sur des demi-francs a chaque
        // etape : ligne, sous-total, puis taxe.
        $product = $this->createProduct(['base_price' => 1275]);

        Sanctum::actingAs($client, ['*']);

        $response = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [
                ['type' => 'product', 'product_id' => $product->id, 'quantity' => 1.5],
            ],
        ]);

        $response->assertCreated();
        $order = $response->json('data');

        foreach (['subtotal', 'tax', 'delivery_fee', 'total'] as $field) {
            $amount = (float) $order[$field];
            $this->assertSame(
                $amount,
                floor($amount),
                "Le champ {$field} vaut {$amount} : le XOF n'admet pas de décimale."
            );
        }
    }

    public function test_subtotal_equals_the_sum_of_its_lines(): void
    {
        $client = $this->createClient();
        [$zone] = $this->createZoneWithSlot();
        $slot = $this->createHourlySlot($zone, '23:00');
        $address = $this->createAddressFor($client, $zone);
        $first = $this->createProduct(['base_price' => 1833]);
        $second = $this->createProduct(['base_price' => 1275]);

        Sanctum::actingAs($client, ['*']);

        $response = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [
                ['type' => 'product', 'product_id' => $first->id, 'quantity' => 1.5],
                ['type' => 'product', 'product_id' => $second->id, 'quantity' => 2.5],
            ],
        ])->assertCreated();

        $order = \App\Models\Order::with('items')->find($response->json('data.id'));

        // Le client additionne les lignes qu'il a sous les yeux : le total doit
        // tomber juste.
        $this->assertSame(
            (float) $order->items->sum('total_price'),
            (float) $order->subtotal
        );
    }
}

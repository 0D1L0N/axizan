<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Marge reelle et preuve de paiement.
 *
 * Le prix affiche est fige a la commande ; le prix paye se negocie au marche.
 * Sans ces deux mesures, l'ecart disparait dans la nature.
 */
class PurchaseMarginTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    private function orderWithTwoLines(): Order
    {
        $client = $this->createClient();
        [$zone] = $this->createZoneWithSlot();
        $slot = $this->createHourlySlot($zone, '23:00');
        $address = $this->createAddressFor($client, $zone);
        $first = $this->createProduct(['base_price' => 2000]);
        $second = $this->createProduct(['base_price' => 1000]);

        Sanctum::actingAs($client, ['*']);

        $response = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [
                ['type' => 'product', 'product_id' => $first->id, 'quantity' => 2],
                ['type' => 'product', 'product_id' => $second->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        return Order::with('items')->find($response->json('data.id'));
    }

    public function test_margin_is_null_until_every_line_is_recorded(): void
    {
        $order = $this->orderWithTwoLines();

        $this->assertNull($order->grossMargin());
        $this->assertNull($order->purchaseCost());

        // Une seule ligne saisie : toujours pas de marge. Un chiffre calcule
        // sur un panier a moitie renseigne serait faux et trompeur.
        $order->items->first()->update([
            'actual_quantity' => 2,
            'actual_unit_price' => 1800,
            'actual_total_price' => 3600,
            'purchased_at' => now(),
        ]);

        $this->assertNull($order->fresh()->load('items')->grossMargin());
    }

    public function test_margin_is_the_difference_once_all_lines_are_recorded(): void
    {
        $order = $this->orderWithTwoLines();

        // Sous-total facture : 2 x 2000 + 1 x 1000 = 5000
        $this->assertSame(5000.0, (float) $order->subtotal);

        foreach ($order->items as $index => $item) {
            $item->update([
                'actual_quantity' => $item->quantity,
                'actual_unit_price' => $index === 0 ? 1800 : 1100,
                'actual_total_price' => $index === 0 ? 3600 : 1100,
                'purchased_at' => now(),
            ]);
        }

        $order = $order->fresh()->load('items');

        // Achat reel : 3600 + 1100 = 4700
        $this->assertSame(4700, $order->purchaseCost());
        $this->assertSame(300, $order->grossMargin());
    }

    public function test_a_negative_margin_is_reported_as_such(): void
    {
        $order = $this->orderWithTwoLines();

        // Le marche a monte : l'achat coute plus cher que ce qu'a paye le
        // client. C'est precisement ce qu'on veut pouvoir constater.
        foreach ($order->items as $item) {
            $item->update([
                'actual_quantity' => $item->quantity,
                'actual_unit_price' => 3000,
                'actual_total_price' => 3000 * (float) $item->quantity,
                'purchased_at' => now(),
            ]);
        }

        $this->assertLessThan(0, $order->fresh()->load('items')->grossMargin());
    }

    public function test_payment_validation_requires_a_reference(): void
    {
        $order = $this->orderWithTwoLines();
        $admin = $this->createStaff('admin');

        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/v1/admin/orders/{$order->id}/validate-payment")
            ->assertStatus(422)
            ->assertJsonValidationErrors('reference_number');
    }

    public function test_payment_validation_records_who_validated(): void
    {
        $order = $this->orderWithTwoLines();
        $admin = $this->createStaff('admin');

        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/v1/admin/orders/{$order->id}/validate-payment", [
            'reference_number' => 'MOOV-88-4412',
            'payment_provider' => 'moov',
            'phone_number' => '+22997000000',
        ])->assertOk();

        $payment = Payment::where('order_id', $order->id)->firstOrFail();

        $this->assertSame('MOOV-88-4412', $payment->reference_number);
        $this->assertSame($admin->id, $payment->validated_by);
        $this->assertNotNull($payment->validated_at);
        $this->assertSame('moov', $payment->payment_provider);
    }

    public function test_a_payment_cannot_be_validated_twice(): void
    {
        $order = $this->orderWithTwoLines();
        $admin = $this->createStaff('admin');

        Sanctum::actingAs($admin, ['*']);

        $payload = ['reference_number' => 'MTN-01'];
        $this->postJson("/api/v1/admin/orders/{$order->id}/validate-payment", $payload)->assertOk();

        // Sans ce garde-fou, une double validation ecrase la premiere trace.
        $this->postJson("/api/v1/admin/orders/{$order->id}/validate-payment", $payload)
            ->assertStatus(422)
            ->assertJsonPath('error', 'already_validated');
    }
}

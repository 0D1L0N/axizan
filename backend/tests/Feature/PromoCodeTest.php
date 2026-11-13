<?php

namespace Tests\Feature;

use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class PromoCodeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    private function promo(array $overrides = []): Promotion
    {
        return Promotion::create(array_merge([
            'code' => 'MARCHE500',
            'discount_type' => 'fixed_amount',
            'discount_value' => 500,
            'min_order_amount' => 5000,
            'max_usage_per_user' => 1,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'is_active' => true,
        ], $overrides));
    }

    private function placeOrder(?string $code, int $unitPrice = 6000)
    {
        $client = $this->createClient();
        [$zone, $slot] = $this->createZoneWithSlot(fee: 500);
        $address = $this->createAddressFor($client, $zone);
        $product = $this->createProduct(['base_price' => $unitPrice]);

        Sanctum::actingAs($client, ['*']);

        return $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'promo_code' => $code,
            'items' => [['type' => 'product', 'product_id' => $product->id, 'quantity' => 1]],
        ]);
    }

    public function test_preview_returns_the_discount_for_a_valid_code(): void
    {
        $this->promo();
        Sanctum::actingAs($this->createClient(), ['*']);

        // Saisie en minuscules : le code est insensible a la casse.
        $this->postJson('/api/v1/promotions/check', ['code' => 'marche500', 'subtotal' => 6000])
            ->assertOk()
            ->assertJsonPath('data.discount', 500);
    }

    public function test_preview_explains_why_a_code_does_not_apply(): void
    {
        $this->promo();
        Sanctum::actingAs($this->createClient(), ['*']);

        $this->postJson('/api/v1/promotions/check', ['code' => 'INCONNU', 'subtotal' => 6000])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Code promo inconnu.');

        $this->postJson('/api/v1/promotions/check', ['code' => 'MARCHE500', 'subtotal' => 3000])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ce code s’applique dès 5 000 FCFA d’achats.');
    }

    public function test_an_order_placed_with_a_code_gets_the_discount(): void
    {
        $this->promo();

        $response = $this->placeOrder('MARCHE500')->assertCreated();

        // 6000 + 600 de taxe + 500 de livraison - 500 de reduction
        $this->assertEquals(500, (float) $response->json('data.promo_discount'));
        $this->assertEquals(6600, (float) $response->json('data.total'));
        $this->assertDatabaseCount('promotion_usages', 1);
    }

    public function test_an_order_with_a_rejected_code_is_not_created(): void
    {
        $this->promo();

        $this->placeOrder('MARCHE500', unitPrice: 2000)
            ->assertStatus(400)
            ->assertJsonPath('message', 'Ce code s’applique dès 5 000 FCFA d’achats.');

        $this->assertDatabaseCount('orders', 0);
    }
}

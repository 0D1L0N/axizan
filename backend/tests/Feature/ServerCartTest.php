<?php

namespace Tests\Feature;

use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Panier serveur : il doit suivre le client d'un appareil a l'autre.
 */
class ServerCartTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_adding_the_same_product_twice_updates_the_line(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct();
        Sanctum::actingAs($client, ['*']);

        $this->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 2])->assertOk();
        $response = $this->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 5])->assertOk();

        // Une ligne par (utilisateur, produit) : pas de doublon.
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(1, CartItem::where('user_id', $client->id)->count());
        $this->assertSame('5.000', CartItem::first()->quantity);
    }

    public function test_fractional_quantities_survive(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct(['price_type' => 'kg']);
        Sanctum::actingAs($client, ['*']);

        $this->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 1.5])->assertOk();

        // 1,5 kg d'ignames doit tenir : la colonne est decimal(10,3).
        $this->assertSame('1.500', CartItem::first()->quantity);
    }

    public function test_merge_keeps_the_larger_quantity(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct();
        Sanctum::actingAs($client, ['*']);

        $this->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 3])->assertOk();

        // Rejouer la meme fusion ne doit pas doubler la commande : on garde le
        // maximum, operation stable si elle se repete.
        $payload = ['items' => [['product_id' => $product->id, 'quantity' => 2]]];
        $this->postJson('/api/v1/cart/merge', $payload)->assertOk();
        $this->postJson('/api/v1/cart/merge', $payload)->assertOk();

        $this->assertSame('3.000', CartItem::first()->quantity);
    }

    public function test_merge_adopts_a_larger_local_quantity(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct();
        Sanctum::actingAs($client, ['*']);

        $this->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
        $this->postJson('/api/v1/cart/merge', [
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
        ])->assertOk();

        $this->assertSame('4.000', CartItem::first()->quantity);
    }

    public function test_a_client_never_sees_another_cart(): void
    {
        $client = $this->createClient();
        $other = $this->createClient();
        $product = $this->createProduct();

        CartItem::create(['user_id' => $other->id, 'product_id' => $product->id, 'quantity' => 9]);

        Sanctum::actingAs($client, ['*']);
        $this->assertCount(0, $this->getJson('/api/v1/cart')->json('data'));
    }

    public function test_removing_and_clearing(): void
    {
        $client = $this->createClient();
        $first = $this->createProduct();
        $second = $this->createProduct();
        Sanctum::actingAs($client, ['*']);

        $this->postJson('/api/v1/cart', ['product_id' => $first->id, 'quantity' => 1]);
        $this->postJson('/api/v1/cart', ['product_id' => $second->id, 'quantity' => 1]);

        $this->deleteJson("/api/v1/cart/{$first->id}")->assertOk();
        $this->assertSame(1, CartItem::where('user_id', $client->id)->count());

        $this->deleteJson('/api/v1/cart')->assertOk();
        $this->assertSame(0, CartItem::where('user_id', $client->id)->count());
    }

    public function test_the_cart_requires_authentication(): void
    {
        $this->getJson('/api/v1/cart')->assertUnauthorized();
    }
}

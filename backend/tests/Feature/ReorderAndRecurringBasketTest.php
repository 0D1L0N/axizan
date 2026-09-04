<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Notification;
use App\Models\Order;
use App\Models\RecurringBasket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ReorderAndRecurringBasketTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    private function placeOrder($client, array $products): Order
    {
        [$zone] = $this->createZoneWithSlot();
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

        return Order::find($response->json('data.id'));
    }

    public function test_reorder_fills_the_cart_from_a_past_order(): void
    {
        $client = $this->createClient();
        $first = $this->createProduct();
        $second = $this->createProduct();
        $order = $this->placeOrder($client, [$first, $second]);

        $this->postJson("/api/v1/orders/{$order->id}/reorder")->assertOk();

        $this->assertSame(2, CartItem::where('user_id', $client->id)->count());
        $this->assertSame('2.000', CartItem::where('product_id', $first->id)->first()->quantity);
    }

    public function test_reorder_reports_articles_no_longer_available(): void
    {
        $client = $this->createClient();
        $available = $this->createProduct();
        $gone = $this->createProduct();
        $order = $this->placeOrder($client, [$available, $gone]);

        $gone->update(['is_available' => false]);

        $response = $this->postJson("/api/v1/orders/{$order->id}/reorder")->assertOk();

        // Signale, pas ignore : le client doit savoir ce qui manque.
        $this->assertSame([$gone->name], $response->json('meta.skipped'));
        $this->assertSame(1, CartItem::where('user_id', $client->id)->count());
    }

    public function test_reorder_refuses_someone_elses_order(): void
    {
        $client = $this->createClient();
        $intruder = $this->createClient();
        $order = $this->placeOrder($client, [$this->createProduct()]);

        Sanctum::actingAs($intruder, ['*']);
        $this->postJson("/api/v1/orders/{$order->id}/reorder")->assertNotFound();
    }

    public function test_a_recurring_basket_can_be_created_and_reloaded(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct();
        Sanctum::actingAs($client, ['*']);

        $created = $this->postJson('/api/v1/recurring-baskets', [
            'name' => 'Courses du mois',
            'frequency' => 'monthly',
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertCreated();

        $basketId = $created->json('data.id');

        // Le premier rappel tombe a l'echeance, pas tout de suite.
        $basket = RecurringBasket::find($basketId);
        $this->assertTrue($basket->next_reminder_at->isFuture());

        $this->postJson("/api/v1/recurring-baskets/{$basketId}/load")->assertOk();
        $this->assertSame('3.000', CartItem::where('user_id', $client->id)->first()->quantity);
    }

    public function test_changing_the_frequency_reschedules_the_reminder(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct();
        Sanctum::actingAs($client, ['*']);

        $id = $this->postJson('/api/v1/recurring-baskets', [
            'name' => 'Hebdo',
            'frequency' => 'monthly',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->json('data.id');

        $this->patchJson("/api/v1/recurring-baskets/{$id}", ['frequency' => 'weekly'])->assertOk();

        // Sans reprogrammation, le panier resterait sur l'ancien rythme.
        $this->assertTrue(RecurringBasket::find($id)->next_reminder_at->lte(now()->addDays(8)));
    }

    public function test_a_basket_belongs_to_its_owner_only(): void
    {
        $owner = $this->createClient();
        $intruder = $this->createClient();
        $product = $this->createProduct();

        Sanctum::actingAs($owner, ['*']);
        $id = $this->postJson('/api/v1/recurring-baskets', [
            'name' => 'Privé',
            'frequency' => 'weekly',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->json('data.id');

        Sanctum::actingAs($intruder, ['*']);
        $this->getJson('/api/v1/recurring-baskets')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/recurring-baskets/{$id}/load")->assertNotFound();
        $this->deleteJson("/api/v1/recurring-baskets/{$id}")->assertNotFound();
    }

    public function test_due_baskets_produce_a_reminder_and_reschedule(): void
    {
        $client = $this->createClient();
        $product = $this->createProduct();
        Sanctum::actingAs($client, ['*']);

        $id = $this->postJson('/api/v1/recurring-baskets', [
            'name' => 'Courses du mois',
            'frequency' => 'monthly',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->json('data.id');

        RecurringBasket::find($id)->update(['next_reminder_at' => now()->subDay()]);

        $this->artisan('axizan:send-basket-reminders')->assertSuccessful();

        $this->assertSame(1, Notification::where('user_id', $client->id)
            ->where('title', 'Votre panier « Courses du mois »')
            ->count());

        // Reprogramme, donc un second passage ne renvoie pas le meme rappel.
        $this->artisan('axizan:send-basket-reminders')->assertSuccessful();
        $this->assertSame(1, Notification::where('user_id', $client->id)
            ->where('title', 'Votre panier « Courses du mois »')
            ->count());
    }
}

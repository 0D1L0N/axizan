<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Le remplacement en direct.
 *
 * Le client apprenait a la livraison qu'un produit avait change. Il le sait
 * maintenant pendant que le preparateur est encore a l'etal, et peut refuser.
 *
 * LE PARTI PRIS : prevenir et laisser contester, pas demander et attendre. Le
 * preparateur n'attend jamais — l'immobiliser sur un reseau capricieux, en
 * attendant la reponse d'un client peut-etre en reunion, couterait plus cher
 * que le probleme qu'on resout. Sans reponse, sa decision tient.
 */
class LiveSubstitutionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    /** @return array{0: Order, 1: \App\Models\User, 2: \App\Models\User, 3: \App\Models\Product} */
    private function orderInPreparation(): array
    {
        [$zone, $slot] = $this->createZoneWithSlot();
        [$market, $supplier] = $this->createMarketFor($zone);
        $preparateur = $this->createPreparateurFor($market);

        $client = $this->createClient();
        $address = $this->createAddressFor($client, $zone);
        $product = $this->createProduct(['name' => 'Gombo frais']);

        Sanctum::actingAs($client, ['*']);
        $orderId = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [['type' => 'product', 'product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()->json('data.id');

        $order = Order::find($orderId);
        $order->update([
            'payment_status' => 'completed',
            'preparateur_id' => $preparateur->id,
            'status' => OrderStatus::PREPARING,
        ]);

        return [$order->fresh(), $client, $preparateur, $supplier];
    }

    private function replaceItem(Order $order, $supplier, $substitute): OrderItem
    {
        $item = $order->items->first();

        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$item->id}", [
            'collection_status' => 'replaced',
            'supplier_id' => $supplier->id,
            'replacement_product_id' => $substitute->id,
        ])->assertOk();

        return $item->fresh();
    }

    public function test_the_client_is_warned_the_moment_an_item_is_replaced(): void
    {
        [$order, $client, $preparateur, $supplier] = $this->orderInPreparation();
        $substitute = $this->createProduct(['name' => 'Gombo sec']);

        Sanctum::actingAs($preparateur, ['*']);
        $item = $this->replaceItem($order, $supplier, $substitute);

        $notice = Notification::where('user_id', $client->id)
            ->where('type', NotificationType::ITEM_SUBSTITUTED)
            ->first();

        // Le préparateur est encore à l'étal : c'est le seul moment où le
        // client peut encore refuser.
        $this->assertNotNull($notice);
        $this->assertStringContainsString('Gombo frais', $notice->message);
        $this->assertStringContainsString('Gombo sec', $notice->message);
        $this->assertNotNull($item->substitution_notified_at);
    }

    public function test_a_removed_item_warns_too(): void
    {
        [$order, $client, $preparateur] = $this->orderInPreparation();

        Sanctum::actingAs($preparateur, ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$order->items->first()->id}", [
            'collection_status' => 'removed',
        ])->assertOk();

        $message = Notification::where('user_id', $client->id)
            ->where('type', NotificationType::ITEM_SUBSTITUTED)
            ->value('message');

        $this->assertStringContainsString('introuvable', $message);
    }

    public function test_only_the_first_substitution_of_an_order_costs_an_sms(): void
    {
        [$zone, $slot] = $this->createZoneWithSlot();
        [$market, $supplier] = $this->createMarketFor($zone);
        $preparateur = $this->createPreparateurFor($market);

        $client = $this->createClient();
        $address = $this->createAddressFor($client, $zone);
        $one = $this->createProduct(['name' => 'Gombo']);
        $two = $this->createProduct(['name' => 'Tomate']);

        Sanctum::actingAs($client, ['*']);
        $orderId = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [
                ['type' => 'product', 'product_id' => $one->id, 'quantity' => 1],
                ['type' => 'product', 'product_id' => $two->id, 'quantity' => 1],
            ],
        ])->assertCreated()->json('data.id');

        $order = Order::find($orderId);
        $order->update(['preparateur_id' => $preparateur->id, 'status' => OrderStatus::PREPARING]);

        Sanctum::actingAs($preparateur, ['*']);
        foreach ($order->items as $item) {
            $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$item->id}", [
                'collection_status' => 'removed',
            ])->assertOk();
        }

        $notices = Notification::where('user_id', $client->id)
            ->where('type', NotificationType::ITEM_SUBSTITUTED)
            ->get();

        // Deux traces consultables, un seul SMS : il ne sert qu'à faire ouvrir
        // l'application, et trois messages coûteraient trois fois le prix pour
        // dire la même chose.
        $this->assertCount(2, $notices);
        $this->assertSame(1, $notices->where('is_sent_sms', true)->count());
    }

    public function test_refusing_takes_the_item_out_of_the_basket(): void
    {
        [$order, $client, $preparateur, $supplier] = $this->orderInPreparation();
        $substitute = $this->createProduct(['name' => 'Gombo sec']);

        Sanctum::actingAs($preparateur, ['*']);
        $item = $this->replaceItem($order, $supplier, $substitute);

        Sanctum::actingAs($client, ['*']);
        $this->postJson("/api/v1/orders/{$order->id}/items/{$item->id}/substitution", [
            'response' => 'refused',
        ])->assertOk();

        $item->refresh();

        $this->assertSame('removed', $item->collection_status);
        $this->assertNull($item->replacement_product_id);
        $this->assertSame('refused', $item->substitution_response);
    }

    public function test_refusing_clears_what_would_have_been_owed_to_the_supplier(): void
    {
        [$order, $client, $preparateur, $supplier] = $this->orderInPreparation();
        $substitute = $this->createProduct(['name' => 'Gombo sec']);

        \App\Models\SupplierPrice::create([
            'supplier_id' => $supplier->id,
            'product_id' => $substitute->id,
            'unit_price' => 900,
        ]);

        Sanctum::actingAs($preparateur, ['*']);
        $item = $this->replaceItem($order, $supplier, $substitute);
        $this->assertSame('1800.00', $item->actual_total_price);

        Sanctum::actingAs($client, ['*']);
        $this->postJson("/api/v1/orders/{$order->id}/items/{$item->id}/substitution", ['response' => 'refused'])
            ->assertOk();

        // Rien n'est sorti de l'étal : le facturer au fournisseur reviendrait à
        // payer une marchandise jamais retirée.
        $this->assertNull($item->fresh()->actual_total_price);
        $this->assertNull($item->fresh()->supplier_id);
    }

    public function test_accepting_keeps_the_replacement(): void
    {
        [$order, $client, $preparateur, $supplier] = $this->orderInPreparation();
        $substitute = $this->createProduct(['name' => 'Gombo sec']);

        Sanctum::actingAs($preparateur, ['*']);
        $item = $this->replaceItem($order, $supplier, $substitute);

        Sanctum::actingAs($client, ['*']);
        $this->postJson("/api/v1/orders/{$order->id}/items/{$item->id}/substitution", ['response' => 'accepted'])
            ->assertOk();

        $item->refresh();

        $this->assertSame('replaced', $item->collection_status);
        $this->assertSame($substitute->id, $item->replacement_product_id);
        $this->assertSame('accepted', $item->substitution_response);
    }

    public function test_the_window_closes_when_the_basket_leaves_the_market(): void
    {
        [$order, $client, $preparateur, $supplier] = $this->orderInPreparation();
        $substitute = $this->createProduct(['name' => 'Gombo sec']);

        Sanctum::actingAs($preparateur, ['*']);
        $item = $this->replaceItem($order, $supplier, $substitute);

        $order->update(['status' => OrderStatus::READY]);

        Sanctum::actingAs($client, ['*']);

        // Passé le dépôt, le préparateur est parti et l'article est dans le
        // panier : refuser promettrait au client ce qu'on ne peut plus tenir.
        $this->postJson("/api/v1/orders/{$order->id}/items/{$item->id}/substitution", ['response' => 'refused'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'window_closed');

        $this->assertSame('replaced', $item->fresh()->collection_status);
    }

    public function test_silence_lets_the_preparateur_decision_stand(): void
    {
        [$order, , $preparateur, $supplier] = $this->orderInPreparation();
        $substitute = $this->createProduct(['name' => 'Gombo sec']);

        Sanctum::actingAs($preparateur, ['*']);
        $item = $this->replaceItem($order, $supplier, $substitute);

        // Le client ne répond pas : c'est la seule règle qui fonctionne quand
        // l'un des deux n'a pas de réseau.
        $this->assertNull($item->substitution_response);
        $this->assertSame('replaced', $item->collection_status);

        Sanctum::actingAs($preparateur, ['*']);
        $this->markReady($order->id)->assertOk();

        $this->assertSame('ready', $order->fresh()->status->value);
    }

    public function test_a_collected_item_is_not_a_substitution(): void
    {
        [$order, $client, $preparateur, $supplier] = $this->orderInPreparation();

        Sanctum::actingAs($preparateur, ['*']);
        $item = $order->items->first();
        $this->patchJson("/api/v1/preparateur/orders/{$order->id}/items/{$item->id}", [
            'collection_status' => 'collected',
            'supplier_id' => $supplier->id,
        ])->assertOk();

        // Un article trouvé tel quel ne dérange personne.
        $this->assertSame(0, Notification::where('type', NotificationType::ITEM_SUBSTITUTED)->count());

        Sanctum::actingAs($client, ['*']);
        $this->postJson("/api/v1/orders/{$order->id}/items/{$item->id}/substitution", ['response' => 'refused'])
            ->assertNotFound();
    }

    public function test_another_client_cannot_answer_for_someone_else(): void
    {
        [$order, , $preparateur, $supplier] = $this->orderInPreparation();
        $substitute = $this->createProduct(['name' => 'Gombo sec']);

        Sanctum::actingAs($preparateur, ['*']);
        $item = $this->replaceItem($order, $supplier, $substitute);

        Sanctum::actingAs($this->createClient(), ['*']);

        $this->postJson("/api/v1/orders/{$order->id}/items/{$item->id}/substitution", ['response' => 'refused'])
            ->assertNotFound();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Le preparateur depose au depot et repart chercher la commande suivante.
 *
 * Personne ne se passe le panier de la main a la main : sans signal, il attend
 * qu'un livreur pense a rafraichir son ecran, et une course fraiche vieillit au
 * depot pendant que des livreurs disponibles ne savent rien.
 */
class DepotHandoffNotificationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_marking_a_basket_ready_alerts_every_active_livreur(): void
    {
        [$zone, $slot] = $this->createZoneWithSlot();
        [$market] = $this->createMarketFor($zone);
        $preparateur = $this->createPreparateurFor($market);

        $onDuty = $this->createStaff('livreur');
        $suspended = $this->createStaff('livreur', ['status' => 'suspended']);
        $otherClient = $this->createClient();

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

        $order = Order::find($orderId);
        $order->update(['preparateur_id' => $preparateur->id]);

        app(\App\Services\OrderService::class)->markReady($order);

        $alerts = Notification::where('type', NotificationType::BASKET_AT_DEPOT)->get();

        // Le livreur suspendu et les clients n'ont rien a faire de ce signal.
        $this->assertSame([$onDuty->id], $alerts->pluck('user_id')->all());
        $this->assertStringContainsString($order->order_number, $alerts->first()->message);

        $this->assertSame(
            0,
            Notification::where('user_id', $suspended->id)->count()
            + Notification::where('user_id', $otherClient->id)->count()
        );
    }

    public function test_the_alert_names_the_depot_where_the_basket_waits(): void
    {
        [$zone, $slot] = $this->createZoneWithSlot();
        [$market] = $this->createMarketFor($zone);

        $depot = \App\Models\Depot::create([
            'name' => 'Dépôt Fidjrossè',
            'address' => 'Rue 12.045',
            'city' => 'Cotonou',
            'is_active' => true,
        ]);
        $market->update(['depot_id' => $depot->id]);

        $preparateur = $this->createPreparateurFor($market);
        $livreur = $this->createStaff('livreur');

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

        $order = Order::find($orderId);
        $order->update(['preparateur_id' => $preparateur->id]);

        app(\App\Services\OrderService::class)->markReady($order);

        // Le livreur doit savoir OU aller sans ouvrir la commande.
        $this->assertStringContainsString(
            'Dépôt Fidjrossè',
            Notification::where('user_id', $livreur->id)
                ->where('type', NotificationType::BASKET_AT_DEPOT)
                ->value('message')
        );
    }
}

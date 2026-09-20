<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\Depot;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Le passage du panier au livreur.
 *
 * Trois choses se jouent a ce moment-la et aucune n'etait couverte :
 *
 * 1. La commande doit porter le nom du livreur. `orders.livreur_id` n'etait
 *    ecrit par aucun chemin vivant — la salle de controle affichait un contact
 *    « Livreur » vide pour toutes les commandes, y compris celles en cours de
 *    route. L'ecran fait pour appeler la personne en charge ne pouvait pas la
 *    nommer.
 *
 * 2. Le client doit etre prevenu au depart. config/notifications.php declare
 *    ORDER_OUT_FOR_DELIVERY en SMS avec ce motif : « le client doit etre la.
 *    C'est le SMS le plus utile de tout le parcours. » Il ne partait jamais.
 *
 * 3. Un seul livreur par panier. Le depot montre la liste a tous les livreurs
 *    actifs ; deux doigts sur « Accepter » ne doivent pas ouvrir deux courses.
 */
class LivreurHandoverTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    /** Une commande deposee au depot, en attente d'un livreur. */
    private function orderAtDepot(): Order
    {
        [$zone] = $this->createZoneWithSlot();
        [$market, $supplier] = $this->createMarketFor($zone);

        $depot = Depot::create([
            'name' => 'Dépôt Cotonou',
            'address' => 'Rue 12.34',
            'city' => 'Cotonou',
            'landmark' => 'Face à la station',
        ]);
        $market->update(['depot_id' => $depot->id]);

        $client = $this->createClient();
        $slot = $this->createHourlySlot($zone, '23:00');
        $address = $this->createAddressFor($client, $zone);

        Sanctum::actingAs($client, ['*']);
        $orderId = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [['type' => 'product', 'product_id' => $this->createProduct()->id, 'quantity' => 2]],
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $this->postJson("/api/v1/admin/orders/{$orderId}/validate-payment", [
            'reference_number' => 'MTN-1',
        ])->assertOk();

        Sanctum::actingAs($this->createPreparateurFor($market), ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$orderId}/accept")->assertOk();
        $this->patchJson("/api/v1/preparateur/orders/{$orderId}/start")->assertOk();

        $order = Order::find($orderId);
        foreach ($order->items as $item) {
            $this->patchJson("/api/v1/preparateur/orders/{$orderId}/items/{$item->id}", [
                'collection_status' => 'collected',
                'supplier_id' => $supplier->id,
            ])->assertOk();
        }

        $this->markReady($orderId)->assertOk();

        return $order->fresh();
    }

    public function test_the_order_carries_the_name_of_the_livreur_who_took_it(): void
    {
        $order = $this->orderAtDepot();
        $livreur = $this->createStaff('livreur');

        Sanctum::actingAs($livreur, ['*']);
        $this->postJson("/api/v1/deliveries/{$order->id}/accept")->assertOk();

        // Le defaut exact qui se voyait en production : la course existait,
        // mais la commande ne savait pas qui l'avait prise.
        $this->assertSame($livreur->id, $order->fresh()->livreur_id);
    }

    public function test_the_control_room_can_reach_the_livreur_on_the_road(): void
    {
        $order = $this->orderAtDepot();
        $livreur = $this->createStaff('livreur', ['phone' => '+22961000001']);

        Sanctum::actingAs($livreur, ['*']);
        $this->postJson("/api/v1/deliveries/{$order->id}/accept")->assertOk();

        // C'est la raison d'etre de l'ecran : quand un client appelle pour dire
        // que personne n'arrive, l'administrateur doit avoir un numero.
        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $this->getJson("/api/v1/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.order.livreur.name', $livreur->name)
            ->assertJsonPath('data.order.livreur.phone', '+22961000001');
    }

    public function test_taking_the_basket_is_written_in_the_journal(): void
    {
        $order = $this->orderAtDepot();
        $livreur = $this->createStaff('livreur');

        Sanctum::actingAs($livreur, ['*']);
        $this->postJson("/api/v1/deliveries/{$order->id}/accept")->assertOk();

        // Le statut de la commande ne change pas a ce moment : sans trace
        // explicite, « qui est parti avec ce panier » serait introuvable apres
        // coup.
        $event = OrderEvent::where('order_id', $order->id)
            ->where('event', 'delivery.assigned')
            ->first();

        $this->assertNotNull($event, 'La prise du panier doit laisser une trace.');
        $this->assertSame($livreur->id, $event->context['livreur_id']);
    }

    public function test_the_client_is_warned_when_the_livreur_leaves_the_depot(): void
    {
        $order = $this->orderAtDepot();
        $livreur = $this->createStaff('livreur', ['phone' => '+22961000002']);

        Sanctum::actingAs($livreur, ['*']);
        $deliveryId = $this->postJson("/api/v1/deliveries/{$order->id}/accept")
            ->assertOk()
            ->json('data.id');

        // Rien encore : le livreur est au depot, le client n'a rien a faire de
        // cette information.
        $this->assertSame(0, Notification::where('user_id', $order->client_id)
            ->where('type', NotificationType::ORDER_OUT_FOR_DELIVERY)
            ->count());

        $this->patchJson("/api/v1/deliveries/{$deliveryId}/start")->assertOk();

        $notice = Notification::where('user_id', $order->client_id)
            ->where('type', NotificationType::ORDER_OUT_FOR_DELIVERY)
            ->first();

        $this->assertNotNull($notice, 'Le depart du depot doit prevenir le client.');

        // Le numero est dans le texte : si le client a un empechement, il doit
        // pouvoir appeler sans rouvrir l'application.
        $this->assertStringContainsString($livreur->name, $notice->message);
        $this->assertStringContainsString('+22961000002', $notice->message);
    }

    public function test_the_livreur_is_not_told_about_the_course_he_just_took(): void
    {
        $order = $this->orderAtDepot();
        $livreur = $this->createStaff('livreur');

        Sanctum::actingAs($livreur, ['*']);
        $deliveryId = $this->postJson("/api/v1/deliveries/{$order->id}/accept")
            ->assertOk()
            ->json('data.id');
        $this->patchJson("/api/v1/deliveries/{$deliveryId}/start")->assertOk();

        // Un message pour lui apprendre ce qu'il a fait lui-meme trois secondes
        // plus tot n'apprend rien a personne, et use la notification.
        $this->assertSame(0, Notification::where('user_id', $livreur->id)->count());
    }

    public function test_a_second_livreur_gets_a_sentence_not_a_sql_error(): void
    {
        $order = $this->orderAtDepot();

        Sanctum::actingAs($this->createStaff('livreur'), ['*']);
        $this->postJson("/api/v1/deliveries/{$order->id}/accept")->assertOk();

        // Le depot affiche la liste a tous les livreurs actifs : la collision
        // est le cas normal, pas l'incident. La base l'arretait deja — mais en
        // renvoyant « SQLSTATE[23505] » sur le telephone du second.
        Sanctum::actingAs($this->createStaff('livreur'), ['*']);
        $this->postJson("/api/v1/deliveries/{$order->id}/accept")
            ->assertStatus(409)
            ->assertJsonPath('error', 'already_taken')
            ->assertJsonPath('message', 'Un autre livreur vient de prendre ce panier.');
    }
}

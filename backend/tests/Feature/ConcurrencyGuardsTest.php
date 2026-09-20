<?php

namespace Tests\Feature;

use App\Exceptions\SlotUnavailableException;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Les ressources que deux personnes peuvent vouloir en meme temps.
 *
 * Axizan ne detient aucun stock — les ignames ne manquent pas au marche. Ce qui
 * est rare ici, ce sont les HEURES : la capacite d'un creneau est le seul stock
 * reel de la plateforme. Et les commandes elles-memes, que plusieurs
 * preparateurs voient apparaitre sur la meme liste.
 *
 * Ces deux ressources se lisaient puis s'ecrivaient sans rien entre les deux.
 *
 * Note sur ce que ces tests prouvent : la suite tourne sur SQLite, ou
 * `lockForUpdate` ne compile rien. Le verrouillage lui-meme ne s'eprouve qu'en
 * PostgreSQL. Ce qui se teste ici, c'est que le CONTROLE a bien ete deplace la
 * ou le verrou le protege — dans la transaction — au lieu de rester a la porte.
 */
class ConcurrencyGuardsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_the_last_spot_is_defended_inside_the_transaction(): void
    {
        [$zone] = $this->createZoneWithSlot();
        $slot = $this->createHourlySlot($zone, '23:00');
        $slot->update(['max_orders_per_slot' => 1]);

        $client = $this->createClient();
        $address = $this->createAddressFor($client, $zone);
        $date = now()->addDay()->toDateString();

        Sanctum::actingAs($client, ['*']);
        $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => $date,
            'items' => [['product_id' => $this->createProduct()->id, 'quantity' => 1]],
        ])->assertCreated();

        $before = Order::count();

        /*
         * On appelle le service directement, en court-circuitant le controle
         * d'entree du controleur. C'est exactement la situation de la course :
         * ce controle a ete passe alors que la place etait encore libre, et la
         * commande arrive a l'ecriture apres que quelqu'un d'autre l'a prise.
         *
         * Avant, plus rien ne l'arretait a ce stade — la place etait vendue
         * deux fois, et le preparateur le decouvrait le matin.
         */
        $this->expectException(SlotUnavailableException::class);

        try {
            app(OrderService::class)->createOrder($client, [
                ['product_id' => $this->createProduct()->id, 'quantity' => 1],
            ], [
                'delivery_address_id' => $address->id,
                'delivery_slot_id' => $slot->id,
                'delivery_date' => $date,
            ]);
        } finally {
            // La transaction est annulee : pas de commande orpheline, pas de
            // ligne de paiement a rattraper a la main.
            $this->assertSame($before, Order::count());
        }
    }

    public function test_a_closed_slot_is_refused_at_the_write_too(): void
    {
        [$zone] = $this->createZoneWithSlot();
        $slot = $this->createHourlySlot($zone, '23:00');

        $client = $this->createClient();
        $address = $this->createAddressFor($client, $zone);

        // Une zone suspendue en pleine journee ferme ses creneaux. Une commande
        // deja engagee dans le tunnel ne doit pas se poser dessus.
        $slot->update(['is_active' => false]);

        $this->expectException(SlotUnavailableException::class);

        app(OrderService::class)->createOrder($client, [
            ['product_id' => $this->createProduct()->id, 'quantity' => 1],
        ], [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
        ]);
    }

    public function test_a_second_preparateur_is_told_the_order_is_already_taken(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market] = $this->createMarketFor($zone);

        $client = $this->createClient();
        $slot = $this->createHourlySlot($zone, '23:00');
        $address = $this->createAddressFor($client, $zone);

        Sanctum::actingAs($client, ['*']);
        $orderId = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [['product_id' => $this->createProduct()->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $this->postJson("/api/v1/admin/orders/{$orderId}/validate-payment", [
            'reference_number' => 'MTN-1',
        ])->assertOk();

        $first = $this->createPreparateurFor($market);
        $second = $this->createPreparateurFor($market);

        Sanctum::actingAs($first, ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$orderId}/accept")->assertOk();

        /*
         * Les deux regardent la meme liste : le second a la commande sous les
         * yeux au moment ou le premier la prend. Il doit apprendre qu'elle lui
         * echappe, et pourquoi — pas « cette commande ne peut pas etre
         * acceptee », qui laisse croire a un defaut de la commande.
         */
        Sanctum::actingAs($second, ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$orderId}/accept")
            ->assertStatus(400)
            ->assertJsonPath('message', 'Cette commande vient d’être prise par quelqu’un d’autre.');

        // Et le premier la garde : le second ne l'a pas ecrasee.
        $this->assertSame($first->id, Order::find($orderId)->preparateur_id);
    }

    public function test_the_preparateur_who_already_holds_the_order_is_not_locked_out(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market] = $this->createMarketFor($zone);

        $client = $this->createClient();
        $slot = $this->createHourlySlot($zone, '23:00');
        $address = $this->createAddressFor($client, $zone);

        Sanctum::actingAs($client, ['*']);
        $orderId = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [['product_id' => $this->createProduct()->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $this->postJson("/api/v1/admin/orders/{$orderId}/validate-payment", [
            'reference_number' => 'MTN-1',
        ])->assertOk();

        $preparateur = $this->createPreparateurFor($market);

        Sanctum::actingAs($preparateur, ['*']);
        $this->patchJson("/api/v1/preparateur/orders/{$orderId}/accept")->assertOk();

        /*
         * Sur un reseau instable, le meme doigt appuie deux fois. Le garde-fou
         * ne doit pas lui reprocher d'avoir pris sa propre commande : il verra
         * un message qui accuse un collegue inexistant, et appellera le bureau.
         */
        $this->patchJson("/api/v1/preparateur/orders/{$orderId}/accept")
            ->assertStatus(400)
            ->assertJsonPath('message', 'Cette commande ne peut pas être acceptée');

        $this->assertSame($preparateur->id, Order::find($orderId)->preparateur_id);
    }
}

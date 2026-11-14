<?php

namespace Tests\Feature;

use App\Models\DeliveryClosure;
use App\Models\DeliverySlot;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Les creneaux : capacite, zones, et jours fermes.
 *
 * La regle qui tient tout l'ecran, et que ces tests defendent ligne a ligne :
 * RIEN ICI NE TOUCHE AUX COMMANDES DEJA POSEES. Fermer une journee, suspendre
 * une zone ou baisser une capacite arrete les NOUVELLES reservations. Annuler
 * une commande payee est une decision d'argent, qui se prend une par une dans
 * la salle de controle.
 *
 * L'ecran dit combien sont concernees. Il ne decide pas a la place de
 * l'administrateur.
 */
class SlotAdministrationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    private function admin(): User
    {
        return $this->createStaff('admin');
    }

    /** Passe une commande sur un creneau, et renvoie la reponse brute. */
    private function order($client, $address, DeliverySlot $slot, $date)
    {
        Sanctum::actingAs($client, ['*']);

        return $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => $date,
            'items' => [['product_id' => $this->createProduct()->id, 'quantity' => 1]],
        ]);
    }

    public function test_a_closed_day_refuses_new_orders_and_says_why(): void
    {
        [$zone] = $this->createZoneWithSlot();
        $slot = $this->createHourlySlot($zone, '23:00');
        $client = $this->createClient();
        $address = $this->createAddressFor($client, $zone);
        $date = now()->addDay()->toDateString();

        Sanctum::actingAs($this->admin(), ['*']);
        $this->postJson('/api/v1/admin/creneaux/fermetures', [
            'closed_on' => $date,
            'reason' => 'Fête nationale — les marchés sont fermés',
        ])->assertCreated();

        /*
         * Le motif est repris tel quel. « Nous ne livrons pas ce jour-la » sans
         * raison donne l'impression d'une panne ; la raison fait choisir un
         * autre jour au lieu d'appeler le bureau.
         */
        $this->order($client, $address, $slot, $date)
            ->assertStatus(422)
            ->assertJsonPath('error', 'day_closed')
            ->assertJsonPath('message', 'Nous ne livrons pas ce jour-là : Fête nationale — les marchés sont fermés');
    }

    public function test_the_closure_is_enforced_at_the_write_too(): void
    {
        [$zone] = $this->createZoneWithSlot();
        $slot = $this->createHourlySlot($zone, '23:00');
        $client = $this->createClient();
        $address = $this->createAddressFor($client, $zone);
        $date = now()->addDay()->toDateString();

        DeliveryClosure::create(['closed_on' => $date, 'reason' => 'Marché en feu']);

        /*
         * Une journee peut se fermer pendant qu'un client est dans le tunnel —
         * c'est meme le cas d'une fermeture en urgence. Le controle d'entree
         * l'a laisse passer il y a deux minutes ; celui de la transaction a le
         * dernier mot.
         */
        $this->expectException(\App\Exceptions\SlotUnavailableException::class);

        app(\App\Services\OrderService::class)->createOrder($client, [
            ['product_id' => $this->createProduct()->id, 'quantity' => 1],
        ], [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => $date,
        ]);
    }

    public function test_closing_one_zone_leaves_the_others_open(): void
    {
        [$zoneA] = $this->createZoneWithSlot();
        [$zoneB] = $this->createZoneWithSlot();
        $slotA = $this->createHourlySlot($zoneA, '23:00');
        $slotB = $this->createHourlySlot($zoneB, '23:00');
        $date = now()->addDay()->toDateString();

        $clientA = $this->createClient();
        $clientB = $this->createClient();

        Sanctum::actingAs($this->admin(), ['*']);
        $this->postJson('/api/v1/admin/creneaux/fermetures', [
            'closed_on' => $date,
            'zone_id' => $zoneA->id,
            'reason' => 'Route coupée à Godomey',
        ])->assertCreated();

        // Une inondation a Godomey n'empeche pas de livrer a Fidjrosse.
        $this->order($clientA, $this->createAddressFor($clientA, $zoneA), $slotA, $date)
            ->assertStatus(422)
            ->assertJsonPath('error', 'day_closed');

        $this->order($clientB, $this->createAddressFor($clientB, $zoneB), $slotB, $date)
            ->assertCreated();
    }

    public function test_closing_a_day_never_touches_the_orders_already_placed(): void
    {
        [$zone] = $this->createZoneWithSlot();
        $slot = $this->createHourlySlot($zone, '23:00');
        $client = $this->createClient();
        $address = $this->createAddressFor($client, $zone);
        $date = now()->addDay()->toDateString();

        $orderId = $this->order($client, $address, $slot, $date)->assertCreated()->json('data.id');

        Sanctum::actingAs($this->admin(), ['*']);
        $response = $this->postJson('/api/v1/admin/creneaux/fermetures', [
            'closed_on' => $date,
            'reason' => 'Grève des transports',
        ])->assertCreated();

        /*
         * Le coeur de l'ecran. Annuler d'office rembourserait des clients sans
         * que personne l'ait decide — et une greve annoncee la veille est
         * parfois levee le matin. La commande reste ; l'administrateur tranche.
         */
        $this->assertSame('pending', Order::find($orderId)->status->value);

        // Mais la taire serait pire : l'administrateur croirait la journee
        // reglee, et ce client attendrait une livraison que personne ne fera.
        $response->assertJsonPath('affected_orders.0.order_number', Order::find($orderId)->order_number);
    }

    public function test_reopening_a_day_lets_orders_through_again(): void
    {
        [$zone] = $this->createZoneWithSlot();
        $slot = $this->createHourlySlot($zone, '23:00');
        $client = $this->createClient();
        $address = $this->createAddressFor($client, $zone);
        $date = now()->addDay()->toDateString();

        Sanctum::actingAs($this->admin(), ['*']);
        $closureId = $this->postJson('/api/v1/admin/creneaux/fermetures', [
            'closed_on' => $date,
            'reason' => 'Grève annoncée',
        ])->assertCreated()->json('data.id');

        $this->order($client, $address, $slot, $date)->assertStatus(422);

        // La grève est levée : la journée se rouvre.
        Sanctum::actingAs($this->admin(), ['*']);
        $this->deleteJson("/api/v1/admin/creneaux/fermetures/{$closureId}")->assertOk();

        $this->order($client, $address, $slot, $date)->assertCreated();
    }

    public function test_a_day_already_gone_cannot_be_closed(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);
        $this->postJson('/api/v1/admin/creneaux/fermetures', [
            'closed_on' => now()->subDay()->toDateString(),
            'reason' => 'Trop tard',
        ])->assertStatus(422)->assertJsonValidationErrors('closed_on');
    }

    public function test_closing_demands_a_reason(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);
        $this->postJson('/api/v1/admin/creneaux/fermetures', [
            'closed_on' => now()->addDay()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('reason');
    }

    public function test_the_client_sees_the_reason_instead_of_an_empty_day(): void
    {
        [$zone] = $this->createZoneWithSlot();
        $this->createHourlySlot($zone, '23:00');
        $date = now()->addDay()->toDateString();

        DeliveryClosure::create([
            'closed_on' => $date,
            'reason' => 'Fête de l’Indépendance',
        ]);

        Sanctum::actingAs($this->createClient(), ['*']);
        $response = $this->getJson("/api/v1/delivery-slots?zone_id={$zone->id}&date={$date}")
            ->assertOk();

        // Les creneaux restent affiches, barres : les masquer laisserait croire
        // a un bug, exactement comme pour un creneau deja passe.
        $response->assertJsonPath('meta.closed', true)
            ->assertJsonPath('meta.closed_reason', 'Fête de l’Indépendance')
            ->assertJsonPath('meta.has_bookable', false)
            ->assertJsonPath('data.0.unavailable_reason', 'closed')
            ->assertJsonPath('data.0.is_bookable', false);
    }

    public function test_capacity_cannot_drop_below_what_is_already_booked(): void
    {
        [$zone] = $this->createZoneWithSlot();
        $slot = $this->createHourlySlot($zone, '23:00');
        $date = now()->addDay()->toDateString();

        foreach (range(1, 3) as $ignored) {
            $client = $this->createClient();
            $this->order($client, $this->createAddressFor($client, $zone), $slot, $date)->assertCreated();
        }

        /*
         * Le creneau afficherait un nombre de places negatif — et surtout le
         * chiffre mentirait : les commandes sont posees, quelqu'un devra les
         * preparer. Baisser la capacite sert a ne plus en prendre, pas a
         * effacer celles qu'on a prises.
         */
        Sanctum::actingAs($this->admin(), ['*']);
        $this->patchJson("/api/v1/admin/creneaux/{$slot->id}", ['max_orders_per_slot' => 2])
            ->assertStatus(422)
            ->assertJsonPath('error', 'below_booked');

        $this->assertSame(20, $slot->fresh()->max_orders_per_slot);

        // Exactement ce qui est pose : accepte.
        $this->patchJson("/api/v1/admin/creneaux/{$slot->id}", ['max_orders_per_slot' => 3])
            ->assertOk();

        $this->assertSame(3, $slot->fresh()->max_orders_per_slot);
    }

    public function test_raising_the_capacity_opens_the_slot_again(): void
    {
        [$zone] = $this->createZoneWithSlot();
        $slot = $this->createHourlySlot($zone, '23:00');
        $slot->update(['max_orders_per_slot' => 1]);
        $date = now()->addDay()->toDateString();

        $first = $this->createClient();
        $this->order($first, $this->createAddressFor($first, $zone), $slot, $date)->assertCreated();

        $second = $this->createClient();
        $address = $this->createAddressFor($second, $zone);
        $this->order($second, $address, $slot, $date)->assertStatus(422);

        // Un samedi qui deborde : on ouvre une place de plus.
        Sanctum::actingAs($this->admin(), ['*']);
        $this->patchJson("/api/v1/admin/creneaux/{$slot->id}", ['max_orders_per_slot' => 2])->assertOk();

        $this->order($second, $address, $slot, $date)->assertCreated();
    }

    public function test_a_withdrawn_slot_stops_taking_orders(): void
    {
        [$zone] = $this->createZoneWithSlot();
        $slot = $this->createHourlySlot($zone, '23:00');
        $client = $this->createClient();
        $address = $this->createAddressFor($client, $zone);
        $date = now()->addDay()->toDateString();

        Sanctum::actingAs($this->admin(), ['*']);
        $this->patchJson("/api/v1/admin/creneaux/{$slot->id}", ['is_active' => false])->assertOk();

        $this->order($client, $address, $slot, $date)->assertStatus(422);
    }

    public function test_a_suspended_zone_stops_taking_orders_but_keeps_its_promises(): void
    {
        [$zone] = $this->createZoneWithSlot();
        $slot = $this->createHourlySlot($zone, '23:00');
        $client = $this->createClient();
        $address = $this->createAddressFor($client, $zone);
        $date = now()->addDay()->toDateString();

        $orderId = $this->order($client, $address, $slot, $date)->assertCreated()->json('data.id');

        Sanctum::actingAs($this->admin(), ['*']);
        $this->patchJson("/api/v1/admin/creneaux/zones/{$zone->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('still_booked', 1);

        // Plus rien de neuf...
        $this->order($client, $address, $slot, $date)->assertStatus(422);

        // ...mais ce qui est promis sera livre. Suspendre une zone n'est pas
        // annuler sa journee : le preparateur ira, le livreur livrera.
        $this->assertSame('pending', Order::find($orderId)->status->value);
    }

    public function test_the_screen_shows_the_load_that_makes_capacity_decidable(): void
    {
        [$zone] = $this->createZoneWithSlot();
        $slot = $this->createHourlySlot($zone, '23:00');
        $date = now()->addDay()->toDateString();

        $client = $this->createClient();
        $this->order($client, $this->createAddressFor($client, $zone), $slot, $date)->assertCreated();

        Sanctum::actingAs($this->admin(), ['*']);
        $payload = $this->getJson('/api/v1/admin/creneaux')->assertOk()->json('data');

        $found = collect($payload['zones'])
            ->firstWhere('id', $zone->id)['slots'];
        $row = collect($found)->firstWhere('id', $slot->id);

        // Une capacite se regle en regardant ce qui se remplit, pas dans
        // l'abstrait : le chiffre seul ne dirait pas s'il est bien regle.
        $booked = collect($row['load'])->firstWhere('date', $date)['booked'];
        $this->assertSame(1, $booked);
    }

    public function test_the_screen_is_closed_to_everyone_but_an_admin(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market] = $this->createMarketFor($zone);

        Sanctum::actingAs($this->createPreparateurFor($market), ['*']);
        $this->getJson('/api/v1/admin/creneaux')->assertForbidden();

        Sanctum::actingAs($this->createClient(), ['*']);
        $this->postJson('/api/v1/admin/creneaux/fermetures', [
            'closed_on' => now()->addDay()->toDateString(),
            'reason' => 'Tentative.',
        ])->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Gestion des clients.
 *
 * Deux sujets, et le second compte plus que le premier.
 *
 * La FICHE : ce qu'on ouvre quand quelqu'un appelle pour reclamer.
 *
 * La SUSPENSION : `users.status` etait ecrit a l'inscription et lu nulle part.
 * Suspendre un compte ne faisait rien du tout — meme classe de bug que le
 * produit « retire de la vente » qui restait en boutique.
 */
class ClientManagementTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    /** Une commande passee, a la date de livraison voulue. */
    private function orderFor($client, string $deliveryDate, array $overrides = []): Order
    {
        [$zone, $slot] = $this->createZoneWithSlot();
        $address = $this->createAddressFor($client, $zone);
        $product = $this->createProduct(['base_price' => 1000]);

        Sanctum::actingAs($client, ['*']);
        $id = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [['type' => 'product', 'product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()->json('data.id');

        $order = Order::find($id);
        // La date passe APRES la creation : le controleur refuse un creneau
        // deja ecoule, et c'est bien ce qu'on veut fabriquer ici.
        $order->update(array_merge(['delivery_date' => $deliveryDate], $overrides));

        return $order->fresh();
    }

    // ------------------------------------------------------- la suspension

    public function test_a_suspended_client_can_no_longer_log_in(): void
    {
        $client = $this->createClient(['email' => 'awa@example.test', 'password' => 'Password123!']);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $this->patchJson("/api/v1/admin/clients/{$client->id}/status", [
            'suspended' => true,
            'reason' => 'Trois commandes jamais payées',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'awa@example.test',
            'password' => 'Password123!',
        ])->assertStatus(403)->assertJsonPath('error', 'account_suspended');
    }

    public function test_an_existing_token_stops_working_immediately(): void
    {
        $client = $this->createClient();

        Sanctum::actingAs($client, ['*']);
        $this->getJson('/api/v1/orders')->assertOk();

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $this->patchJson("/api/v1/admin/clients/{$client->id}/status", [
            'suspended' => true,
            'reason' => 'Impayés',
        ])->assertOk();

        Sanctum::actingAs($client->fresh(), ['*']);

        // Bloquer la seule connexion ne suffirait pas : les jetons vivent
        // trente jours, et le compte continuerait de commander pendant
        // tout ce temps.
        $this->getJson('/api/v1/orders')->assertStatus(403)->assertJsonPath('error', 'account_suspended');
    }

    public function test_suspending_requires_a_reason(): void
    {
        $client = $this->createClient();
        Sanctum::actingAs($this->createStaff('admin'), ['*']);

        $this->patchJson("/api/v1/admin/clients/{$client->id}/status", ['suspended' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_a_client_can_be_reinstated(): void
    {
        $client = $this->createClient();
        Sanctum::actingAs($this->createStaff('admin'), ['*']);

        $this->patchJson("/api/v1/admin/clients/{$client->id}/status", ['suspended' => true, 'reason' => 'x']);
        $this->patchJson("/api/v1/admin/clients/{$client->id}/status", ['suspended' => false])->assertOk();

        $this->assertSame('active', $client->fresh()->status);

        Sanctum::actingAs($client->fresh(), ['*']);
        $this->getJson('/api/v1/orders')->assertOk();
    }

    public function test_a_suspended_preparateur_is_locked_out_too(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market] = $this->createMarketFor($zone);
        $preparateur = $this->createPreparateurFor($market);

        Sanctum::actingAs($preparateur, ['*']);
        $this->getJson('/api/v1/preparateur/orders')->assertOk();

        // Le verrou est sur l'authentification, pas sur le role client : un
        // livreur qui rend son tablier ne garde pas ses acces.
        $preparateur->update(['status' => 'suspended']);
        Sanctum::actingAs($preparateur->fresh(), ['*']);

        $this->getJson('/api/v1/preparateur/orders')->assertStatus(403);
    }

    // ---------------------------------------------------------- les impayés

    public function test_an_unpaid_order_still_in_the_future_is_not_a_problem(): void
    {
        $client = $this->createClient();
        $this->orderFor($client, now()->addDays(2)->toDateString());

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $row = $this->getJson('/api/v1/admin/clients')->assertOk()->json('data.0');

        // Le client a encore le temps de payer : ce n'est pas un impayé.
        $this->assertSame(0, $row['unpaid']);
    }

    public function test_an_unpaid_order_past_its_slot_is_flagged(): void
    {
        $client = $this->createClient();
        $this->orderFor($client, now()->subDays(2)->toDateString());

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $response = $this->getJson('/api/v1/admin/clients')->assertOk();

        // Elle a occupé un créneau que personne d'autre n'a pu prendre.
        $this->assertSame(1, $response->json('data.0.unpaid'));
        $this->assertSame(1, $response->json('meta.with_unpaid'));
    }

    public function test_a_cancelled_order_is_not_counted_as_unpaid(): void
    {
        $client = $this->createClient();
        $this->orderFor($client, now()->subDays(2)->toDateString(), ['status' => OrderStatus::CANCELLED]);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);

        // Une commande annulée a libéré sa place : elle ne reproche rien.
        $this->assertSame(0, $this->getJson('/api/v1/admin/clients')->json('data.0.unpaid'));
    }

    public function test_the_unpaid_filter_isolates_them(): void
    {
        $bon = $this->createClient(['name' => 'Bon payeur']);
        $this->orderFor($bon, now()->subDay()->toDateString())->update(['payment_status' => 'completed']);

        $mauvais = $this->createClient(['name' => 'Mauvais payeur']);
        $this->orderFor($mauvais, now()->subDays(3)->toDateString());

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $found = $this->getJson('/api/v1/admin/clients?filter=unpaid')->assertOk()->json('data');

        $this->assertSame(['Mauvais payeur'], array_column($found, 'name'));
    }

    // -------------------------------------------------------------- la fiche

    public function test_the_profile_counts_only_what_was_really_paid(): void
    {
        $client = $this->createClient(['name' => 'Awa Hounkpatin']);
        $this->orderFor($client, now()->subDays(3)->toDateString())->update(['payment_status' => 'completed']);
        $this->orderFor($client, now()->subDays(2)->toDateString());

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $data = $this->getJson("/api/v1/admin/clients/{$client->id}")->assertOk()->json('data');

        $this->assertSame('Awa Hounkpatin', $data['client']['name']);
        $this->assertSame(2, $data['stats']['orders']);

        // 2 × 1000 + 800 de livraison + 200 de taxe = 3000, une seule fois :
        // une commande impayée n'est pas du chiffre d'affaires.
        $this->assertSame(3000, $data['stats']['spent']);
        $this->assertSame(3000, $data['stats']['average_basket']);
        $this->assertSame(1, $data['stats']['unpaid']);
        $this->assertSame(3000, $data['stats']['unpaid_amount']);
    }

    public function test_the_profile_lists_the_orders_and_the_addresses(): void
    {
        $client = $this->createClient();
        $order = $this->orderFor($client, now()->subDay()->toDateString());

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $data = $this->getJson("/api/v1/admin/clients/{$client->id}")->assertOk()->json('data');

        $this->assertSame($order->order_number, $data['orders'][0]['order_number']);
        $this->assertTrue($data['orders'][0]['unpaid']);
        $this->assertNotEmpty($data['addresses']);
    }

    public function test_only_clients_appear_in_the_client_list(): void
    {
        $this->createClient(['name' => 'Une cliente']);
        $this->createStaff('livreur', ['name' => 'Un livreur']);
        $this->createStaff('admin', ['name' => 'Un admin']);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $names = array_column($this->getJson('/api/v1/admin/clients')->json('data'), 'name');

        $this->assertSame(['Une cliente'], $names);
    }

    public function test_a_staff_profile_cannot_be_opened_through_this_route(): void
    {
        $livreur = $this->createStaff('livreur');

        Sanctum::actingAs($this->createStaff('admin'), ['*']);

        // Le personnel se gère ailleurs : cette route ne parle que des clients.
        $this->getJson("/api/v1/admin/clients/{$livreur->id}")->assertNotFound();
    }

    public function test_client_files_are_closed_to_everyone_but_the_admin(): void
    {
        $client = $this->createClient();

        foreach (['client', 'preparateur', 'livreur'] as $role) {
            Sanctum::actingAs($this->createStaff($role), ['*']);

            $this->getJson('/api/v1/admin/clients')->assertStatus(403);
            $this->getJson("/api/v1/admin/clients/{$client->id}")->assertStatus(403);
            $this->patchJson("/api/v1/admin/clients/{$client->id}/status", ['suspended' => true, 'reason' => 'x'])
                ->assertStatus(403);
        }
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class StaffCreationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    private function staffPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nouveau Livreur',
            'email' => 'livreur-staff@example.test',
            'phone' => '+22998000001',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'livreur',
        ], $overrides);
    }

    public function test_a_non_admin_cannot_create_a_staff_account(): void
    {
        $client = $this->createClient();
        Sanctum::actingAs($client, ['*']);

        $this->postJson('/api/v1/admin/staff', $this->staffPayload())
            ->assertStatus(403);
    }

    public function test_an_admin_can_create_a_preparateur_or_livreur_account(): void
    {
        $admin = $this->createStaff('admin');
        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/v1/admin/staff', $this->staffPayload())
            ->assertCreated()
            ->assertJsonPath('data.role', 'livreur');

        $this->assertDatabaseHas('users', [
            'email' => 'livreur-staff@example.test',
            'role' => 'livreur',
        ]);
    }

    public function test_an_admin_cannot_create_another_admin_account_via_this_endpoint(): void
    {
        $admin = $this->createStaff('admin');
        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/v1/admin/staff', $this->staffPayload(['role' => 'admin']))
            ->assertStatus(422);
    }

    public function test_a_preparateur_cannot_be_created_without_a_market(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->createStaff('admin'), ['*']);

        // Sans marché, le compte serait créé mort-né : le préparateur ouvre son
        // écran, ne voit aucune commande, et personne ne comprend pourquoi.
        $this->postJson('/api/v1/admin/staff', $this->staffPayload(['role' => 'preparateur']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('market_id');
    }

    public function test_a_preparateur_is_attached_to_his_market(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market] = $this->createMarketFor($zone);

        \Laravel\Sanctum\Sanctum::actingAs($this->createStaff('admin'), ['*']);

        $response = $this->postJson('/api/v1/admin/staff', $this->staffPayload([
            'role' => 'preparateur',
            'market_id' => $market->id,
        ]))->assertCreated();

        $this->assertSame($market->id, \App\Models\User::find($response->json('data.id'))->market_id);
    }

    public function test_an_admin_lists_the_markets_with_their_headcount(): void
    {
        [$zone] = $this->createZoneWithSlot();
        [$market] = $this->createMarketFor($zone, ['name' => 'Marché Saint-Michel']);
        $this->createPreparateurFor($market);

        // Le compteur ne doit compter que des preparateurs : `market_id` vit
        // sur `users`, un autre role rattache un jour fausserait le chiffre.
        $this->createStaff('client', ['market_id' => $market->id]);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);

        $this->getJson('/api/v1/admin/markets')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Marché Saint-Michel')
            ->assertJsonPath('data.0.preparateurs_count', 1);
    }

    public function test_a_non_admin_cannot_list_the_markets(): void
    {
        Sanctum::actingAs($this->createClient(), ['*']);

        $this->getJson('/api/v1/admin/markets')->assertStatus(403);
    }

    public function test_a_livreur_needs_no_market(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->createStaff('admin'), ['*']);

        // Le livreur suit les commandes prêtes, pas un lieu de retrait.
        $response = $this->postJson('/api/v1/admin/staff', $this->staffPayload(['role' => 'livreur']))
            ->assertCreated();

        $this->assertNull(\App\Models\User::find($response->json('data.id'))->market_id);
    }
}

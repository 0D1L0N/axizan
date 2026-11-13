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
}

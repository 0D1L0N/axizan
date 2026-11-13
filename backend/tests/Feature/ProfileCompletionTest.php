<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ProfileCompletionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_me_reports_a_missing_address(): void
    {
        $client = $this->createClient();
        Sanctum::actingAs($client, ['*']);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk();
        $this->assertFalse($response->json('profile.is_complete'));
        $this->assertSame(['address'], $response->json('profile.missing'));
        $this->assertFalse($response->json('profile.can_order'));
    }

    public function test_profile_is_complete_once_an_address_exists(): void
    {
        $client = $this->createClient();
        [$zone] = $this->createZoneWithSlot();
        $this->createAddressFor($client, $zone);

        Sanctum::actingAs($client, ['*']);

        $response = $this->getJson('/api/v1/auth/me');

        $this->assertTrue($response->json('profile.is_complete'));
        $this->assertTrue($response->json('profile.can_order'));
    }

    public function test_a_missing_phone_blocks_ordering(): void
    {
        $client = $this->createClient(['phone' => null]);
        [$zone] = $this->createZoneWithSlot();
        $this->createAddressFor($client, $zone);

        Sanctum::actingAs($client, ['*']);

        $response = $this->getJson('/api/v1/auth/me');

        $this->assertContains('phone', $response->json('profile.missing'));
        $this->assertFalse($response->json('profile.can_order'));
    }

    public function test_client_can_update_phone_and_email(): void
    {
        $client = $this->createClient();
        Sanctum::actingAs($client, ['*']);

        $response = $this->patchJson('/api/v1/auth/profile', [
            'name' => 'Ama Koffi',
            'phone' => '+229 97 12 34 56',
            'email' => 'ama@example.bj',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $client->id,
            'name' => 'Ama Koffi',
            'email' => 'ama@example.bj',
        ]);
    }

    public function test_reposting_ones_own_phone_is_not_a_conflict(): void
    {
        $client = $this->createClient(['phone' => '+22997000000']);
        Sanctum::actingAs($client, ['*']);

        // Sans Rule::unique()->ignore(), renvoyer le formulaire inchange
        // echouerait sur son propre numero.
        $this->patchJson('/api/v1/auth/profile', [
            'phone' => '+22997000000',
            'email' => $client->email,
        ])->assertOk();
    }

    public function test_a_phone_already_taken_is_rejected(): void
    {
        $other = $this->createClient(['phone' => '+22996000000']);
        $client = $this->createClient();

        Sanctum::actingAs($client, ['*']);

        $this->patchJson('/api/v1/auth/profile', ['phone' => $other->phone])
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'Ce numéro est déjà utilisé par un autre compte.');
    }
}

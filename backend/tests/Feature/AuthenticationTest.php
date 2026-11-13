<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_register_and_log_in(): void
    {
        $credentials = [
            'name' => 'Awa Kossi',
            'email' => 'awa@example.test',
            'phone' => '+22997000001',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'client',
        ];

        $this->postJson('/api/v1/auth/register', $credentials)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id', 'role'], 'token']);

        $this->assertDatabaseHas('users', [
            'email' => 'awa@example.test',
            'phone' => '+22997000001',
            'role' => 'client',
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'awa@example.test',
            'password' => 'Password123!',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id'], 'token']);

        // Régression : la route protégée /auth/me doit résoudre le guard `sanctum`.
        $token = $loginResponse->json('token');
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'awa@example.test');
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_always_creates_a_client_even_if_another_role_is_requested(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Faux Admin',
            'email' => 'faux-admin@example.test',
            'phone' => '+22997000010',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'admin',
        ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'client');

        $this->assertDatabaseHas('users', [
            'email' => 'faux-admin@example.test',
            'role' => 'client',
        ]);
    }
}

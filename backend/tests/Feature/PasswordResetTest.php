<?php

namespace Tests\Feature;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_a_reset_link_is_sent_to_a_known_address(): void
    {
        Notification::fake();
        $client = $this->createClient();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $client->email])
            ->assertOk();

        Notification::assertSentTo($client, ResetPasswordNotification::class);
    }

    public function test_an_unknown_address_gets_the_same_answer(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'inconnu@nulle-part.bj',
        ]);

        // Repondre differemment transformerait ce point d'entree en annuaire
        // des comptes existants.
        $response->assertOk();
        $this->assertTrue($response->json('success'));
        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $client = $this->createClient();
        $token = Password::createToken($client);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $client->email,
            'password' => 'NouveauMdp2026!',
            'password_confirmation' => 'NouveauMdp2026!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NouveauMdp2026!', $client->fresh()->password));
    }

    public function test_a_reset_revokes_existing_tokens(): void
    {
        $client = $this->createClient();
        $client->createToken('appareil')->plainTextToken;
        $this->assertSame(1, $client->tokens()->count());

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => Password::createToken($client),
            'email' => $client->email,
            'password' => 'NouveauMdp2026!',
            'password_confirmation' => 'NouveauMdp2026!',
        ])->assertOk();

        // Changer le mot de passe sans revoquer les jetons ne protege de rien.
        $this->assertSame(0, $client->tokens()->count());
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $client = $this->createClient();

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'jeton-invente',
            'email' => $client->email,
            'password' => 'NouveauMdp2026!',
            'password_confirmation' => 'NouveauMdp2026!',
        ])->assertStatus(422)->assertJsonPath('error', 'invalid_token');
    }

    public function test_change_password_requires_the_current_one(): void
    {
        $client = $this->createClient(['password' => Hash::make('password123')]);
        Sanctum::actingAs($client, ['*']);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'mauvais',
            'password' => 'NouveauMdp2026!',
            'password_confirmation' => 'NouveauMdp2026!',
        ])->assertStatus(422)->assertJsonPath('errors.current_password.0', 'Mot de passe actuel incorrect.');
    }

    public function test_change_password_succeeds_with_the_current_one(): void
    {
        $client = $this->createClient(['password' => Hash::make('password123')]);
        Sanctum::actingAs($client, ['*']);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'password123',
            'password' => 'NouveauMdp2026!',
            'password_confirmation' => 'NouveauMdp2026!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NouveauMdp2026!', $client->fresh()->password));
    }
}

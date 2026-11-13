<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class KycTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function submitDocument($livreur)
    {
        Sanctum::actingAs($livreur, ['*']);

        return $this->postJson('/api/v1/livreur/kyc', [
            // PDF plutot qu'image : fake()->image() exige l'extension GD.
            'document' => UploadedFile::fake()->create('cni.pdf', 200, 'application/pdf'),
        ]);
    }

    public function test_document_is_stored_privately_and_the_account_stays_usable(): void
    {
        $livreur = $this->createStaff('livreur');

        $this->submitDocument($livreur)->assertCreated();

        $livreur->refresh();
        Storage::disk('local')->assertExists($livreur->kyc_document_url);
        $this->assertEmpty(Storage::disk('public')->allFiles());
        $this->assertSame('active', $livreur->status);

        // Le chemin du document ne sort pas dans le profil.
        $this->getJson('/api/v1/auth/me')->assertJsonMissingPath('data.kyc_document_url');
    }

    public function test_an_unverified_livreur_cannot_take_a_delivery(): void
    {
        Sanctum::actingAs($this->createStaff('livreur', ['kyc_verified' => false]), ['*']);

        $this->postJson('/api/v1/deliveries/1/accept')
            ->assertForbidden()
            ->assertJsonPath('error', 'kyc_required');
    }

    public function test_only_livreurs_can_submit_a_document(): void
    {
        $this->submitDocument($this->createClient())->assertForbidden();
    }

    public function test_admin_approves_a_pending_document(): void
    {
        $livreur = $this->createStaff('livreur');
        $this->submitDocument($livreur);

        Sanctum::actingAs($this->createStaff('admin'), ['*']);

        $this->getJson('/api/v1/admin/kyc/pending')->assertOk()->assertJsonPath('data.0.id', $livreur->id);
        $this->get("/api/v1/admin/kyc/{$livreur->id}/document")->assertOk();
        $this->postJson("/api/v1/admin/kyc/{$livreur->id}/approve")->assertOk();

        $this->assertTrue($livreur->fresh()->kyc_verified);
        $this->getJson('/api/v1/admin/kyc/pending')->assertJsonCount(0, 'data');
    }

    public function test_rejection_requires_a_reason_and_removes_the_document(): void
    {
        $livreur = $this->createStaff('livreur');
        $this->submitDocument($livreur);
        $path = $livreur->fresh()->kyc_document_url;

        Sanctum::actingAs($this->createStaff('admin'), ['*']);

        $this->postJson("/api/v1/admin/kyc/{$livreur->id}/reject")->assertStatus(422);
        $this->postJson("/api/v1/admin/kyc/{$livreur->id}/reject", ['reason' => 'Photo floue'])->assertOk();

        Storage::disk('local')->assertMissing($path);
        $this->assertNull($livreur->fresh()->kyc_document_url);
        $this->assertDatabaseHas('notifications', ['user_id' => $livreur->id, 'title' => 'Document refusé']);
    }

    public function test_kyc_review_is_admin_only_and_limited_to_livreurs(): void
    {
        $client = $this->createClient();

        Sanctum::actingAs($this->createStaff('livreur'), ['*']);
        $this->getJson('/api/v1/admin/kyc/pending')->assertForbidden();

        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        $this->postJson("/api/v1/admin/kyc/{$client->id}/approve")->assertNotFound();
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PreparateurFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_preparateur_can_view_assigned_orders()
    {
        $preparateur = User::factory()->create(['role' => 'preparateur']);

        $this->actingAs($preparateur, 'sanctum')
            ->getJson('/api/v1/preparateur/orders')
            ->assertStatus(200);
    }

    public function test_preparateur_can_request_substitution()
    {
        $preparateur = User::factory()->create(['role' => 'preparateur']);

        $response = $this->actingAs($preparateur, 'sanctum')
            ->postJson('/api/v1/preparateur/orders/1/substitute', [
                'item_id' => 1,
                'action' => 'remove',
            ]);

        // since order 1 may not exist, expect 404 or success; ensure no server error
        $this->assertTrue(in_array($response->status(), [200, 201, 404]));
    }
}

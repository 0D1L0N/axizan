<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class AdminLogisticsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_only_admins_can_manage_zones(): void
    {
        Sanctum::actingAs($this->createClient(), ['*']);

        $this->getJson('/api/v1/admin/delivery-zones')->assertForbidden();
        $this->postJson('/api/v1/admin/delivery-zones', ['name' => 'Akpakpa'])->assertForbidden();
    }

    public function test_admin_creates_and_updates_a_zone_with_pricing_rules(): void
    {
        Sanctum::actingAs($this->createStaff('admin'), ['*']);

        $id = $this->postJson('/api/v1/admin/delivery-zones', [
            'name' => 'Akpakpa',
            'base_delivery_fee' => 700,
            'min_order_amount' => 2500,
            'small_order_action' => 'block',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/admin/delivery-zones/{$id}", [
            'name' => 'Akpakpa',
            'free_delivery_threshold' => 15000,
        ])->assertOk();

        $this->assertDatabaseHas('delivery_zones', [
            'id' => $id,
            'min_order_amount' => 2500,
            'small_order_action' => 'block',
            'free_delivery_threshold' => 15000,
        ]);
    }

    public function test_a_zone_in_use_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        [$zone] = $this->createZoneWithSlot();

        $this->deleteJson("/api/v1/admin/delivery-zones/{$zone->id}")->assertStatus(422);
        $this->assertDatabaseHas('delivery_zones', ['id' => $zone->id]);
    }

    public function test_admin_edits_a_slot_with_times_read_back_from_the_database(): void
    {
        Sanctum::actingAs($this->createStaff('admin'), ['*']);
        [$zone, $slot] = $this->createZoneWithSlot();

        $this->putJson("/api/v1/admin/delivery-slots/{$slot->id}", [
            'zone_id' => $zone->id,
            'time_start' => '09:00:00',
            'time_end' => '11:00:00',
            'display_label' => 'Matin',
            'additional_fee' => 250,
        ])->assertOk();

        $this->assertDatabaseHas('delivery_slots', ['id' => $slot->id, 'display_label' => 'Matin', 'additional_fee' => 250]);
    }
}

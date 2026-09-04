<?php

namespace Tests\Feature;

use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    private function notifyUser($user, bool $read = false): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => 'order_accepted',
            'title' => 'Commande acceptée',
            'message' => 'Un préparateur a pris votre commande.',
            'data' => ['order_id' => 42],
            'is_read' => $read,
        ]);
    }

    public function test_a_client_only_sees_their_own_notifications(): void
    {
        $client = $this->createClient();
        $other = $this->createClient();
        $this->notifyUser($client);
        $this->notifyUser($other);

        Sanctum::actingAs($client, ['*']);

        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(1, $response->json('meta.unread_count'));
    }

    public function test_marking_someone_elses_notification_returns_404(): void
    {
        $client = $this->createClient();
        $other = $this->createClient();
        $foreign = $this->notifyUser($other);

        Sanctum::actingAs($client, ['*']);

        // 404 plutot que 403 : un 403 confirmerait que cet identifiant existe.
        $this->patchJson("/api/v1/notifications/{$foreign->id}/read")->assertNotFound();
        $this->assertFalse($foreign->fresh()->is_read);
    }

    public function test_unread_filter_and_mark_all(): void
    {
        $client = $this->createClient();
        $this->notifyUser($client);
        $this->notifyUser($client);
        $this->notifyUser($client, read: true);

        Sanctum::actingAs($client, ['*']);

        $this->assertSame(2, $this->getJson('/api/v1/notifications/unread-count')->json('data.unread_count'));
        $this->assertCount(2, $this->getJson('/api/v1/notifications?unread=1')->json('data'));

        $this->postJson('/api/v1/notifications/read-all')->assertOk();

        $this->assertSame(0, $this->getJson('/api/v1/notifications/unread-count')->json('data.unread_count'));
    }

    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }
}

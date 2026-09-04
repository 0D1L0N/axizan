<?php

namespace Tests\Feature;

use App\Contracts\SmsGateway;
use App\Enums\NotificationType;
use App\Notifications\OrderUpdateNotification;
use App\Services\NotificationService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Diffusion multicanale : in-app toujours, mail et SMS selon le type et les
 * preferences du client.
 */
class NotificationChannelsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    /** Passerelle d'essai : retient ce qui aurait ete envoye. */
    private function fakeGateway(): object
    {
        $gateway = new class implements SmsGateway
        {
            public array $sent = [];

            public function send(string $to, string $message): bool
            {
                $this->sent[] = ['to' => $to, 'message' => $message];

                return true;
            }

            public function name(): string
            {
                return 'fake';
            }
        };

        app()->instance(get_class($gateway), $gateway);
        config(['sms.driver' => 'fake', 'sms.gateways.fake' => get_class($gateway)]);

        return $gateway;
    }

    public function test_an_order_creation_reaches_in_app_mail_and_sms(): void
    {
        NotificationFacade::fake();
        $gateway = $this->fakeGateway();

        $client = $this->createClient(['phone' => '+22997001122']);
        app(NotificationService::class)->notify(
            $client,
            NotificationType::ORDER_CREATED,
            'Commande reçue',
            'Votre commande a été créée.'
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'is_sent_in_app' => true,
            'is_sent_email' => true,
            'is_sent_sms' => true,
        ]);

        NotificationFacade::assertSentTo($client, OrderUpdateNotification::class);
        $this->assertCount(1, $gateway->sent);
    }

    public function test_a_tracking_step_stays_in_app_only(): void
    {
        NotificationFacade::fake();
        $gateway = $this->fakeGateway();

        $client = $this->createClient(['phone' => '+22997001122']);
        app(NotificationService::class)->notify(
            $client,
            NotificationType::ORDER_PREPARING,
            'En préparation',
            'Vos articles sont achetés au marché.'
        );

        // Un SMS a chaque changement de statut ferait desinstaller
        // l'application — et couterait un segment a chaque fois.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'is_sent_in_app' => true,
            'is_sent_email' => false,
            'is_sent_sms' => false,
        ]);
        NotificationFacade::assertNothingSent();
        $this->assertCount(0, $gateway->sent);
    }

    public function test_a_client_without_a_phone_gets_no_sms(): void
    {
        NotificationFacade::fake();
        $gateway = $this->fakeGateway();

        $client = $this->createClient(['phone' => null]);
        app(NotificationService::class)->notify(
            $client,
            NotificationType::ORDER_CREATED,
            'Commande reçue',
            'Votre commande a été créée.'
        );

        $this->assertCount(0, $gateway->sent);
        $this->assertDatabaseHas('notifications', ['user_id' => $client->id, 'is_sent_sms' => false]);
    }

    public function test_preferences_silence_non_transactional_channels(): void
    {
        NotificationFacade::fake();
        $gateway = $this->fakeGateway();

        $client = $this->createClient([
            'phone' => '+22997001122',
            'notification_preferences' => ['sms' => false, 'email' => false],
        ]);

        app(NotificationService::class)->notify(
            $client,
            NotificationType::ORDER_DELIVERED,
            'Commande livrée',
            'Votre commande a été remise.'
        );

        $this->assertCount(0, $gateway->sent);
        NotificationFacade::assertNothingSent();
    }

    public function test_transactional_notifications_ignore_preferences(): void
    {
        NotificationFacade::fake();
        $gateway = $this->fakeGateway();

        $client = $this->createClient([
            'phone' => '+22997001122',
            'notification_preferences' => ['sms' => false, 'email' => false],
        ]);

        app(NotificationService::class)->notify(
            $client,
            NotificationType::PAYMENT_CONFIRMED,
            'Paiement validé',
            'Nous partons au marché.'
        );

        // Couper ces messages ferait perdre des commandes deja payees.
        $this->assertCount(1, $gateway->sent);
        NotificationFacade::assertSentTo($client, OrderUpdateNotification::class);
    }

    public function test_beninese_numbers_are_normalised(): void
    {
        $sms = app(SmsService::class);

        $this->assertSame('+22997001122', $sms->normalize('97 00 11 22'));
        $this->assertSame('+22997001122', $sms->normalize('097001122'));
        $this->assertSame('+22997001122', $sms->normalize('+229 97 00 11 22'));
        $this->assertSame('+22997001122', $sms->normalize('(229) 97-00-11-22'));
        $this->assertNull($sms->normalize(null));
        $this->assertNull($sms->normalize('   '));
    }

    public function test_an_overlong_message_is_not_sent(): void
    {
        $gateway = $this->fakeGateway();
        config(['sms.max_length' => 20]);

        // Tronquer couperait au milieu d'un montant ou d'un numero de
        // commande : on prefere ne pas envoyer.
        $this->assertFalse(app(SmsService::class)->send('+22997001122', str_repeat('a', 50)));
        $this->assertCount(0, $gateway->sent);
    }

    public function test_a_failing_gateway_never_breaks_the_order(): void
    {
        NotificationFacade::fake();

        $broken = new class implements SmsGateway
        {
            public function send(string $to, string $message): bool
            {
                throw new \RuntimeException('Agrégateur injoignable');
            }

            public function name(): string
            {
                return 'broken';
            }
        };

        app()->instance(get_class($broken), $broken);
        config(['sms.driver' => 'broken', 'sms.gateways.broken' => get_class($broken)]);

        $client = $this->createClient(['phone' => '+22997001122']);

        $notification = app(NotificationService::class)->notify(
            $client,
            NotificationType::ORDER_CREATED,
            'Commande reçue',
            'Votre commande a été créée.'
        );

        // La notification existe malgre la panne : la commande passe.
        $this->assertNotNull($notification->id);
        $this->assertFalse((bool) $notification->is_sent_sms);
    }
}

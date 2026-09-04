<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\FedapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Paiement en ligne via FedaPay.
 *
 * Le coeur du sujet : **le navigateur ne prouve rien**. Le module de paiement
 * renvoie un identifiant de transaction ; c'est le serveur qui demande a
 * FedaPay si elle a ete payee et si le montant correspond. Ces tests
 * verrouillent ce contrat — c'est la difference entre encaisser et se faire
 * voler des courses.
 */
class FedapayPaymentTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.fedapay.enabled' => true,
            'payments.fedapay.public_key' => 'pk_sandbox_test',
            'payments.fedapay.secret_key' => 'sk_sandbox_test',
            'payments.fedapay.webhook_secret' => 'wh_sandbox_test',
            'payments.fedapay.sandbox' => true,
        ]);
    }

    /** Double du service : simule la reponse de FedaPay sans appel reseau. */
    private function fakeVerification(bool $ok, ?float $amount): void
    {
        $this->app->bind(FedapayService::class, fn ($app) => new class(
            $app->make(\App\Services\PaymentService::class),
            $ok,
            $amount
        ) extends FedapayService {
            public function __construct(
                \App\Services\PaymentService $payments,
                private bool $paid,
                private ?float $sum
            ) {
                parent::__construct($payments);
            }

            public function verify(string $transactionId): array
            {
                return [
                    'ok' => $this->paid,
                    'status' => $this->paid ? 'approved' : 'declined',
                    'amount' => $this->sum,
                    'raw' => ['amount' => $this->sum, 'mode' => 'mtn'],
                ];
            }
        });
    }

    private function makeOrder(): Order
    {
        [$zone] = $this->createZoneWithSlot();
        $client = $this->createClient();
        $slot = $this->createHourlySlot($zone, '23:00');
        $address = $this->createAddressFor($client, $zone);

        Sanctum::actingAs($client, ['*']);

        $id = $this->postJson('/api/v1/orders', [
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [[
                'type' => 'product',
                'product_id' => $this->createProduct(['base_price' => 5000])->id,
                'quantity' => 2,
            ]],
        ])->assertCreated()->json('data.id');

        return Order::with('payment', 'client')->find($id);
    }

    public function test_a_paid_transaction_settles_the_order(): void
    {
        $order = $this->makeOrder();
        $this->fakeVerification(ok: true, amount: (float) $order->total);

        Sanctum::actingAs($order->client, ['*']);
        $this->postJson("/api/v1/orders/{$order->id}/confirm-payment", [
            'transaction_id' => '123456',
        ])->assertOk();

        $order->refresh();
        $this->assertSame('completed', $order->payment_status);
        $this->assertSame(PaymentStatus::COMPLETED, $order->payment->status);
        $this->assertSame('fedapay', $order->payment->payment_method);
        // La commande peut maintenant partir au marche.
        $this->assertTrue($order->canBeAccepted());
    }

    public function test_an_unpaid_transaction_settles_nothing(): void
    {
        $order = $this->makeOrder();
        $this->fakeVerification(ok: false, amount: (float) $order->total);

        Sanctum::actingAs($order->client, ['*']);
        $this->postJson("/api/v1/orders/{$order->id}/confirm-payment", [
            'transaction_id' => '999999',
        ])->assertStatus(422)->assertJsonPath('error', 'transaction_not_paid');

        // Un identifiant invente ne debloque rien.
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_an_insufficient_amount_is_refused(): void
    {
        $order = $this->makeOrder();
        // 100 F regles pour une commande a plusieurs milliers.
        $this->fakeVerification(ok: true, amount: 100.0);

        Sanctum::actingAs($order->client, ['*']);
        $this->postJson("/api/v1/orders/{$order->id}/confirm-payment", [
            'transaction_id' => '111111',
        ])->assertStatus(422)->assertJsonPath('error', 'amount_mismatch');

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertFalse($order->fresh()->canBeAccepted());
    }

    public function test_settling_twice_is_harmless(): void
    {
        $order = $this->makeOrder();
        $this->fakeVerification(ok: true, amount: (float) $order->total);

        Sanctum::actingAs($order->client, ['*']);
        $payload = ['transaction_id' => '222222'];

        // Le retour du navigateur ET le webhook peuvent arriver tous les deux.
        $this->postJson("/api/v1/orders/{$order->id}/confirm-payment", $payload)->assertOk();
        $this->postJson("/api/v1/orders/{$order->id}/confirm-payment", $payload)->assertOk();

        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
        $this->assertSame('completed', $order->fresh()->payment_status);
    }

    public function test_someone_else_cannot_settle_your_order(): void
    {
        $order = $this->makeOrder();
        $this->fakeVerification(ok: true, amount: (float) $order->total);

        Sanctum::actingAs($this->createClient(), ['*']);
        $this->postJson("/api/v1/orders/{$order->id}/confirm-payment", [
            'transaction_id' => '333333',
        ])->assertNotFound();
    }

    public function test_the_endpoint_answers_503_when_fedapay_is_off(): void
    {
        config(['payments.fedapay.enabled' => false]);
        $order = $this->makeOrder();

        Sanctum::actingAs($order->client, ['*']);
        $this->postJson("/api/v1/orders/{$order->id}/confirm-payment", [
            'transaction_id' => '444444',
        ])->assertStatus(503)->assertJsonPath('error', 'fedapay_disabled');
    }

    // ---------------------------------------------------------------- webhook

    /** Signature FedaPay : `t=<horodatage>,s=<hmac sha256 de "t.payload">`. */
    private function signature(string $payload, string $secret): string
    {
        $timestamp = time();
        $hash = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return "t={$timestamp},s={$hash}";
    }

    public function test_a_webhook_without_a_valid_signature_is_rejected(): void
    {
        $order = $this->makeOrder();
        $this->fakeVerification(ok: true, amount: (float) $order->total);

        $payload = json_encode([
            'name' => 'transaction.approved',
            'entity' => ['id' => 555, 'custom_metadata' => ['order_number' => $order->order_number]],
        ]);

        $this->call(
            'POST',
            '/api/v1/webhooks/fedapay',
            [],
            [],
            [],
            ['HTTP_X_FEDAPAY_SIGNATURE' => 't=1,s=faux', 'CONTENT_TYPE' => 'application/json'],
            $payload
        )->assertStatus(401);

        // Sans cette verification, n'importe qui reglerait n'importe quelle
        // commande en appelant une route publique.
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_a_webhook_without_any_signature_is_rejected(): void
    {
        $order = $this->makeOrder();

        $this->call(
            'POST',
            '/api/v1/webhooks/fedapay',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'transaction.approved', 'entity' => ['id' => 1]])
        )->assertStatus(401);

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_a_signed_approval_settles_the_order(): void
    {
        $order = $this->makeOrder();
        $this->fakeVerification(ok: true, amount: (float) $order->total);

        $payload = json_encode([
            'name' => 'transaction.approved',
            'entity' => ['id' => 777, 'custom_metadata' => ['order_number' => $order->order_number]],
        ]);

        $this->call(
            'POST',
            '/api/v1/webhooks/fedapay',
            [],
            [],
            [],
            [
                'HTTP_X_FEDAPAY_SIGNATURE' => $this->signature($payload, 'wh_sandbox_test'),
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        )->assertOk();

        // C'est le seul chemin qui marche si le client ferme son telephone.
        $this->assertSame('completed', $order->fresh()->payment_status);
    }

    public function test_a_non_approval_event_changes_nothing(): void
    {
        $order = $this->makeOrder();
        $this->fakeVerification(ok: true, amount: (float) $order->total);

        $payload = json_encode([
            'name' => 'transaction.canceled',
            'entity' => ['id' => 888, 'custom_metadata' => ['order_number' => $order->order_number]],
        ]);

        $this->call(
            'POST',
            '/api/v1/webhooks/fedapay',
            [],
            [],
            [],
            [
                'HTTP_X_FEDAPAY_SIGNATURE' => $this->signature($payload, 'wh_sandbox_test'),
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        )->assertOk();

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_payment_methods_announce_the_online_flow(): void
    {
        $meta = $this->getJson('/api/v1/payment-methods')->assertOk()->json('meta');

        $this->assertSame('online', $meta['flow']);
        $this->assertSame('pk_sandbox_test', $meta['fedapay']['public_key']);
        // La cle secrete et le secret du webhook ne sortent JAMAIS de l'API.
        $this->assertArrayNotHasKey('secret_key', $meta['fedapay']);
        $this->assertArrayNotHasKey('webhook_secret', $meta['fedapay']);
    }

    public function test_the_declarative_flow_is_announced_when_fedapay_is_off(): void
    {
        config(['payments.fedapay.enabled' => false]);

        $meta = $this->getJson('/api/v1/payment-methods')->assertOk()->json('meta');

        // Repli sans API : le client envoie l'argent et un admin verifie.
        $this->assertSame('declarative', $meta['flow']);
        $this->assertNull($meta['fedapay']);
    }
}

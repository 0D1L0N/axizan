<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Paiement declaratif, en attendant les API operateurs.
 *
 * Le point essentiel : ce que le client declare n'est PAS une preuve. Seule la
 * verification d'un administrateur valide un paiement.
 */
class PaymentDeclarationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    private function placeOrder($client, ?string $provider = null): Order
    {
        [$zone] = $this->createZoneWithSlot();
        $slot = $this->createHourlySlot($zone, '23:00');
        $address = $this->createAddressFor($client, $zone);
        $product = $this->createProduct();

        Sanctum::actingAs($client, ['*']);

        $response = $this->postJson('/api/v1/orders', array_filter([
            'delivery_address_id' => $address->id,
            'delivery_slot_id' => $slot->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'payment_provider' => $provider,
            'items' => [['type' => 'product', 'product_id' => $product->id, 'quantity' => 2]],
        ]))->assertCreated();

        return Order::find($response->json('data.id'));
    }

    public function test_payment_methods_are_public(): void
    {
        // Le regime est impose ICI, pas hérité du .env de la machine : sinon
        // ce test dit seulement si le developpeur a des cles FedaPay, et il
        // casse le jour ou quelqu'un en configure.
        config(['payments.fedapay.enabled' => false]);

        $response = $this->getJson('/api/v1/payment-methods')->assertOk();

        $this->assertNotEmpty($response->json('data'));
        $this->assertSame('declarative', $response->json('meta.flow'));
    }

    public function test_a_closed_operator_hides_its_merchant_number(): void
    {
        config(['payments.methods' => [
            ['code' => 'celtiis', 'label' => 'Celtiis Cash', 'color' => '#E30613',
             'merchant_number' => '+229 97 00 00 00', 'merchant_name' => 'Axizán',
             'ussd' => '*800#', 'enabled' => false],
        ]]);

        $method = $this->getJson('/api/v1/payment-methods')->json('data.0');

        // Afficher le numero d'un service ferme ferait envoyer de l'argent sur
        // un compte que personne ne releve.
        $this->assertFalse($method['enabled']);
        $this->assertNull($method['merchant_number']);
    }

    public function test_the_chosen_operator_is_recorded_on_the_payment(): void
    {
        $client = $this->createClient();
        $order = $this->placeOrder($client, 'moov');

        $this->assertSame('moov', Payment::where('order_id', $order->id)->first()->payment_provider);
    }

    public function test_a_client_can_declare_a_reference(): void
    {
        $client = $this->createClient();
        $order = $this->placeOrder($client, 'mtn');

        $this->postJson("/api/v1/orders/{$order->id}/declare-payment", [
            'reference' => 'MP240903.1425.A12345',
            'payer_phone' => '+22997123456',
        ])->assertOk();

        $payment = Payment::where('order_id', $order->id)->first();

        $this->assertSame('MP240903.1425.A12345', $payment->payer_declared_reference);
        $this->assertNotNull($payment->payer_declared_at);
        $this->assertSame('+22997123456', $payment->phone_number);
    }

    public function test_declaring_does_not_validate_the_payment(): void
    {
        $client = $this->createClient();
        $order = $this->placeOrder($client, 'mtn');

        $this->postJson("/api/v1/orders/{$order->id}/declare-payment", [
            'reference' => 'MP-FAUSSE-REFERENCE',
        ])->assertOk();

        $payment = Payment::where('order_id', $order->id)->first();

        // Le coeur du dispositif : une affirmation du client ne debloque rien.
        $this->assertSame('pending', $payment->status->value);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertNull($payment->reference_number);
        $this->assertNull($payment->validated_by);
        // La commande ne peut toujours pas partir au marche.
        $this->assertFalse($order->fresh()->canBeAccepted());
    }

    public function test_the_declared_reference_survives_admin_validation(): void
    {
        $client = $this->createClient();
        $order = $this->placeOrder($client, 'mtn');

        $this->postJson("/api/v1/orders/{$order->id}/declare-payment", [
            'reference' => 'CLIENT-DIT-CECI',
        ])->assertOk();

        $admin = $this->createStaff('admin');
        Sanctum::actingAs($admin, ['*']);
        $this->postJson("/api/v1/admin/orders/{$order->id}/validate-payment", [
            'reference_number' => 'CONSTATE-SUR-LE-COMPTE',
        ])->assertOk();

        $payment = Payment::where('order_id', $order->id)->first();

        // Les deux colonnes coexistent : en cas de litige, on sait ce que le
        // client a affirme ET ce que nous avons constate.
        $this->assertSame('CLIENT-DIT-CECI', $payment->payer_declared_reference);
        $this->assertSame('CONSTATE-SUR-LE-COMPTE', $payment->reference_number);
        $this->assertSame($admin->id, $payment->validated_by);
    }

    public function test_a_reference_is_required(): void
    {
        $client = $this->createClient();
        $order = $this->placeOrder($client);

        $this->postJson("/api/v1/orders/{$order->id}/declare-payment", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reference');
    }

    public function test_someone_else_cannot_declare_on_your_order(): void
    {
        $client = $this->createClient();
        $intruder = $this->createClient();
        $order = $this->placeOrder($client);

        Sanctum::actingAs($intruder, ['*']);
        $this->postJson("/api/v1/orders/{$order->id}/declare-payment", ['reference' => 'X'])
            ->assertNotFound();
    }

    public function test_declaring_on_a_validated_payment_is_refused(): void
    {
        $client = $this->createClient();
        $order = $this->placeOrder($client);

        $admin = $this->createStaff('admin');
        Sanctum::actingAs($admin, ['*']);
        $this->postJson("/api/v1/admin/orders/{$order->id}/validate-payment", [
            'reference_number' => 'DEJA-VALIDE',
        ])->assertOk();

        Sanctum::actingAs($client, ['*']);
        $this->postJson("/api/v1/orders/{$order->id}/declare-payment", ['reference' => 'TROP-TARD'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'already_validated');
    }
}

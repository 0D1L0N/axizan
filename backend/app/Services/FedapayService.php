<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Paiement Mobile Money via FedaPay.
 *
 * Le principe de securite tient en une phrase : **le navigateur ne prouve
 * rien**. Le module de paiement renvoie un identifiant de transaction, et
 * c'est le serveur qui demande a FedaPay si elle existe, si elle a ete payee,
 * et si son montant correspond a la commande.
 *
 * Sans cette verification, il suffirait d'appeler notre API avec un
 * identifiant invente pour faire partir des courses sans avoir paye.
 */
class FedapayService
{
    public function __construct(private PaymentService $paymentService)
    {}

    public function isEnabled(): bool
    {
        return (bool) config('payments.fedapay.enabled')
            && filled(config('payments.fedapay.public_key'))
            && filled(config('payments.fedapay.secret_key'));
    }

    /** Prépare le SDK. La clé secrète ne quitte jamais le serveur. */
    private function boot(): void
    {
        FedaPay::setApiKey(config('payments.fedapay.secret_key'));
        FedaPay::setEnvironment(config('payments.fedapay.sandbox') ? 'sandbox' : 'live');
    }

    /**
     * Interroge FedaPay sur une transaction.
     *
     * @return array{ok: bool, status: ?string, amount: ?float, raw: mixed}
     */
    public function verify(string $transactionId): array
    {
        $this->boot();

        try {
            $transaction = Transaction::retrieve($transactionId);
        } catch (\Throwable $e) {
            // Agregateur injoignable ou transaction inconnue : on ne confirme
            // rien. Mieux vaut un client qui patiente qu'une commande partie
            // sans paiement.
            Log::warning('FedaPay injoignable', ['transaction' => $transactionId, 'error' => $e->getMessage()]);

            return ['ok' => false, 'status' => null, 'amount' => null, 'raw' => null];
        }

        return [
            // wasPaid() couvre approved, transferred et les remboursements
            // partiels — la liste officielle du SDK, pas une comparaison
            // maison qui vieillirait mal.
            'ok' => $transaction->wasPaid(),
            'status' => $transaction->status ?? null,
            'amount' => isset($transaction->amount) ? (float) $transaction->amount : null,
            'raw' => $transaction,
        ];
    }

    /**
     * Confirme le paiement d'une commande a partir d'un identifiant FedaPay.
     *
     * Idempotent : le retour du navigateur ET le webhook peuvent arriver tous
     * les deux, dans n'importe quel ordre. Confirmer deux fois ne doit rien
     * casser ni rien dupliquer.
     *
     * @return array{ok: bool, error: ?string, message: ?string}
     */
    public function settle(Order $order, string $transactionId, string $source = 'client'): array
    {
        $payment = $order->payment;

        if (! $payment) {
            return ['ok' => false, 'error' => 'no_payment', 'message' => 'Aucun paiement rattaché à cette commande.'];
        }

        if ($payment->status === PaymentStatus::COMPLETED) {
            // Deja regle — l'autre canal est arrive avant.
            return ['ok' => true, 'error' => null, 'message' => 'Paiement déjà confirmé.'];
        }

        $verification = $this->verify($transactionId);

        if (! $verification['ok']) {
            return [
                'ok' => false,
                'error' => 'transaction_not_paid',
                'message' => 'Cette transaction n’est pas confirmée par l’opérateur.',
            ];
        }

        // Controle du montant : sans lui, on pourrait payer 100 F une commande
        // de 15 000 F et faire partir les courses.
        $expected = (float) $order->total;
        $paid = (float) ($verification['amount'] ?? 0);

        if ($paid + 0.5 < $expected) {
            Log::warning('FedaPay : montant insuffisant', [
                'order' => $order->order_number,
                'attendu' => $expected,
                'recu' => $paid,
            ]);

            return [
                'ok' => false,
                'error' => 'amount_mismatch',
                'message' => 'Le montant réglé ne correspond pas au total de la commande.',
            ];
        }

        DB::transaction(function () use ($order, $payment, $transactionId, $verification, $source, $paid) {
            $payment->update([
                'payment_method' => 'fedapay',
                'transaction_id' => $transactionId,
                'reference_number' => $transactionId,
                'payment_provider' => $this->providerFrom($verification['raw']),
            ]);

            // Passe le paiement et la commande en « payé », et notifie le client.
            $this->paymentService->confirmPayment($payment, transactionId: $transactionId);

            OrderEvent::record(
                $order,
                'payment.settled',
                reason: 'Confirmé par FedaPay',
                context: [
                    'transaction_id' => $transactionId,
                    'amount' => $paid,
                    'status' => $verification['status'],
                    // D'ou vient la confirmation : le retour du navigateur ou
                    // le webhook. Utile quand un reglement se comporte mal.
                    'source' => $source,
                ]
            );
        });

        return ['ok' => true, 'error' => null, 'message' => 'Paiement confirmé.'];
    }

    /**
     * FedaPay decrit l'operateur de facon variable selon le canal ; on le
     * ramene a l'enumeration de la table payments.
     */
    private function providerFrom($raw): ?string
    {
        $haystack = strtolower(json_encode($raw) ?: '');

        foreach (['mtn', 'moov', 'celtiis'] as $provider) {
            if (str_contains($haystack, $provider)) {
                return $provider;
            }
        }

        return null;
    }
}

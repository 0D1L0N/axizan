<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\FedapayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Paiement FedaPay.
 *
 * Deux chemins menent a la confirmation, et c'est voulu :
 *
 *  - `confirm` : le navigateur revient du module de paiement avec un
 *    identifiant. Rapide, mais peu fiable — le client peut fermer l'onglet
 *    juste apres avoir paye, ou perdre le reseau.
 *  - `webhook` : FedaPay nous appelle de serveur a serveur. C'est la source
 *    de verite, et le seul chemin qui fonctionne quand le client a ferme son
 *    telephone.
 *
 * Les deux passent par la meme methode idempotente : peu importe lequel
 * arrive en premier, ou si les deux arrivent.
 */
class FedapayController extends Controller
{
    public function __construct(private FedapayService $fedapay)
    {}

    /**
     * Demande de paiement Mobile Money : le telephone du client sonne.
     *
     * Aucune page de paiement, aucun formulaire de carte — le parcours normal
     * au Benin. Le client saisit son numero ici, confirme sur son telephone,
     * et c'est le webhook qui debloque la commande.
     */
    public function request(Request $request, $orderId)
    {
        $data = $request->validate([
            'provider' => 'required|in:mtn,moov,celtiis',
            'phone' => 'required|string|max:30',
        ], [
            'provider.in' => 'Choisissez MTN, Moov ou Celtiis.',
            'phone.required' => 'Indiquez le numéro Mobile Money à débiter.',
        ]);

        $order = Order::with('payment', 'client')->find($orderId);

        if (! $order || $order->client_id !== $request->user()->id) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        if (! $this->fedapay->isEnabled()) {
            return response()->json([
                'success' => false,
                'error' => 'fedapay_disabled',
                'message' => 'Le paiement en ligne n’est pas activé.',
            ], 503);
        }

        if ($order->payment_status === 'completed') {
            return response()->json([
                'success' => false,
                'error' => 'already_paid',
                'message' => 'Cette commande est déjà payée.',
            ], 422);
        }

        $result = $this->fedapay->requestPayment($order, $data['provider'], $data['phone']);

        return response()->json([
            'success' => $result['ok'],
            'error' => $result['error'],
            'message' => $result['message'],
            'data' => ['transaction_id' => $result['transaction_id']],
        ], $result['ok'] ? 202 : 422);
    }

    /**
     * Ou en est le paiement ?
     *
     * Le webhook reste la source de verite, mais il ne suffit pas : en
     * developpement il n'y a pas d'URL publique, et en production une
     * notification peut se perdre. Cet appel REDEMANDE a FedaPay tant que le
     * paiement est en attente, ce qui debloque la commande meme si aucune
     * notification n'arrive jamais.
     */
    public function status(Request $request, $orderId)
    {
        $order = Order::with('payment')->find($orderId);

        if (! $order || $order->client_id !== $request->user()->id) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $payment = $order->payment;
        $transactionId = $payment?->transaction_id;

        if ($payment && $payment->status !== \App\Enums\PaymentStatus::COMPLETED && $transactionId) {
            $this->fedapay->settle($order, $transactionId, source: 'polling');
            $order->refresh()->load('payment');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'payment_status' => $order->payment_status,
                'status' => $order->status,
                // `pending` cote FedaPay signifie « le telephone du client
                // sonne encore » : l'interface doit continuer d'attendre.
                'awaiting_confirmation' => $order->payment_status !== 'completed' && (bool) $transactionId,
            ],
        ]);
    }

    /**
     * Le client revient du module de paiement. On interroge FedaPay avant de
     * croire quoi que ce soit.
     */
    public function confirm(Request $request, $orderId)
    {
        $data = $request->validate([
            'transaction_id' => 'required|string|max:190',
        ]);

        $order = Order::with('payment')->find($orderId);

        if (! $order || $order->client_id !== $request->user()->id) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        if (! $this->fedapay->isEnabled()) {
            return response()->json([
                'success' => false,
                'error' => 'fedapay_disabled',
                'message' => 'Le paiement en ligne n’est pas activé.',
            ], 503);
        }

        $result = $this->fedapay->settle($order, $data['transaction_id'], source: 'client');

        return response()->json([
            'success' => $result['ok'],
            'error' => $result['error'],
            'message' => $result['message'],
            'data' => $order->fresh()->load('payment'),
        ], $result['ok'] ? 200 : 422);
    }

    /**
     * Notification serveur a serveur de FedaPay.
     *
     * Route publique : l'authenticite est etablie par la signature
     * `X-FEDAPAY-SIGNATURE`, verifiee sur le corps BRUT de la requete. Le
     * moindre reformatage du JSON invaliderait la signature, d'ou
     * getContent() plutot que les donnees deja decodees par Laravel.
     *
     * Le secret du webhook est distinct de la cle API : c'est la cause n°1
     * des erreurs de signature.
     */
    public function webhook(Request $request)
    {
        $secret = config('payments.fedapay.webhook_secret');

        if (! $secret) {
            Log::error('Webhook FedaPay reçu mais aucun secret configuré.');

            return response()->json(['success' => false], 500);
        }

        try {
            $event = \FedaPay\Webhook::constructEvent(
                $request->getContent(),
                $request->header('X-FEDAPAY-SIGNATURE', ''),
                $secret
            );
        } catch (\Throwable $e) {
            Log::warning('Webhook FedaPay rejeté', ['ip' => $request->ip(), 'error' => $e->getMessage()]);

            return response()->json(['success' => false], 401);
        }

        $entity = $event->entity ?? null;
        $transactionId = $entity->id ?? null;
        // `custom_metadata` porte ce qu'on a transmis a la creation : notre
        // numero de commande.
        $reference = $entity->custom_metadata->order_number
            ?? ($entity->description ?? null);

        $order = $this->resolveOrder($reference, $transactionId ? (string) $transactionId : null);

        if (! $order) {
            // On repond 200 quand meme : sans cela FedaPay reessaierait une
            // notification qu'on ne saura jamais rattacher.
            Log::warning('Webhook FedaPay sans commande correspondante', [
                'event' => $event->name ?? null,
                'transaction' => $transactionId,
            ]);

            return response()->json(['success' => true, 'ignored' => true]);
        }

        // Seule l'approbation debloque la commande ; les autres evenements du
        // cycle de vie sont acquittes sans effet.
        if (($event->name ?? null) !== 'transaction.approved') {
            Log::info('Webhook FedaPay ignoré', [
                'order' => $order->order_number,
                'event' => $event->name ?? null,
            ]);

            return response()->json(['success' => true]);
        }

        $this->fedapay->settle($order, (string) $transactionId, source: 'webhook');

        return response()->json(['success' => true]);
    }

    /**
     * Retrouve la commande depuis la reference transmise a FedaPay, ou depuis
     * un paiement deja rattache a cette transaction.
     */
    private function resolveOrder(?string $reference, ?string $transactionId): ?Order
    {
        if ($reference) {
            // La description peut contenir plus que le numero : on l'extrait.
            preg_match('/AXZ-\d{8}-\d{5}/', $reference, $matches);
            $number = $matches[0] ?? trim($reference);

            $order = Order::with('payment')->where('order_number', $number)->first();

            if ($order) {
                return $order;
            }
        }

        if ($transactionId) {
            return Order::with('payment')
                ->whereHas('payment', fn ($query) => $query->where('transaction_id', $transactionId))
                ->first();
        }

        return null;
    }
}

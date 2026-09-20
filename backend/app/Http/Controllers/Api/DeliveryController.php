<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DeliveryService;
use App\Models\Delivery;
use App\Models\Order;
use App\Enums\OrderStatus;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(private DeliveryService $deliveryService)
    {}

    /**
     * Lister les commandes prêtes pour livraison (livreur)
     */
    public function listReadyOrders(Request $request)
    {
        // Vérifier rôle livreur
        if (!$request->user()->isLivreur()) {
            return response()->json(['success' => false, 'error' => 'forbidden'], 403);
        }

        // Le depot est indispensable : c'est la que le livreur va chercher le
        // panier. Sans lui, il sait qu'une commande l'attend mais pas ou.
        $orders = Order::where('status', OrderStatus::READY)
            ->with(
                'client:id,name,phone',
                'deliveryAddress',
                'items.product:id,name,price_type',
                'preparateur.market.depot',
                'deliverySlot'
            )
            ->orderBy('ready_at')
            ->paginate($request->get('limit', 20));

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
            ],
        ]);
    }

    /**
     * Mes livraisons en cours (assignées, récupérées ou en transit)
     */
    public function mine(Request $request)
    {
        $user = $request->user();

        if (!$user->isLivreur()) {
            return response()->json(['success' => false, 'error' => 'forbidden'], 403);
        }

        $deliveries = $user->deliveries()
            ->whereIn('status', ['assigned', 'picked_up', 'in_transit'])
            ->with(
                'order.client:id,name,phone',
                'order.deliveryAddress',
                'order.items.product:id,name,price_type',
                'order.preparateur.market.depot',
                'order.deliverySlot'
            )
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $deliveries]);
    }

    /**
     * Accepter une livraison
     */
    public function accept($orderId, Request $request)
    {
        $user = $request->user();

        if (!$user->isLivreur()) {
            return response()->json(['success' => false, 'error' => 'forbidden'], 403);
        }

        $order = Order::find($orderId);

        if (!$order || $order->status !== OrderStatus::READY) {
            return response()->json(['success' => false, 'error' => 'invalid_order'], 404);
        }

        try {
            $delivery = $this->deliveryService->assignOrderToLivreur($order, $user);
            $this->deliveryService->pickupDelivery($delivery);

            return response()->json([
                'success' => true,
                'data' => $delivery->load('order', 'livreur'),
            ]);
        } catch (QueryException $e) {
            /*
             * Deux livreurs sur le meme panier.
             *
             * Le depot affiche la liste a TOUS les livreurs actifs : le premier
             * arrive prend, c'est le principe. L'unicite de `deliveries.order_id`
             * en base empeche depuis toujours la seconde course — mais elle
             * repondait « SQLSTATE[23505] : duplicate key value violates unique
             * constraint », affiche tel quel sur le telephone du livreur.
             *
             * Le garde-fou etait bon ; seule la phrase manquait.
             */
            if ($this->isDuplicateDelivery($e)) {
                return response()->json([
                    'success' => false,
                    'error' => 'already_taken',
                    'message' => 'Un autre livreur vient de prendre ce panier.',
                ], 409);
            }

            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Violation de l'unicite de `deliveries.order_id` ?
     *
     * 23505 est le code SQLSTATE normalise de la violation d'unicite : il vaut
     * pour PostgreSQL comme pour SQLite, la ou le texte du message differe de
     * l'un a l'autre.
     */
    private function isDuplicateDelivery(QueryException $e): bool
    {
        return $e->getCode() === '23505'
            || str_contains($e->getMessage(), 'deliveries.order_id')
            || str_contains($e->getMessage(), 'deliveries_order_id_unique');
    }

    /**
     * Mettre à jour position GPS
     */
    public function updateLocation($deliveryId, Request $request)
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $delivery = Delivery::find($deliveryId);

        if (!$delivery || $delivery->livreur_id !== $request->user()->id) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $delivery = $this->deliveryService->updateLocation(
            $delivery,
            $validated['latitude'],
            $validated['longitude']
        );

        return response()->json([
            'success' => true,
            'data' => $delivery,
        ]);
    }

    /**
     * Démarrer livraison
     */
    public function startDelivery($deliveryId, Request $request)
    {
        $delivery = Delivery::find($deliveryId);

        if (!$delivery || $delivery->livreur_id !== $request->user()->id) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $delivery = $this->deliveryService->startDelivery($delivery);

        return response()->json([
            'success' => true,
            'data' => $delivery,
        ]);
    }

    /**
     * Confirmer livraison avec preuve
     */
    public function confirm($deliveryId, Request $request)
    {
        // L'autorisation avant la validation : sinon un inconnu qui tape cette
        // route apprend la forme attendue du corps de requete avant meme
        // qu'on lui dise que la livraison ne le regarde pas.
        $delivery = Delivery::find($deliveryId);

        if (!$delivery || $delivery->livreur_id !== $request->user()->id) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        // La photo est OBLIGATOIRE. C'est la seule preuve que le panier a bien
        // ete remis, a la bonne personne, dans l'etat annonce. Sans elle, une
        // contestation « je n'ai rien recu » ou « les tomates etaient
        // ecrasees » ne peut se trancher que sur parole, et le client a paye
        // d'avance des courses qu'il n'a pas vu faire.
        $validated = $request->validate([
            'proof_photo' => 'required|image|max:5120',
            'signature_data' => 'nullable|string',
            'delivery_notes' => 'nullable|string|max:500',
        ], [
            'proof_photo.required' => 'Photographiez la remise avant de confirmer : c’est la preuve de la livraison.',
            'proof_photo.image' => 'Le fichier doit être une image.',
            'proof_photo.max' => 'Photo trop lourde (5 Mo maximum).',
        ]);

        try {
            $delivery = $this->deliveryService->confirmDelivery(
                $delivery,
                $request->file('proof_photo'),
                $validated['signature_data'] ?? null,
                $validated['delivery_notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $delivery,
                'message' => 'Livraison confirmée',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Stats livreur
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        $today = $user->deliveries()
            ->whereDate('created_at', today())
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_deliveries_today' => $today->count(),
                'successful_deliveries' => $today->where('status', 'delivered')->count(),
                'failed_deliveries' => $today->where('status', 'failed')->count(),
                // Frais de livraison encaissés par Axizán sur ses courses du
                // jour — pas sa rémunération, qui relève du contrat livreur.
                'delivery_fees_today' => \App\Support\Money::round(
                    $today->sum(fn ($d) => (float) ($d->order->delivery_fee ?? 0))
                ),
            ],
        ]);
    }
}

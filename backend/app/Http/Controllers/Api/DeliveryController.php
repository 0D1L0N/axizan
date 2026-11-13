<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DeliveryService;
use App\Models\Delivery;
use App\Models\Order;
use App\Enums\OrderStatus;
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

        $orders = Order::where('status', OrderStatus::READY)
            ->with('client', 'deliveryAddress', 'items')
            ->latest()
            ->paginate($this->perPage($request, 20));

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
            ->with('order.client', 'order.deliveryAddress')
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

        // Une commande payee ne part pas avec un inconnu : l'identite du
        // livreur doit avoir ete verifiee par un administrateur.
        if (!$user->kyc_verified) {
            return response()->json([
                'success' => false,
                'error' => 'kyc_required',
                'message' => 'Votre identité doit être vérifiée avant de prendre des livraisons.',
            ], 403);
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
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
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

        try {
            $delivery = $this->deliveryService->startDelivery($delivery);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }

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
        $validated = $request->validate([
            'proof_photo' => 'nullable|image|max:5120',
            'signature_data' => 'nullable|string',
            'delivery_notes' => 'nullable|string|max:500',
        ]);

        $delivery = Delivery::find($deliveryId);

        if (!$delivery || $delivery->livreur_id !== $request->user()->id) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

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
                'total_earnings_today' => $today->sum(function ($d) {
                    return $d->order->delivery_fee ?? 0;
                }),
                'average_rating' => $user->receivedReviews()
                    ->whereNotNull('rating')
                    ->avg('rating') ?? 0,
            ],
        ]);
    }
}

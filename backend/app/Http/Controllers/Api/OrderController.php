<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Models\Order;
use App\Models\Address;
use App\Enums\OrderStatus;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService)
    {}

    /**
     * Créer une commande
     */
    public function store(Request $request, OrderService $orderService)
    {
        $user = $request->user();

        $validated = $request->validate([
            'delivery_address_id' => 'required|exists:addresses,id',
            'delivery_slot_id' => 'required|exists:delivery_slots,id',
            'delivery_date' => 'required|date|after:today',
            'payment_method' => 'in:mobile_money,cash,card',
            'promo_code_id' => 'nullable|exists:promotions,id',
            'client_notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
        ]);

        try {
            // TODO: Récupérer les items du panier
            $cartItems = $request->items;

            $order = $orderService->createOrder($user, $cartItems, $validated);

            return response()->json([
                'success' => true,
                'data' => $order->load('items', 'client', 'deliveryAddress'),
                'message' => 'Commande créée avec succès',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'order_creation_failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Lister les commandes du client
     */
    public function index(Request $request)
    {
        $query = Order::byClient($request->user()->id);

        // Filtrer par statut
        if ($request->has('status')) {
            $statuses = explode(',', $request->status);
            $query->whereIn('status', $statuses);
        }

        $orders = $query
            ->with('items', 'client', 'deliveryAddress', 'delivery')
            ->latest()
            ->paginate($request->get('limit', 10));

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    /**
     * Détail commande
     */
    public function show($id, Request $request)
    {
        $order = Order::with('items', 'client', 'deliveryAddress', 'delivery', 'payment')
            ->find($id);

        if (!$order || ($order->client_id !== $request->user()->id && $request->user()->role !== 'admin')) {
            return response()->json([
                'success' => false,
                'error' => 'not_found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Annuler commande
     */
    public function cancel($id, Request $request)
    {
        $order = Order::find($id);

        if (!$order || $order->client_id !== $request->user()->id) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        try {
            $this->orderService->cancelOrder($order, $request->get('reason'));

            return response()->json([
                'success' => true,
                'data' => $order,
                'message' => 'Commande annulée',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Mettre à jour statut (admin)
     */
    public function updateStatus($id, Request $request)
    {
        // Vérifier permission admin
        if ($request->user()->role !== 'admin') {
            return response()->json(['success' => false, 'error' => 'forbidden'], 403);
        }

        $order = Order::find($id);

        if (!$order) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|string',
        ]);

        $order->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }
}

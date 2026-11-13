<?php

namespace App\Http\Controllers\Preparateur;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService)
    {}

    // Liste des commandes assignées au préparateur
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Order::where('preparateur_id', $user->id)
            ->whereIn('status', ['pending','accepted','preparing'])
            ->with('items', 'deliveryAddress')
            ->orderBy('created_at');

        return response()->json($query->get());
    }

    // Accepter/claim une commande
    public function accept($id, Request $request)
    {
        $order = Order::find($id);
        if (! $order) return response()->json(['message' => 'not_found'], 404);

        try {
            $this->orderService->acceptOrder($order, $request->user());
            return response()->json(['success' => true, 'data' => $order]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function startPreparing($id, Request $request)
    {
        $order = Order::find($id);
        if (! $order) return response()->json(['message' => 'not_found'], 404);

        try {
            $this->orderService->startPreparing($order, $request->get('notes'));
            return response()->json(['success' => true, 'data' => $order]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function markReady($id, Request $request)
    {
        $order = Order::find($id);
        if (! $order) return response()->json(['message' => 'not_found'], 404);

        try {
            $this->orderService->markReady($order, $request->get('notes'));
            return response()->json(['success' => true, 'data' => $order]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // Ajuster le stock d'un produit (action préparateur)
    public function adjustStock(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'adjustment' => 'required|numeric',
            'reason' => 'nullable|string|max:255',
        ]);

        $product = Product::find($validated['product_id']);
        if (! $product) return response()->json(['message' => 'not_found'], 404);

        if ($validated['adjustment'] < 0) {
            $product->decreaseStock(abs($validated['adjustment']));
        } else {
            $product->increaseStock($validated['adjustment']);
        }

        // Optionnel: log d'audit
        return response()->json(['success' => true, 'data' => $product->fresh()]);
    }
}

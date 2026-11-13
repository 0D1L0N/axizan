<?php

namespace App\Http\Controllers\Preparateur;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    // Mark substitution or removal for an item
    public function substitute($orderId, Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|integer',
            'action' => 'required|in:call,replace,remove',
            'replacement_product_id' => 'nullable|exists:products,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $order = Order::find($orderId);
        if (! $order) return response()->json(['message' => 'not_found'], 404);

        // Store substitution request in preparateur_notes for now (simple approach)
        $existing = $order->preparateur_notes ?? '';
        $entry = sprintf("[substitution] item:%s action:%s replacement:%s notes:%s\n",
            $validated['item_id'], $validated['action'], $validated['replacement_product_id'] ?? 'none', $validated['notes'] ?? '');

        $order->update(['preparateur_notes' => $existing.$entry]);

        return response()->json(['success' => true, 'message' => 'substitution_recorded']);
    }
}

<?php

namespace App\Http\Controllers\Livreur;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $deliveries = Delivery::where('livreur_id', $user->id)
            ->with('order', 'order.items')
            ->whereIn('status', ['assigned','pickup','in_transit'])
            ->orderBy('created_at')
            ->get();

        return response()->json($deliveries);
    }

    public function accept($id, Request $request)
    {
        $delivery = Delivery::find($id);
        if (! $delivery) return response()->json(['message' => 'not_found'], 404);
        if ($delivery->livreur_id !== $request->user()->id) return response()->json(['message' => 'forbidden'], 403);

        $delivery->update(['status' => 'pickup', 'pickup_time' => now()]);
        return response()->json(['success' => true, 'data' => $delivery]);
    }

    public function updateLocation($id, Request $request)
    {
        $delivery = Delivery::find($id);
        if (! $delivery) return response()->json(['message' => 'not_found'], 404);
        if ($delivery->livreur_id !== $request->user()->id) return response()->json(['message' => 'forbidden'], 403);

        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $delivery->update([
            'current_latitude' => $validated['latitude'],
            'current_longitude' => $validated['longitude'],
            'last_location_update' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function confirm($id, Request $request)
    {
        $delivery = Delivery::find($id);
        if (! $delivery) return response()->json(['message' => 'not_found'], 404);
        if ($delivery->livreur_id !== $request->user()->id) return response()->json(['message' => 'forbidden'], 403);

        $validated = $request->validate([
            'photo' => 'nullable|file|mimes:jpg,jpeg,png',
            'signature' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('deliveries', 'public');
            $delivery->proof_photo_url = '/storage/'.$path;
        }

        if (!empty($validated['signature'])) {
            $delivery->signature_data = $validated['signature'];
        }

        $delivery->status = 'delivered';
        $delivery->delivery_time = now();
        $delivery->delivery_notes = $validated['notes'] ?? $delivery->delivery_notes;
        $delivery->save();

        // Mark related order delivered
        $order = $delivery->order;
        if ($order) {
            $order->update(['status' => 'delivered', 'delivered_at' => now()]);
        }

        return response()->json(['success' => true, 'data' => $delivery]);
    }
}

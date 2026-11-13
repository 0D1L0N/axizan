<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliverySlotRequest;
use App\Models\DeliverySlot;
use Illuminate\Http\Request;

class DeliverySlotController extends Controller
{
    public function index()
    {
        return DeliverySlot::with('zone')->get();
    }

    public function show(DeliverySlot $deliverySlot)
    {
        return $deliverySlot->load('zone');
    }

    public function store(DeliverySlotRequest $request)
    {
        $slot = DeliverySlot::create($request->validated());
        return response()->json($slot, 201);
    }

    public function update(DeliverySlotRequest $request, DeliverySlot $deliverySlot)
    {
        $deliverySlot->update($request->validated());
        return response()->json($deliverySlot);
    }

    public function destroy(DeliverySlot $deliverySlot)
    {
        $deliverySlot->delete();
        return response()->json(['message' => 'Deleted']);
    }
}

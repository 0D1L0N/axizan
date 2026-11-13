<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliverySlotRequest;
use App\Models\DeliverySlot;

class DeliverySlotController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => DeliverySlot::with('zone')->orderBy('zone_id')->orderBy('time_start')->get(),
        ]);
    }

    public function show(DeliverySlot $deliverySlot)
    {
        return response()->json(['success' => true, 'data' => $deliverySlot->load('zone')]);
    }

    public function store(DeliverySlotRequest $request)
    {
        $slot = DeliverySlot::create($request->validated());

        return response()->json(['success' => true, 'data' => $slot->load('zone')], 201);
    }

    public function update(DeliverySlotRequest $request, DeliverySlot $deliverySlot)
    {
        $deliverySlot->update($request->validated());

        return response()->json(['success' => true, 'data' => $deliverySlot->load('zone')]);
    }

    /**
     * Un creneau qui porte deja des commandes ne se supprime pas : elles
     * perdraient leur horaire de livraison. On le desactive.
     */
    public function destroy(DeliverySlot $deliverySlot)
    {
        if ($deliverySlot->orders()->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'slot_in_use',
                'message' => 'Des commandes utilisent ce créneau : désactivez-le plutôt.',
            ], 422);
        }

        $deliverySlot->delete();

        return response()->json(['success' => true, 'message' => 'Créneau supprimé']);
    }
}

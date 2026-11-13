<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryZoneRequest;
use App\Models\DeliveryZone;

class DeliveryZoneController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => DeliveryZone::withCount('slots')->orderBy('name')->get(),
        ]);
    }

    public function show(DeliveryZone $deliveryZone)
    {
        return response()->json(['success' => true, 'data' => $deliveryZone->load('slots')]);
    }

    public function store(DeliveryZoneRequest $request)
    {
        $zone = DeliveryZone::create($request->validated());

        return response()->json(['success' => true, 'data' => $zone], 201);
    }

    public function update(DeliveryZoneRequest $request, DeliveryZone $deliveryZone)
    {
        $deliveryZone->update($request->validated());

        return response()->json(['success' => true, 'data' => $deliveryZone]);
    }

    /**
     * Une zone deja rattachee a des adresses ou des creneaux ne se supprime pas :
     * les adresses retomberaient sur les frais par defaut sans que personne ne
     * le voie. On la desactive.
     */
    public function destroy(DeliveryZone $deliveryZone)
    {
        if ($deliveryZone->addresses()->exists() || $deliveryZone->slots()->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'zone_in_use',
                'message' => 'Cette zone est utilisée par des adresses ou des créneaux : désactivez-la plutôt.',
            ], 422);
        }

        $deliveryZone->delete();

        return response()->json(['success' => true, 'message' => 'Zone supprimée']);
    }
}

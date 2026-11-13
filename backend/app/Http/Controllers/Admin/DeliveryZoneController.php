<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryZoneRequest;
use App\Models\DeliveryZone;
use Illuminate\Http\Request;

class DeliveryZoneController extends Controller
{
    public function index()
    {
        return DeliveryZone::all();
    }

    public function show(DeliveryZone $deliveryZone)
    {
        return $deliveryZone;
    }

    public function store(DeliveryZoneRequest $request)
    {
        $data = $request->validated();
        $zone = DeliveryZone::create($data);
        return response()->json($zone, 201);
    }

    public function update(DeliveryZoneRequest $request, DeliveryZone $deliveryZone)
    {
        $deliveryZone->update($request->validated());
        return response()->json($deliveryZone);
    }

    public function destroy(DeliveryZone $deliveryZone)
    {
        $deliveryZone->delete();
        return response()->json(['message' => 'Deleted']);
    }
}

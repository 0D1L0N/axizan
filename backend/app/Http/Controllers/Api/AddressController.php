<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request) { return response()->json(['success' => true, 'data' => $request->user()->addresses()->active()->latest()->get()]); }
    public function store(Request $request) { $data = $this->validated($request); if ($request->boolean('is_default')) { $request->user()->addresses()->update(['is_default' => false]); } $address = $request->user()->addresses()->create($data); return response()->json(['success' => true, 'data' => $address], 201); }
    public function show(Request $request, Address $address) { abort_unless($address->user_id === $request->user()->id, 404); return response()->json(['success' => true, 'data' => $address]); }
    public function update(Request $request, Address $address) { abort_unless($address->user_id === $request->user()->id, 404); $data = $this->validated($request); if (($data['is_default'] ?? false) === true) { $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]); } $address->update($data); return response()->json(['success' => true, 'data' => $address->fresh()]); }
    public function destroy(Request $request, Address $address) { abort_unless($address->user_id === $request->user()->id, 404); $address->update(['is_active' => false]); return response()->json(['success' => true]); }
    private function validated(Request $request): array { return $request->validate(['label' => 'required|string|max:80', 'quartier' => 'required|string|max:120', 'rue' => 'nullable|string|max:120', 'numero_rue' => 'nullable|string|max:40', 'latitude' => 'nullable|numeric|between:-90,90', 'longitude' => 'nullable|numeric|between:-180,180', 'maps_url' => 'nullable|url|max:500', 'detailed_instructions' => 'nullable|string|max:500', 'landmark' => 'nullable|string|max:160', 'delivery_zone_id' => 'nullable|exists:delivery_zones,id', 'is_default' => 'sometimes|boolean']); }
}

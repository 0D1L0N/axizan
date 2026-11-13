<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorisation geree par le middleware « admin » de la route.
        return true;
    }

    public function rules(): array
    {
        $zone = $this->route('delivery_zone');

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('delivery_zones', 'name')->ignore($zone?->id)],
            'bounds_ne_lat' => 'nullable|numeric|between:-90,90',
            'bounds_ne_lon' => 'nullable|numeric|between:-180,180',
            'bounds_sw_lat' => 'nullable|numeric|between:-90,90',
            'bounds_sw_lon' => 'nullable|numeric|between:-180,180',
            'base_delivery_fee' => 'sometimes|numeric|min:0',
            'extra_fee_per_km' => 'sometimes|numeric|min:0',
            'min_order_amount' => 'sometimes|numeric|min:0',
            'small_order_fee' => 'sometimes|numeric|min:0',
            'small_order_action' => 'sometimes|in:apply_fee,block',
            'free_delivery_threshold' => 'nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ];
    }
}

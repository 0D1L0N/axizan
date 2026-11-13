<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        // authorization handled at route level (admin check)
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'bounds_ne_lat' => 'nullable|numeric',
            'bounds_ne_lon' => 'nullable|numeric',
            'bounds_sw_lat' => 'nullable|numeric',
            'bounds_sw_lon' => 'nullable|numeric',
            'base_delivery_fee' => 'nullable|numeric|min:0',
            'extra_fee_per_km' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'small_order_fee' => 'nullable|numeric|min:0',
            'small_order_action' => 'nullable|in:apply_fee,block',
            'free_delivery_threshold' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }
}

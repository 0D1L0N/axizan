<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeliverySlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'zone_id' => 'required|exists:delivery_zones,id',
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i|after:time_start',
            'display_label' => 'nullable|string|max:50',
            'max_orders_per_slot' => 'nullable|integer|min:1',
            'current_orders_count' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
            'additional_fee' => 'nullable|numeric|min:0',
        ];
    }
}

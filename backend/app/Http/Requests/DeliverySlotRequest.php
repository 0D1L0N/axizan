<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeliverySlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorisation geree par le middleware « admin » de la route.
        return true;
    }

    public function rules(): array
    {
        // Les heures relues en base arrivent en H:i:s : on accepte les deux formes.
        return [
            'zone_id' => 'nullable|exists:delivery_zones,id',
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'time_start' => 'required|date_format:H:i,H:i:s',
            'time_end' => 'required|date_format:H:i,H:i:s|after:time_start',
            'display_label' => 'required|string|max:255',
            'max_orders_per_slot' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'priority' => 'sometimes|integer|min:0',
            'additional_fee' => 'sometimes|numeric|min:0',
        ];
    }
}

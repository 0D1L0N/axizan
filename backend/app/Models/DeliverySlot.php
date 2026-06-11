<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliverySlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'zone_id',
        'day_of_week',
        'time_start',
        'time_end',
        'display_label',
        'max_orders_per_slot',
        'current_orders_count',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function zone()
    {
        return $this->belongsTo(DeliveryZone::class, 'zone_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->active()
            ->whereRaw('current_orders_count < max_orders_per_slot');
    }

    // Helpers
    public function isAvailable()
    {
        return $this->is_active && $this->current_orders_count < $this->max_orders_per_slot;
    }

    public function getAvailableSpotsAttribute()
    {
        return $this->max_orders_per_slot - $this->current_orders_count;
    }

    public function incrementOrderCount()
    {
        $this->increment('current_orders_count');
    }

    public function decrementOrderCount()
    {
        if ($this->current_orders_count > 0) {
            $this->decrement('current_orders_count');
        }
    }
}

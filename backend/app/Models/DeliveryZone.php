<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'bounds_ne_lat',
        'bounds_ne_lon',
        'bounds_sw_lat',
        'bounds_sw_lon',
        'base_delivery_fee',
        'extra_fee_per_km',
        'is_active',
    ];

    protected $casts = [
        'bounds_ne_lat' => 'float',
        'bounds_ne_lon' => 'float',
        'bounds_sw_lat' => 'float',
        'bounds_sw_lon' => 'float',
        'base_delivery_fee' => 'decimal:2',
        'extra_fee_per_km' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function slots()
    {
        return $this->hasMany(DeliverySlot::class, 'zone_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helpers
    public function containsCoordinates($latitude, $longitude)
    {
        return $latitude >= $this->bounds_sw_lat
            && $latitude <= $this->bounds_ne_lat
            && $longitude >= $this->bounds_sw_lon
            && $longitude <= $this->bounds_ne_lon;
    }
}

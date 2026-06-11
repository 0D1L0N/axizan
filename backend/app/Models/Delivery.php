<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'livreur_id',
        'status',
        'pickup_time',
        'delivery_time',
        'current_latitude',
        'current_longitude',
        'last_location_update',
        'proof_photo_url',
        'signature_data',
        'delivery_notes',
        'client_rating',
        'client_feedback',
    ];

    protected $casts = [
        'status' => DeliveryStatus::class,
        'current_latitude' => 'float',
        'current_longitude' => 'float',
        'pickup_time' => 'datetime',
        'delivery_time' => 'datetime',
        'last_location_update' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function livreur()
    {
        return $this->belongsTo(User::class, 'livreur_id');
    }

    // Scopes
    public function scopeByLivreur($query, $livreurId)
    {
        return $query->where('livreur_id', $livreurId);
    }

    public function scopeByStatus($query, DeliveryStatus $status)
    {
        return $query->where('status', $status);
    }

    // Helpers
    public function updateLocation($latitude, $longitude)
    {
        $this->update([
            'current_latitude' => $latitude,
            'current_longitude' => $longitude,
            'last_location_update' => now(),
        ]);
    }

    public function getDeliveryDurationAttribute()
    {
        if ($this->pickup_time && $this->delivery_time) {
            return $this->delivery_time->diffInMinutes($this->pickup_time);
        }
        return null;
    }
}

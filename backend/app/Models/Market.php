<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Marche : le lieu ou le preparateur se presente chez les fournisseurs.
 */
class Market extends Model
{
    protected $fillable = ['name', 'city', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function suppliers()
    {
        return $this->hasMany(Supplier::class);
    }

    /** Zones de livraison desservies depuis ce marche. */
    public function zones()
    {
        return $this->belongsToMany(DeliveryZone::class, 'delivery_zone_market');
    }

    public function preparateurs()
    {
        return $this->hasMany(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

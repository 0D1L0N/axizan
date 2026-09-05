<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Marche : le lieu ou le preparateur se presente chez les fournisseurs.
 */
class Market extends Model
{
    protected $fillable = ['name', 'city', 'address', 'landmark', 'opening_hours', 'notes', 'maps_query', 'depot_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** Depot ou les preparateurs de ce marche deposent les paniers. */
    public function depot()
    {
        return $this->belongsTo(Depot::class);
    }

    public function suppliers()
    {
        return $this->hasMany(Supplier::class);
    }

    /** Zones de livraison desservies depuis ce marche. */
    public function zones()
    {
        return $this->belongsToMany(DeliveryZone::class, 'delivery_zone_market');
    }

    /**
     * Preparateurs rattaches a ce marche.
     *
     * Le filtre sur le role n'est pas decoratif : `market_id` vit sur `users`,
     * donc sans lui la relation ramenerait n'importe quel compte rattache au
     * marche un jour, et le compteur affiche a l'administration mentirait.
     */
    public function preparateurs()
    {
        return $this->hasMany(User::class)->where('role', \App\Enums\UserRole::PREPARATEUR);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

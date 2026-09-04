<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fournisseur partenaire. Le preparateur se sert chez lui sans payer ;
 * le reglement se fait ensuite entre Axizan et le fournisseur.
 */
class Supplier extends Model
{
    protected $fillable = ['market_id', 'name', 'stall', 'phone', 'speciality', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function market()
    {
        return $this->belongsTo(Market::class);
    }

    /** Lignes de commande retirees chez ce fournisseur — base du futur point. */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

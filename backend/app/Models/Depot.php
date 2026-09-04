<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Depot : lieu de remise entre le preparateur et le livreur.
 */
class Depot extends Model
{
    protected $fillable = ['name', 'address', 'city', 'landmark', 'phone', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function markets()
    {
        return $this->hasMany(Market::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

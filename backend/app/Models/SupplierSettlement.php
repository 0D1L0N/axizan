<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un reglement effectue aupres d'un fournisseur partenaire.
 *
 * Les lignes de commande couvertes portent son identifiant : une ligne deja
 * rattachee ne peut plus reapparaitre dans un point suivant. C'est ce qui rend
 * un double paiement structurellement impossible, plutot que de reposer sur la
 * vigilance de celui qui fait les comptes.
 */
class SupplierSettlement extends Model
{
    protected $fillable = [
        'supplier_id',
        'period_start',
        'period_end',
        'amount',
        'item_count',
        'settled_by',
        'settled_at',
        'method',
        'reference',
        'note',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'settled_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /** Qui a acte le reglement. */
    public function settledBy()
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    /** Les lignes couvertes par ce reglement. */
    public function items()
    {
        return $this->hasMany(OrderItem::class, 'settlement_id');
    }
}

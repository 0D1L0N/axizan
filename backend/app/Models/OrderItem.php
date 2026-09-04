<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'quantity_unit',
        'actual_quantity',
        'unit_price',
        'actual_unit_price',
        'total_price',
        'actual_total_price',
        'special_instructions',
        'removed_ingredients',
        'substitution_preference',
        'supplier_id',
        'settlement_id',
        'replacement_product_id',
        'collection_status',
        'collected_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'actual_quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'actual_unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'actual_total_price' => 'decimal:2',
        'removed_ingredients' => 'array',
        'collected_at' => 'datetime',
    ];

    /** Chez qui cette ligne a ete retiree. Base du futur point fournisseur. */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /** Produit effectivement pris quand l'original manquait. */
    public function replacementProduct()
    {
        return $this->belongsTo(Product::class, 'replacement_product_id');
    }

    /** Ce que le client recevra reellement. */
    public function effectiveProduct()
    {
        return $this->replacementProduct ?? $this->product;
    }

    public function isCollected(): bool
    {
        return in_array($this->collection_status, ['collected', 'replaced'], true);
    }

    /**
     * Cette ligne a-t-elle un prix reel saisi ?
     *
     * Rempli au moment du point avec le fournisseur, pas par le preparateur :
     * il ne paie rien au marche.
     */
    public function isPurchased(): bool
    {
        return $this->actual_total_price !== null;
    }

    /**
     * Ecart entre ce que le client a paye et ce que l'achat a coute.
     *
     * Positif = marge sur la ligne. Negatif = on a perdu de l'argent dessus,
     * ce qui arrive quand le prix du marche a monte depuis l'affichage.
     * null tant que l'achat n'a pas ete saisi : zero serait un mensonge.
     */
    public function marginAmount(): ?int
    {
        if (! $this->isPurchased()) {
            return null;
        }

        return \App\Support\Money::round((float) $this->total_price - (float) $this->actual_total_price);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

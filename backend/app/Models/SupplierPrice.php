<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tarif convenu avec un fournisseur partenaire, pour un article.
 *
 * C'est la reference du point : elle permet de chiffrer ce qu'on doit sans
 * attendre que le fournisseur annonce un montant, et de voir immediatement un
 * ecart entre ce qui etait convenu et ce qui est reclame.
 *
 * Ce n'est PAS la verite comptable d'un reglement passe : le prix reellement
 * retenu est estampille sur chaque ligne de commande au moment du retrait
 * (`order_items.actual_unit_price`). Renegocier un tarif ne doit jamais faire
 * bouger un point deja regle.
 */
class SupplierPrice extends Model
{
    protected $fillable = ['supplier_id', 'product_id', 'unit_price'];

    protected $casts = ['unit_price' => 'decimal:2'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** Tarif courant, ou null si rien n'a ete convenu pour cet article. */
    public static function priceFor($supplierId, $productId): ?float
    {
        $price = static::where('supplier_id', $supplierId)
            ->where('product_id', $productId)
            ->value('unit_price');

        return $price === null ? null : (float) $price;
    }
}

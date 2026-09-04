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

    /**
     * Categories couvertes par cet etal.
     *
     * C'est ce qui route le preparateur. Auparavant le rapprochement se faisait
     * en comparant le NOM de la categorie a la chaine libre `speciality` :
     * deux textes qui devaient rester identiques a la lettre pres, dans deux
     * tables, sans contrainte. Renommer une categorie cassait le routage en
     * silence. `speciality` reste le libelle affiche, plus la cle de jointure.
     *
     * Plusieurs categories par etal, comme sur le terrain : une vendeuse de
     * legumes propose presque toujours les condiments qui vont avec.
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class);
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

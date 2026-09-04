<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le catalogue epouse l'organisation du marche, et les etals s'y rattachent.
 *
 * Deux corrections liees, qui n'ont de sens qu'ensemble.
 *
 * 1. LES CATEGORIES. « Viandes & poissons » melangeait deux zones distinctes :
 *    a Dantokpa la poissonnerie et la boucherie sont deux endroits differents,
 *    tenus par des gens differents. Un preparateur ne peut pas etre envoye
 *    « au rayon viandes-et-poissons », ca n'existe pas. Meme probleme pour
 *    « Huiles & sauces », qui reunissait de l'huile de palme achetee a un etal
 *    et de la tomate concentree achetee en boutique.
 *
 * 2. LE RATTACHEMENT DES FOURNISSEURS. Le routage vers les etals comparait le
 *    NOM de la categorie a une chaine libre saisie dans `suppliers.speciality`.
 *    Deux chaines qui doivent rester identiques a la lettre pres, dans deux
 *    tables, sans contrainte : renommer une categorie cassait le routage en
 *    silence, et le preparateur perdait ses suggestions d'etal sans qu'aucune
 *    erreur n'apparaisse nulle part.
 *
 *    D'ou la table `category_supplier`. Un fournisseur declare les categories
 *    qu'il couvre — plusieurs, comme sur le terrain : la FAO note que la
 *    plupart des detaillantes proposent des produits complementaires.
 *    `speciality` survit comme libelle affiche, plus comme cle de jointure.
 */
return new class extends Migration
{
    /**
     * Anciennes categories -> nouvelles. Le decoupage se fait ensuite par
     * produit pour les deux categories qui se scindent.
     */
    private const RENAMES = [
        'Légumes frais' => 'Légumes du jour',
        'Tubercules' => 'Tubercules & plantain',
        'Épices & condiments' => 'Condiments & épices',
    ];

    /** Produits qui changent de famille, et non de simple etiquette. */
    private const MOVES = [
        'Poissons & fruits de mer' => ['Poisson fumé', 'Tilapia frais', 'Crevettes séchées'],
        'Viandes & œufs' => ['Poulet fermier', 'Oeufs fermiers', 'Viande de bœuf'],
        'Huiles & pâtes' => ['Huile rouge', 'Huile végétale', 'Pâte d’arachide', 'Beurre de karité'],
        'Épicerie & conserves' => ['Tomate concentrée', 'Sardines', 'Eau minérale 1,5 L'],
    ];

    /** Ordre d'affichage : celui de l'assiette, du feculent vers la boisson. */
    private const ORDER = [
        'Tubercules & plantain',
        'Céréales & légumineuses',
        'Légumes du jour',
        'Condiments & épices',
        'Poissons & fruits de mer',
        'Viandes & œufs',
        'Huiles & pâtes',
        'Épicerie & conserves',
        'Fruits',
        'Boissons locales',
    ];

    /** Categories couvertes par chaque etal deja enregistre, par specialite. */
    private const SUPPLIER_COVERAGE = [
        'Légumes frais' => ['Légumes du jour', 'Condiments & épices'],
        'Légumes du jour' => ['Légumes du jour', 'Condiments & épices'],
        'Tubercules' => ['Tubercules & plantain'],
        'Tubercules & plantain' => ['Tubercules & plantain'],
        'Viandes & poissons' => ['Poissons & fruits de mer', 'Viandes & œufs'],
        'Céréales & légumineuses' => ['Céréales & légumineuses'],
        'Huiles & sauces' => ['Huiles & pâtes', 'Épicerie & conserves'],
        'Épices & condiments' => ['Condiments & épices'],
        'Condiments & épices' => ['Condiments & épices'],
        'Fruits' => ['Fruits'],
    ];

    public function up(): void
    {
        Schema::create('category_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['supplier_id', 'category_id']);
        });

        // Une base fraiche part du seeder : rien a reprendre ici.
        if (DB::table('categories')->count() === 0) {
            return;
        }

        foreach (self::RENAMES as $from => $to) {
            DB::table('categories')->where('name', $from)->update(['name' => $to]);
        }

        foreach (self::MOVES as $categoryName => $products) {
            $categoryId = $this->categoryId($categoryName);

            DB::table('products')->whereIn('name', $products)->update(['category_id' => $categoryId]);
        }

        foreach (self::ORDER as $index => $name) {
            DB::table('categories')->where('name', $name)->update(['display_order' => $index + 1]);
        }

        // Les anciennes categories videes par le decoupage n'ont plus de
        // raison d'exister ; celles qui portent encore un produit restent, pour
        // qu'aucun article ne se retrouve orphelin a cause d'un nom oublie.
        DB::table('categories')
            ->whereNotIn('name', self::ORDER)
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('products')
                ->whereColumn('products.category_id', 'categories.id'))
            ->delete();

        $this->linkSuppliers();
    }

    /** Rattache chaque etal aux categories qu'il couvre, depuis sa specialite. */
    private function linkSuppliers(): void
    {
        $categories = DB::table('categories')->pluck('id', 'name');

        foreach (DB::table('suppliers')->get() as $supplier) {
            $covered = self::SUPPLIER_COVERAGE[$supplier->speciality] ?? [];

            $rows = collect($covered)
                ->map(fn (string $name) => $categories[$name] ?? null)
                ->filter()
                ->map(fn ($categoryId) => [
                    'supplier_id' => $supplier->id,
                    'category_id' => $categoryId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            if ($rows !== []) {
                DB::table('category_supplier')->insertOrIgnore($rows);
            }
        }
    }

    private function categoryId(string $name): int
    {
        $id = DB::table('categories')->where('name', $name)->value('id');

        if ($id) {
            return $id;
        }

        return DB::table('categories')->insertGetId([
            'name' => $name,
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Le rattachement se defait ; le decoupage du catalogue, non.
     *
     * Refusionner la poissonnerie et la boucherie ne rendrait service a
     * personne, et les produits deplaces n'ont pas de « place d'origine » qui
     * vaille la peine d'etre restauree. Le seeder fait foi pour le catalogue.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_supplier');
    }
};

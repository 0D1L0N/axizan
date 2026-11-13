<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marches et fournisseurs partenaires.
 *
 * Le modele n'est pas « acheter au marche avec de l'argent » : Axizan a des
 * contrats avec des fournisseurs, le preparateur se presente et se sert, et
 * le reglement se fait ensuite entre Axizan et chaque fournisseur.
 *
 * Consequence : le preparateur ne saisit aucun prix. Ce qu'il DOIT enregistrer,
 * c'est chez QUI il a pris chaque article. Sans cette trace, le jour ou un
 * fournisseur annonce « vous avez pris 40 kg ce mois-ci », il n'y a rien a
 * confronter — et cette information ne se reconstitue pas apres coup.
 *
 * Le rattachement se fait au marche (lieu de retrait), pas a la zone de
 * livraison. Un marche dessert une ou plusieurs zones : c'est ce lien qui
 * amene les commandes aux bons preparateurs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('markets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Un marche dessert des zones de livraison ; une zone peut etre servie
        // par plusieurs marches (redondance utile quand un marche ferme).
        Schema::create('delivery_zone_market', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_zone_id')->constrained()->cascadeOnDelete();
            $table->unique(['market_id', 'delivery_zone_id']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('stall')->nullable();       // emplacement dans le marche
            $table->string('phone')->nullable();
            $table->string('speciality')->nullable();  // « poissons », « legumes »...
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // Marche de rattachement du preparateur. Nul pour les autres roles.
            $table->foreignId('market_id')->nullable()->after('role')
                ->constrained()->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('product_id')
                ->constrained()->nullOnDelete();

            // Le client a choisi « remplacer » : le preparateur designe le
            // produit effectivement pris, pris dans le catalogue.
            $table->foreignId('replacement_product_id')->nullable()->after('supplier_id')
                ->constrained('products')->nullOnDelete();

            // pending | collected | replaced | removed
            $table->string('collection_status')->default('pending')->after('substitution_preference');
        });

        // « purchased_at » decrivait un achat paye. Ici le preparateur ne paie
        // pas : il recupere. Une colonne qui ment est pire qu'une colonne
        // absente — le prochain developpeur s'y fierait.
        Schema::table('order_items', function (Blueprint $table) {
            $table->renameColumn('purchased_at', 'collected_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->renameColumn('collected_at', 'purchased_at');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropConstrainedForeignId('replacement_product_id');
            $table->dropColumn('collection_status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('market_id');
        });

        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('delivery_zone_market');
        Schema::dropIfExists('markets');
    }
};

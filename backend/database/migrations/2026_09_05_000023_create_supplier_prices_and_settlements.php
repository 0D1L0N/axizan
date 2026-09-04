<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le point avec les fournisseurs partenaires.
 *
 * Rappel du modele : le preparateur se presente chez le fournisseur, se sert,
 * et ne paie RIEN. Axizan regle ensuite. Cette dette grossissait jusqu'ici
 * sans que personne puisse la chiffrer.
 *
 * Deux tables, et une colonne.
 *
 * `supplier_prices` : le tarif convenu avec chaque partenaire, par article.
 * C'est la reference, pas la verite comptable — voir ci-dessous.
 *
 * `supplier_settlements` : un reglement effectue, avec sa periode, son
 * montant, et qui l'a acte.
 *
 * `order_items.settlement_id` : la ligne rattachee a son reglement. C'est ce
 * qui rend un double paiement impossible : une ligne deja rattachee ne
 * reapparait dans aucun point suivant.
 *
 * Pourquoi le tarif ne suffit pas comme verite comptable : un prix se
 * renegocie. Recalculer un point de la semaine derniere avec le tarif
 * d'aujourd'hui ferait bouger un chiffre deja regle. Le prix est donc
 * ESTAMPILLE sur la ligne au moment du retrait, dans `actual_unit_price` —
 * colonne qui existe deja et attendait cet usage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Prix pour UNE unite de l'article, dans l'unite du produit
            // (le kilo, la piece, le tas). Le XOF n'a pas de subdivision,
            // mais on garde deux decimales par coherence avec les autres
            // montants du schema.
            $table->decimal('unit_price', 12, 2);

            $table->timestamps();

            // Un seul tarif courant par couple : renegocier, c'est mettre a
            // jour cette ligne, pas en empiler une seconde.
            $table->unique(['supplier_id', 'product_id']);
        });

        Schema::create('supplier_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();

            // Periode couverte, telle qu'annoncee au fournisseur. Conservee
            // meme si elle se deduit des lignes : c'est ce qui a ete dit au
            // moment du point, et le fournisseur en a peut-etre une trace.
            $table->date('period_start');
            $table->date('period_end');

            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('item_count');

            // Qui a acte le reglement, et quand. Meme exigence que pour la
            // validation des paiements clients : une somme qui change de mains
            // sans nom en face n'est pas tracable.
            $table->foreignId('settled_by')->constrained('users');
            $table->timestamp('settled_at');

            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['supplier_id', 'settled_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('settlement_id')->nullable()->after('supplier_id')
                ->constrained('supplier_settlements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settlement_id');
        });

        Schema::dropIfExists('supplier_settlements');
        Schema::dropIfExists('supplier_prices');
    }
};

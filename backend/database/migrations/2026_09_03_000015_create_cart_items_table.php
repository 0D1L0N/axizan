<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panier serveur.
 *
 * Le panier ne vivait que dans le localStorage du navigateur. Un client qui
 * compose son panier au bureau et veut commander le soir depuis son telephone
 * repartait de zero — friction reelle pour une cible de menages qui preparent
 * leurs courses a deux.
 *
 * Une ligne par (utilisateur, produit) : ajouter deux fois le meme article
 * augmente la quantite, ca ne cree pas un doublon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // decimal(10,3) comme order_items : on achete au poids autant qu'a
            // la piece, 1,5 kg d'ignames doit tenir.
            $table->decimal('quantity', 10, 3);
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};

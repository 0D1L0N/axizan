<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppression du code et du schema jamais utilises.
 *
 * `promotions`, `promotion_usages` et `reviews` etaient vides depuis le
 * premier jour, sans aucune interface, mais `applyPromotion()` s'executait a
 * chaque commande et `promo_code_id` trainait sur chaque ligne.
 *
 * Du schema qui ment coute plus cher qu'il ne rapporte : le prochain
 * developpeur croit qu'un systeme de promotions existe, cherche ou il est
 * branche, et ne trouve rien. Mieux vaut le recreer proprement le jour ou le
 * besoin arrive.
 *
 * Les enregistrements financiers (payments, orders, order_items,
 * order_events) ne sont evidemment pas touches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'promo_code_id')) {
                $table->dropConstrainedForeignId('promo_code_id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'promo_discount')) {
                $table->dropColumn('promo_discount');
            }
        });

        // L'ordre compte : promotion_usages reference promotions.
        Schema::dropIfExists('promotion_usages');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('reviews');
    }

    /**
     * Volontairement non reversible.
     *
     * Recreer des tables vides ne rendrait service a personne, et les modeles
     * correspondants ont ete supprimes. Le jour ou promotions ou avis
     * reviendront, ils meritent un schema pense pour l'usage reel — pas la
     * resurrection d'une ebauche jamais branchee.
     */
    public function down(): void
    {
        // Rien.
    }
};

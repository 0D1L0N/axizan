<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le compteur cumulatif ne servait plus a rien depuis que la capacite se
 * compte par date (DeliverySlot::bookedCountsOn). Il continuait pourtant
 * d'etre incremente : une colonne qui ment est pire qu'une colonne absente,
 * le prochain developpeur s'y fierait.
 *
 * Rappel du defaut d'origine : jamais remis a zero, il aurait ferme
 * definitivement un creneau apres N commandes, tous jours confondus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_slots', function (Blueprint $table) {
            $table->dropColumn('current_orders_count');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_slots', function (Blueprint $table) {
            $table->unsignedInteger('current_orders_count')->default(0)->after('max_orders_per_slot');
        });
    }
};

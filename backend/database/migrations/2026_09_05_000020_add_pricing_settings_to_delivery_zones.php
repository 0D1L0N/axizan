<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regles tarifaires par zone, reglables par l'administrateur.
 *
 * - min_order_amount / small_order_action : sous ce sous-total, la commande
 *   est soit refusee (block), soit majoree de small_order_fee (apply_fee).
 * - free_delivery_threshold : a partir de ce sous-total, livraison offerte.
 * - additional_fee (creneau) : supplement d'un creneau tres demande.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->decimal('min_order_amount', 12, 2)->default(0)->after('extra_fee_per_km');
            $table->decimal('small_order_fee', 12, 2)->default(0)->after('min_order_amount');
            $table->string('small_order_action', 20)->default('apply_fee')->after('small_order_fee');
            $table->decimal('free_delivery_threshold', 12, 2)->nullable()->after('small_order_action');
        });

        Schema::table('delivery_slots', function (Blueprint $table) {
            $table->decimal('additional_fee', 12, 2)->default(0)->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_slots', function (Blueprint $table) {
            $table->dropColumn('additional_fee');
        });

        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->dropColumn([
                'min_order_amount', 'small_order_fee', 'small_order_action', 'free_delivery_threshold',
            ]);
        });
    }
};

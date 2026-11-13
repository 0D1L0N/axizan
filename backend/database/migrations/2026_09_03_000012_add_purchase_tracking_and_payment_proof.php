<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deux trous qui coutaient de l'argent sans laisser de trace.
 *
 * 1. Le prix reellement paye au marche. Le total d'une commande est fige a la
 *    commande, mais le prix se negocie le lendemain a l'etal et varie d'un
 *    vendeur a l'autre. Sans ces colonnes, Axizan absorbe l'ecart sans le
 *    savoir — et ne peut ni connaitre sa marge, ni negocier ses partenariats
 *    fournisseurs sur des chiffres.
 *
 *    La quantite reelle est stockee separement : au marche, on ne repart pas
 *    avec exactement 1,5 kg — on repart avec le tas du vendeur.
 *
 * 2. La preuve de validation de paiement. Un administrateur validait sans que
 *    rien n'enregistre comment l'argent etait arrive ni qui avait valide :
 *    ingerable en cas de litige.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('actual_quantity', 10, 3)->nullable()->after('quantity_unit');
            $table->decimal('actual_unit_price', 12, 2)->nullable()->after('unit_price');
            $table->decimal('actual_total_price', 12, 2)->nullable()->after('total_price');
            $table->timestamp('purchased_at')->nullable()->after('substitution_preference');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('validated_by')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable()->after('validated_by');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['actual_quantity', 'actual_unit_price', 'actual_total_price', 'purchased_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validated_by');
            $table->dropColumn('validated_at');
        });
    }
};

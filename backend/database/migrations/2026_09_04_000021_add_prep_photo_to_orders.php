<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo du panier au moment du depot.
 *
 * Le client paie d'avance un retrait qu'il ne voit pas faire. Il a deja la
 * preuve de remise du livreur ; celle-ci montre ce qui a ete reuni, avant
 * meme que la livraison commence.
 *
 * Elle protege aussi Axizan : en cas de contestation sur la qualite ou la
 * quantite, il existe une image datee de ce qui est parti du depot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('prep_photo_url')->nullable()->after('ready_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('prep_photo_url');
        });
    }
};

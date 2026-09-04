<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lien de carte colle par le client (Google Maps, Plans iOS, lien court).
 *
 * A Cotonou, beaucoup d'adresses n'ont ni rue ni numero : le repere partage
 * vaut souvent mieux qu'une adresse postale. On garde le lien tel quel a cote
 * des coordonnees, parce qu'un lien partage porte un contexte que latitude et
 * longitude perdent (nom du lieu, epingle posee a la main).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('maps_url', 500)->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('maps_url');
        });
    }
};

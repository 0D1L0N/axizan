<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferences de notification.
 *
 * Un service qui envoie des SMS sans permettre de les couper se fait bloquer
 * par ses propres clients. Les notifications transactionnelles (argent,
 * livraison imminente) restent hors de portee de ces reglages — les couper
 * ferait perdre des commandes payees.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};

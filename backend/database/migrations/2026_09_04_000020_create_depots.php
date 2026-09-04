<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Depots Axizan.
 *
 * Repartition des roles decidee : le preparateur ne fait que preparer, le
 * livreur vient chercher. La remise se fait dans un depot, pas au marche —
 * le preparateur rapporte le panier, le livreur y passe.
 *
 * Consequence sur le parcours : « marquer prete » ne signifie plus « articles
 * reunis » mais « depose au depot ». Sans cette precision, un livreur se
 * deplacerait pour un panier encore au marche. L'intitule du bouton porte
 * cette signification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depots', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address');
            $table->string('city')->nullable();
            $table->string('landmark')->nullable();  // repere, comme pour les adresses clients
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('markets', function (Blueprint $table) {
            // Depot ou deposent les preparateurs de ce marche.
            $table->foreignId('depot_id')->nullable()->after('city')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('depot_id');
        });

        Schema::dropIfExists('depots');
    }
};

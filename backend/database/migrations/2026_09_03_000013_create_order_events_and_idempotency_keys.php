<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1. order_events : qui a fait quoi, quand, pourquoi.
 *
 *    La table orders porte un horodatage par statut (accepted_at, ready_at...)
 *    mais ces colonnes disent QUAND, jamais QUI ni POURQUOI. Quand une
 *    commande tourne mal, la sequence est irreconstituable — et on manipule
 *    l'argent d'autrui.
 *
 * 2. idempotency_keys : POST /orders n'avait aucune protection contre la
 *    repetition. Sur un reseau mobile instable, un client qui ne voit pas la
 *    reponse arriver et retouche le bouton creait une seconde commande, avec
 *    un second paiement a valider et un second panier a acheter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // L'acteur peut etre nul : certaines transitions viennent du
            // systeme (expiration, tache planifiee), pas d'une personne.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role')->nullable();
            $table->string('event');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('reason')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 128);
            $table->string('endpoint', 120);
            // Empreinte de la charge utile : la meme cle avec un contenu
            // different est une erreur d'appel, pas une reprise.
            $table->string('payload_hash', 64);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'endpoint', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('order_events');
    }
};

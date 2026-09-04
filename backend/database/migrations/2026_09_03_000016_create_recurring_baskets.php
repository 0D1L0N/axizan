<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paniers recurrents.
 *
 * La cible — les menages de fonctionnaires — est payee mensuellement et achete
 * en consequence : une grosse commande en debut de mois, des recharges ensuite.
 *
 * Choix important : un panier recurrent ne cree PAS de commande tout seul. Il
 * envoie un rappel a echeance, et le client recharge son panier d'un geste.
 * Commander sans confirmation reviendrait a partir acheter au marche pour
 * quelqu'un qui n'a rien valide — avec des prix qui ont bouge depuis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_baskets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('frequency'); // weekly | biweekly | monthly
            $table->timestamp('next_reminder_at')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'next_reminder_at']);
        });

        Schema::create('recurring_basket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_basket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 10, 3);
            $table->timestamps();

            $table->unique(['recurring_basket_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_basket_items');
        Schema::dropIfExists('recurring_baskets');
    }
};

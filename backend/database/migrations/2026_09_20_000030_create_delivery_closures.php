<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fermer une journee.
 *
 * Les creneaux sont des gabarits : une plage horaire, une capacite, un
 * interrupteur. Rien ne permettait de dire « le 1er aout, on ne livre pas ».
 * Le seul moyen etait de desactiver les creneaux a la main la veille au soir et
 * de penser a les rouvrir le lendemain — ce que personne ne fait un jour ferie.
 *
 * Une fermeture porte sur une DATE, pas sur un creneau : quand un marche ne
 * tient pas, il ne tient a aucune heure. C'est aussi ce qu'on veut poser a
 * l'avance, alors que les gabarits, eux, ne changent pas.
 *
 * `zone_id` nul ferme TOUTE la couverture — un jour ferie national, une greve
 * des transports. Renseigne, il ne ferme qu'une zone : une inondation a Godomey
 * n'empeche pas de livrer a Fidjrosse.
 *
 * Le motif est obligatoire. Une journee fermee sans explication se rouvre par
 * precaution, et on se retrouve a livrer un jour ou il n'y a rien a acheter.
 *
 * Ce que cette table ne fait PAS : toucher aux commandes deja posees. Fermer un
 * jour arrete les nouvelles reservations ; annuler douze commandes payees est
 * une decision d'argent, qui se prend une par une dans la salle de controle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_closures', function (Blueprint $table) {
            $table->id();
            $table->date('closed_on');
            // Nul = toute la couverture. Une zone supprimee emporte ses
            // fermetures : elles n'auraient plus rien a fermer.
            $table->foreignId('zone_id')->nullable()->constrained('delivery_zones')->cascadeOnDelete();
            $table->string('reason', 300);
            // Qui a ferme : une journee fermee par erreur se retrouve.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Deux fois la meme fermeture n'a pas de sens, et le doublon ferait
            // afficher la journee deux fois dans la liste.
            $table->unique(['closed_on', 'zone_id']);
            $table->index('closed_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_closures');
    }
};

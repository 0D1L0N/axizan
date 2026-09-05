<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le client repond a un remplacement, pendant qu'il a encore lieu.
 *
 * Jusqu'ici il indiquait a la commande ce qu'il fallait faire d'un article
 * introuvable — remplacer ou retirer — puis n'apprenait plus rien jusqu'a la
 * livraison. Il decouvrait dans son panier un produit qu'il n'avait pas choisi.
 *
 * LE PARTI PRIS : prevenir et laisser contester, pas demander et attendre.
 *
 * Le preparateur est debout a l'etal, sur un reseau capricieux. L'immobiliser
 * en attendant une reponse — d'un client peut-etre en reunion, telephone au
 * fond d'un sac — couterait plus cher que le probleme qu'on resout. Il decide
 * donc, le client est prevenu dans la seconde, et peut refuser tant que le
 * panier n'a pas quitte le marche.
 *
 * Sans reponse, la decision du preparateur tient. C'est la seule regle qui
 * fonctionne quand l'un des deux n'a pas de reseau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Quand le client a ete prevenu. Sert aussi a n'envoyer qu'UN SMS
            // par commande, meme si trois articles sont remplaces.
            $table->timestamp('substitution_notified_at')->nullable()->after('substitution_preference');

            // null = pas de reponse, et la decision du preparateur tient.
            $table->string('substitution_response')->nullable()->after('substitution_notified_at');
            $table->timestamp('substitution_responded_at')->nullable()->after('substitution_response');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'substitution_notified_at',
                'substitution_response',
                'substitution_responded_at',
            ]);
        });
    }
};

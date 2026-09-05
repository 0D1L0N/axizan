<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les marches reels, apres la fermeture de Dantokpa.
 *
 * FAIT MAJEUR : Dantokpa a ete definitivement ferme le 2 mai 2026. Ses quelque
 * 20 000 commercants ont ete relocalises par categorie entre le 13 avril et le
 * 2 mai, et le site a ete rase. Le marche que l'application envoyait ses
 * preparateurs approvisionner n'existe plus.
 *
 * Les vivriers, legumes, condiments et cereales sont partis au POLE
 * AGROALIMENTAIRE DU GRAND NOKOUE, rebaptise « Tokpa Daho » : 168 hectares a
 * Akassato, commune d'Abomey-Calavi, a 1,5 km de la RNIE 2, avec six magasins
 * secs et deux unites de froid. C'est desormais la base d'approvisionnement
 * naturelle d'Axizan.
 *
 * Sources : Jeune Afrique, La Nation Benin, Matin Libre, Global Voices,
 * gouv.bj (mars-mai 2026).
 *
 * Cette migration ajoute de quoi DOCUMENTER un marche — un preparateur doit
 * savoir ou aller, a quelle heure, et ce qu'on y trouve — puis remplace
 * Dantokpa par les poles en service.
 */
return new class extends Migration
{
    /**
     * Marches en service sur le perimetre de lancement.
     *
     * Les adresses sont celles publiees par la presse et le gouvernement. Les
     * reperes precis (allees, numeros de halle) restent a relever sur place :
     * ces poles ont ouvert il y a quelques mois et aucune source publique ne
     * les detaille.
     */
    private const MARKETS = [
        [
            'name' => 'Tokpa Dahô — Pôle agroalimentaire',
            'city' => 'Abomey-Calavi',
            'address' => 'Akassato, à 1,5 km de la RNIE 2',
            'landmark' => 'Site de 168 ha, six magasins secs et deux unités de froid',
            'opening_hours' => '06:00 - 18:00',
            'notes' => "Marché de gros du Grand Nokoué, ouvert en mai 2026 pour "
                ."accueillir les vivriers de Dantokpa. Céréales, tubercules, oignons, "
                ."haricot, maïs, fruits et légumes, condiments. C'est ici que se fait "
                ."l'essentiel de l'approvisionnement : les prix de gros y sont les "
                ."meilleurs, mais il faut compter le trajet depuis Cotonou.",
            'maps_query' => 'Pôle agroalimentaire Akassato Abomey-Calavi Bénin',
        ],
        [
            'name' => 'Marché Ganhi',
            'city' => 'Cotonou',
            'address' => 'Quartier Ganhi, centre de Cotonou',
            'landmark' => 'Marché surélevé, à proximité du boulevard Saint-Michel',
            'opening_hours' => '07:00 - 19:00',
            'notes' => "Ouvert en décembre 2024. Marché de détail au cœur de Cotonou : "
                ."fruits et légumes du jour. Utile pour un complément rapide ou une "
                ."commande urgente, quand faire Akassato n'a pas de sens.",
            'maps_query' => 'Marché Ganhi Cotonou Bénin',
        ],
        [
            'name' => 'Marché Midombo',
            'city' => 'Cotonou',
            'address' => 'Akpakpa est',
            'landmark' => 'Zone est d’Akpakpa',
            'opening_hours' => '07:00 - 19:00',
            'notes' => "Ouvert en novembre 2024. Dessert l'est de Cotonou. "
                ."Pratique pour les livraisons d'Akpakpa et de la zone portuaire, "
                ."sans traverser la ville.",
            'maps_query' => 'Marché Midombo Akpakpa Cotonou Bénin',
        ],
        [
            'name' => 'Marché de Cococodji',
            'city' => 'Abomey-Calavi',
            'address' => 'Cococodji',
            'landmark' => 'Marché moderne, inauguré en janvier 2026',
            'opening_hours' => '07:00 - 19:00',
            'notes' => "Marché de détail moderne. Dessert Godomey et l'entrée sud "
                ."d'Abomey-Calavi, le secteur le plus dense de la commune.",
            'maps_query' => 'Marché moderne Cococodji Abomey-Calavi Bénin',
        ],
    ];

    /** Le marche qui n'existe plus. */
    private const CLOSED = 'Dantokpa';

    public function up(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            // Un preparateur ne connait pas forcement le marche ou on l'envoie :
            // sans adresse ni horaires, il perd sa matinee.
            $table->string('address')->nullable()->after('city');
            $table->string('landmark')->nullable()->after('address');
            $table->string('opening_hours')->nullable()->after('landmark');
            $table->text('notes')->nullable()->after('opening_hours');
            // Requete Maps plutot qu'une URL figee : les coordonnees de ces
            // poles neufs ne sont pas encore stables dans les cartes.
            $table->string('maps_query')->nullable()->after('notes');
        });

        if (DB::table('markets')->count() === 0) {
            return;
        }

        foreach (self::MARKETS as $market) {
            $existing = DB::table('markets')->where('name', $market['name'])->first();

            if ($existing) {
                DB::table('markets')->where('id', $existing->id)
                    ->update($market + ['is_active' => true, 'updated_at' => now()]);
                continue;
            }

            DB::table('markets')->insert($market + [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        /*
         * Dantokpa passe inactif — jamais supprime.
         *
         * Des preparateurs y sont rattaches (`users.market_id`), des
         * fournisseurs y sont enregistres, et des lignes de commande deja
         * reglees pointent sur ces etals. Effacer le marche trouerait le point
         * fournisseur de tout ce qui a ete achete avant mai 2026.
         */
        DB::table('markets')->where('name', self::CLOSED)->update([
            'is_active' => false,
            'notes' => 'Fermé définitivement le 2 mai 2026. Site rasé, commerçants '
                .'relocalisés par catégorie. Conservé pour l’historique des achats.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->dropColumn(['address', 'landmark', 'opening_hours', 'notes', 'maps_query']);
        });
    }
};

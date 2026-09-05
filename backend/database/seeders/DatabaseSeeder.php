<?php

namespace Database\Seeders;

use App\Models\DeliverySlot;
use App\Models\DeliveryZone;
use App\Models\Depot;
use App\Models\Market;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Catalogue (catégories + produits du marché béninois)
        $this->call(CatalogSeeder::class);

        // Comptes de démo et données logistiques : jamais en production.
        if (app()->environment(['local', 'testing'])) {
            $this->seedLogistics();
            $this->seedMarketsAndSuppliers();
            $this->seedDemoAccounts();
        }
    }

    /**
     * Marchés de retrait et fournisseurs partenaires.
     *
     * Le préparateur ne paie rien sur place : il se sert chez ces partenaires
     * et le règlement se fait ensuite entre Axizán et chacun d'eux.
     */
    private function seedMarketsAndSuppliers(): void
    {
        /*
         * Les marches REELS, apres la fermeture de Dantokpa le 2 mai 2026.
         *
         * Les vivriers du Grand Nokoue sont partis a Akassato, dans le pole
         * agroalimentaire rebaptise « Tokpa Daho ». C'est desormais la base
         * d'approvisionnement principale : 168 hectares, six magasins secs,
         * deux unites de froid.
         *
         * Les etals ci-dessous sont des EMPLACEMENTS A RENSEIGNER : ces poles
         * ont ouvert il y a quelques mois et aucune source publique ne donne
         * les noms ni les numeros de halle. Ils se relevent sur place, lors du
         * premier passage d'un preparateur.
         *
         * `covers` liste les categories reellement servies par l'etal : c'est
         * ce qui route le preparateur. L'ORDRE des etals fait l'ordre de
         * parcours du marche — il le traverse une fois, sans revenir sur ses pas.
         */
        $markets = [
            [
                'name' => 'Tokpa Dahô — Pôle agroalimentaire',
                'city' => 'Abomey-Calavi',
                'address' => 'Akassato, à 1,5 km de la RNIE 2',
                'landmark' => 'Site de 168 ha, six magasins secs et deux unités de froid',
                'opening_hours' => '06:00 - 18:00',
                'notes' => 'Marché de gros du Grand Nokoué, ouvert en mai 2026 pour accueillir '
                    .'les vivriers de Dantokpa. Céréales, tubercules, oignons, haricot, maïs, '
                    .'fruits et légumes, condiments. Les meilleurs prix de gros, mais il faut '
                    .'compter le trajet depuis Cotonou.',
                'maps_query' => 'Pôle agroalimentaire Akassato Abomey-Calavi Bénin',
                // Le pole dessert les deux communes : c'est un marche de gros,
                // pas un marche de quartier.
                'zones' => ['Cotonou', 'Abomey-Calavi'],
                'suppliers' => [
                    ['name' => 'Grossiste légumes et condiments', 'stall' => 'Halle à renseigner',
                        'speciality' => 'Légumes et condiments',
                        'covers' => ['Légumes du jour', 'Condiments & épices']],
                    ['name' => 'Grossiste tubercules', 'stall' => 'Halle à renseigner',
                        'speciality' => 'Tubercules',
                        'covers' => ['Tubercules & plantain']],
                    ['name' => 'Grossiste céréales et légumineuses', 'stall' => 'Magasin sec à renseigner',
                        'speciality' => 'Céréales et légumineuses',
                        'covers' => ['Céréales & légumineuses']],
                    ['name' => 'Grossiste poissons', 'stall' => 'Unité de froid à renseigner',
                        'speciality' => 'Poissons et fruits de mer',
                        'covers' => ['Poissons & fruits de mer']],
                    ['name' => 'Grossiste viandes et œufs', 'stall' => 'Halle à renseigner',
                        'speciality' => 'Viandes et œufs',
                        'covers' => ['Viandes & œufs']],
                    ['name' => 'Grossiste huiles et pâtes', 'stall' => 'Magasin sec à renseigner',
                        'speciality' => 'Huiles et pâtes',
                        'covers' => ['Huiles & pâtes']],
                    ['name' => 'Grossiste fruits', 'stall' => 'Halle à renseigner',
                        'speciality' => 'Fruits de saison',
                        'covers' => ['Fruits']],
                    ['name' => 'Grossiste épicerie et boissons', 'stall' => 'Magasin sec à renseigner',
                        'speciality' => 'Épicerie et boissons',
                        'covers' => ['Épicerie & conserves', 'Boissons locales']],
                ],
            ],
            [
                'name' => 'Marché Ganhi',
                'city' => 'Cotonou',
                'address' => 'Quartier Ganhi, centre de Cotonou',
                'landmark' => 'Marché surélevé, près du boulevard Saint-Michel',
                'opening_hours' => '07:00 - 19:00',
                'notes' => 'Ouvert en décembre 2024. Détail au cœur de Cotonou : fruits et '
                    .'légumes du jour. Pour un complément rapide ou une commande urgente, '
                    .'quand faire Akassato n’a pas de sens.',
                'maps_query' => 'Marché Ganhi Cotonou Bénin',
                'zones' => ['Cotonou'],
                'suppliers' => [
                    ['name' => 'Étal légumes Ganhi', 'stall' => 'Allée à renseigner',
                        'speciality' => 'Légumes et condiments',
                        'covers' => ['Légumes du jour', 'Condiments & épices']],
                    ['name' => 'Étal fruits Ganhi', 'stall' => 'Allée à renseigner',
                        'speciality' => 'Fruits de saison',
                        'covers' => ['Fruits']],
                ],
            ],
            [
                'name' => 'Marché Midombo',
                'city' => 'Cotonou',
                'address' => 'Akpakpa est',
                'landmark' => 'Zone est d’Akpakpa',
                'opening_hours' => '07:00 - 19:00',
                'notes' => 'Ouvert en novembre 2024. Dessert l’est de Cotonou : pratique pour '
                    .'les livraisons d’Akpakpa et de la zone portuaire, sans traverser la ville.',
                'maps_query' => 'Marché Midombo Akpakpa Cotonou Bénin',
                'zones' => ['Cotonou'],
                'suppliers' => [
                    ['name' => 'Étal légumes Midombo', 'stall' => 'Allée à renseigner',
                        'speciality' => 'Légumes et condiments',
                        'covers' => ['Légumes du jour', 'Condiments & épices']],
                    ['name' => 'Poissonnerie Midombo', 'stall' => 'Halle à renseigner',
                        'speciality' => 'Poissons et fruits de mer',
                        'covers' => ['Poissons & fruits de mer']],
                ],
            ],
            [
                'name' => 'Marché de Cococodji',
                'city' => 'Abomey-Calavi',
                'address' => 'Cococodji',
                'landmark' => 'Marché moderne, inauguré en janvier 2026',
                'opening_hours' => '07:00 - 19:00',
                'notes' => 'Détail moderne. Dessert Godomey et l’entrée sud d’Abomey-Calavi, '
                    .'le secteur le plus dense de la commune.',
                'maps_query' => 'Marché moderne Cococodji Abomey-Calavi Bénin',
                'zones' => ['Abomey-Calavi'],
                'suppliers' => [
                    ['name' => 'Étal légumes Cococodji', 'stall' => 'Allée à renseigner',
                        'speciality' => 'Légumes et condiments',
                        'covers' => ['Légumes du jour', 'Condiments & épices']],
                    ['name' => 'Étal tubercules Cococodji', 'stall' => 'Allée à renseigner',
                        'speciality' => 'Tubercules',
                        'covers' => ['Tubercules & plantain']],
                ],
            ],
        ];

        // Un dépôt par ville : le préparateur y rapporte le panier, le livreur
        // vient l'y chercher. C'est le point de remise entre les deux rôles.
        $depots = [
            'Cotonou' => [
                'name' => 'Dépôt Cotonou',
                'address' => 'Rue 12.34, Akpakpa',
                'landmark' => 'Face à la station Oryx',
                'phone' => '+22961000010',
            ],
            'Abomey-Calavi' => [
                'name' => 'Dépôt Abomey-Calavi',
                'address' => 'Godomey, entrée sud de la commune',
                'landmark' => 'Adresse exacte à relever sur place',
                'phone' => '+22961000011',
            ],
        ];

        foreach ($markets as $data) {
            $depotData = $depots[$data['city']] ?? null;
            $depot = $depotData
                ? Depot::updateOrCreate(
                    ['name' => $depotData['name']],
                    $depotData + ['city' => $data['city'], 'is_active' => true]
                )
                : null;

            $market = Market::updateOrCreate(
                ['name' => $data['name']],
                ['city' => $data['city'], 'depot_id' => $depot?->id, 'is_active' => true]
            );

            $zoneIds = DeliveryZone::whereIn('name', $data['zones'])->pluck('id');
            $market->zones()->sync($zoneIds);

            foreach ($data['suppliers'] as $supplier) {
                $covers = $supplier['covers'] ?? [];
                unset($supplier['covers']);

                $record = Supplier::updateOrCreate(
                    ['market_id' => $market->id, 'name' => $supplier['name']],
                    $supplier + ['is_active' => true]
                );

                // Ce rattachement est ce qui route le preparateur vers le bon
                // etal : sans lui, il recoit une liste sans suggestion et dans
                // un ordre arbitraire.
                $record->categories()->sync(
                    \App\Models\Category::whereIn('name', $covers)->pluck('id')
                );
            }
        }
    }

    /**
     * Comptes de démonstration pour chaque rôle (local/testing uniquement)
     */
    private function seedDemoAccounts(): void
    {
        $accounts = [
            ['email' => 'client@test.com', 'name' => 'Client Démo', 'phone' => '+22961000001', 'role' => 'client'],
            ['email' => 'preparateur@test.com', 'name' => 'Préparateur Démo', 'phone' => '+22961000002', 'role' => 'preparateur', 'market' => 'Tokpa Dahô — Pôle agroalimentaire'],
            ['email' => 'livreur@test.com', 'name' => 'Livreur Démo', 'phone' => '+22961000003', 'role' => 'livreur'],
            ['email' => 'admin@test.com', 'name' => 'Admin Démo', 'phone' => '+22961000004', 'role' => 'admin'],
        ];

        foreach ($accounts as $account) {
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'phone' => $account['phone'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('password123'),
                    'role' => $account['role'],
                    'status' => 'active',
                    'remember_token' => bin2hex(random_bytes(10)),
                    // Le preparateur est rattache a un marche : sans cela il
                    // ne voit aucune commande.
                    'market_id' => isset($account['market'])
                        ? Market::where('name', $account['market'])->value('id')
                        : null,
                ]
            );

            /*
             * Rattachement rejoue a chaque passage, pour deux raisons.
             *
             * `firstOrCreate` n'applique rien aux comptes deja existants : un
             * rattachement ajoute apres coup ne serait jamais pose.
             *
             * Et un preparateur rattache a un marche DESACTIVE est aussi
             * inutilisable qu'un preparateur sans marche : il ouvre son ecran
             * et ne voit aucune commande. C'est arrive avec la fermeture de
             * Dantokpa — le compte pointait sur un marche rase.
             */
            if (isset($account['market'])) {
                $current = $user->market;

                if (! $current || ! $current->is_active) {
                    $user->update([
                        'market_id' => Market::where('name', $account['market'])
                            ->where('is_active', true)
                            ->value('id'),
                    ]);
                }
            }
        }
    }

    /**
     * Zones et créneaux de livraison — Littoral et Atlantique.
     *
     * La couverture épouse deux départements entiers :
     *
     *   Littoral   : Cotonou (commune unique du département)
     *   Atlantique : Abomey-Calavi, Allada, Kpomassè, Ouidah, Sô-Ava, Toffo,
     *                Tori-Bossito, Zè
     *
     * Une zone par commune, parce que c'est le découpage que les clients
     * connaissent et savent nommer — et parce que les frais suivent la
     * distance au dépôt, pas une moyenne départementale.
     *
     * ATTENTION : les montants ci-dessous sont des ordres de grandeur calés
     * sur l'éloignement, pas des tarifs négociés. Ils doivent être révisés
     * depuis l'administration avant l'ouverture réelle de chaque commune.
     */
    private function seedLogistics(): void
    {
        $zones = [
            // Littoral — le dépôt y est.
            ['name' => 'Cotonou', 'base_delivery_fee' => 800],

            // Atlantique, du plus proche au plus éloigné de Cotonou.
            ['name' => 'Abomey-Calavi', 'base_delivery_fee' => 1000],
            ['name' => 'Sô-Ava', 'base_delivery_fee' => 1500],
            ['name' => 'Ouidah', 'base_delivery_fee' => 1800],
            ['name' => 'Tori-Bossito', 'base_delivery_fee' => 1800],
            ['name' => 'Kpomassè', 'base_delivery_fee' => 2000],
            ['name' => 'Allada', 'base_delivery_fee' => 2000],
            ['name' => 'Toffo', 'base_delivery_fee' => 2500],
            ['name' => 'Zè', 'base_delivery_fee' => 2500],
        ];

        foreach ($zones as $zoneData) {
            $zone = DeliveryZone::updateOrCreate(
                ['name' => $zoneData['name']],
                $zoneData + ['is_active' => true]
            );

            // Creneaux d'une heure couvrant les horaires des marches beninois
            // (8h-19h). Ce qui limite le choix du client n'est pas cette grille
            // mais le delai minimum d'une heure, applique a la volee selon
            // l'heure de la commande (voir DeliverySlot::isBookableOn).
            $slots = [];
            $cursor = Carbon::createFromTimeString(DeliverySlot::MARKET_OPENS_AT);
            $closing = Carbon::createFromTimeString(DeliverySlot::MARKET_CLOSES_AT);

            while ($cursor->lt($closing)) {
                $end = $cursor->copy()->addHour();
                $slots[] = [
                    'time_start' => $cursor->format('H:i'),
                    'time_end' => $end->format('H:i'),
                    'display_label' => $cursor->format('H:i').' - '.$end->format('H:i'),
                ];
                $cursor = $end;
            }

            foreach ($slots as $slotData) {
                DeliverySlot::updateOrCreate(
                    ['zone_id' => $zone->id, 'display_label' => $slotData['display_label']],
                    $slotData + ['zone_id' => $zone->id, 'is_active' => true, 'max_orders_per_slot' => 20]
                );
            }

            // Les anciens creneaux sont desactives, jamais supprimes : des commandes
            // passees y font encore reference.
            DeliverySlot::where('zone_id', $zone->id)
                ->whereNotIn('display_label', array_column($slots, 'display_label'))
                ->update(['is_active' => false]);
        }
    }
}

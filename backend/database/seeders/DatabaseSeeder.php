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
        $markets = [
            [
                'name' => 'Dantokpa',
                'city' => 'Cotonou',
                'zones' => ['Cotonou Centre'],
                /*
                 * `covers` liste les categories reellement servies par l'etal :
                 * c'est ce qui route le preparateur. Une vendeuse de legumes
                 * propose presque toujours les condiments qui vont avec, d'ou
                 * plusieurs categories par etal.
                 *
                 * L'ORDRE des etals fait l'ordre de parcours du marche : le
                 * preparateur le traverse une fois, sans revenir sur ses pas.
                 */
                'suppliers' => [
                    ['name' => 'Étal Aïssatou', 'stall' => 'Allée B12', 'speciality' => 'Légumes et condiments',
                        'covers' => ['Légumes du jour', 'Condiments & épices']],
                    ['name' => 'Chez Rachidi', 'stall' => 'Allée C04', 'speciality' => 'Tubercules',
                        'covers' => ['Tubercules & plantain']],
                    ['name' => 'Grains du Nord', 'stall' => 'Allée A21', 'speciality' => 'Céréales et légumineuses',
                        'covers' => ['Céréales & légumineuses']],
                    ['name' => 'Poissonnerie Codjo', 'stall' => 'Halle poissons', 'speciality' => 'Poissons et fruits de mer',
                        'covers' => ['Poissons & fruits de mer']],
                    ['name' => 'Boucherie Zinsou', 'stall' => 'Halle viandes', 'speciality' => 'Viandes et œufs',
                        'covers' => ['Viandes & œufs']],
                    ['name' => 'Huilerie Sèdjro', 'stall' => 'Allée D07', 'speciality' => 'Huiles et pâtes',
                        'covers' => ['Huiles & pâtes']],
                    ['name' => 'Fruits Dossou', 'stall' => 'Allée E02', 'speciality' => 'Fruits de saison',
                        'covers' => ['Fruits']],
                    ['name' => 'Boutique Mensah', 'stall' => 'Entrée nord', 'speciality' => 'Épicerie et boissons',
                        'covers' => ['Épicerie & conserves', 'Boissons locales']],
                ],
            ],
            [
                'name' => 'Marché Ouando',
                'city' => 'Porto-Novo',
                'zones' => ['Porto-Novo'],
                'suppliers' => [
                    ['name' => 'Étal Bernadette', 'stall' => 'Rangée 3', 'speciality' => 'Légumes et tubercules',
                        'covers' => ['Légumes du jour', 'Tubercules & plantain']],
                    ['name' => 'Fruits Adjovi', 'stall' => 'Rangée 1', 'speciality' => 'Fruits de saison',
                        'covers' => ['Fruits']],
                    ['name' => 'Épices Kpogli', 'stall' => 'Rangée 7', 'speciality' => 'Condiments et épices',
                        'covers' => ['Condiments & épices']],
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
            'Porto-Novo' => [
                'name' => 'Dépôt Porto-Novo',
                'address' => 'Avenue Liberté, Djègan-Daho',
                'landmark' => 'À côté du marché Ouando',
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
            ['email' => 'preparateur@test.com', 'name' => 'Préparateur Démo', 'phone' => '+22961000002', 'role' => 'preparateur', 'market' => 'Dantokpa'],
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

            // firstOrCreate n'applique rien aux comptes déjà existants : un
            // rattachement ajouté après coup ne serait jamais posé, et le
            // préparateur resterait sans marché sans qu'on comprenne pourquoi.
            if (isset($account['market']) && ! $user->market_id) {
                $user->update(['market_id' => Market::where('name', $account['market'])->value('id')]);
            }
        }
    }

    /**
     * Zones et créneaux de livraison pour Cotonou / Porto-Novo
     */
    private function seedLogistics(): void
    {
        $zones = [
            ['name' => 'Cotonou Centre', 'base_delivery_fee' => 800],
            ['name' => 'Porto-Novo', 'base_delivery_fee' => 1200],
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

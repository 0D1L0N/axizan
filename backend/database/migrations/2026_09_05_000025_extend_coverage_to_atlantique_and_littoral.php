<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La couverture passe a deux departements entiers.
 *
 *   Littoral   : Cotonou (commune unique du departement)
 *   Atlantique : Abomey-Calavi, Allada, Kpomasse, Ouidah, So-Ava, Toffo,
 *                Tori-Bossito, Ze
 *
 * Porto-Novo SORT de la couverture : la commune appartient au departement de
 * l'Oueme, pas a l'Atlantique.
 *
 * Rien n'est supprime. Des adresses, des commandes et des factures pointent sur
 * la zone de Porto-Novo et sur le marche Ouando ; les effacer trouerait
 * l'historique. Ils sont DESACTIVES : ils disparaissent des choix offerts aux
 * nouveaux clients, et tout ce qui existe reste lisible.
 *
 * Les frais ci-dessous sont des ordres de grandeur cales sur l'eloignement du
 * depot, pas des tarifs negocies. Ils se revisent depuis l'administration.
 */
return new class extends Migration
{
    private const ZONES = [
        ['name' => 'Cotonou', 'fee' => 800],
        ['name' => 'Abomey-Calavi', 'fee' => 1000],
        ['name' => 'Sô-Ava', 'fee' => 1500],
        ['name' => 'Ouidah', 'fee' => 1800],
        ['name' => 'Tori-Bossito', 'fee' => 1800],
        ['name' => 'Kpomassè', 'fee' => 2000],
        ['name' => 'Allada', 'fee' => 2000],
        ['name' => 'Toffo', 'fee' => 2500],
        ['name' => 'Zè', 'fee' => 2500],
    ];

    /** Ce qui quitte la couverture, sans etre efface. */
    private const RETIRED_ZONES = ['Porto-Novo'];

    private const RETIRED_MARKETS = ['Marché Ouando'];

    public function up(): void
    {
        // Base fraiche : le seeder fait foi, rien a reprendre.
        if (DB::table('delivery_zones')->count() === 0) {
            return;
        }

        // « Cotonou Centre » devient « Cotonou » : la zone couvre desormais la
        // commune entiere, pas un centre-ville dont personne n'a jamais defini
        // les limites. Renommer plutot que recreer preserve les adresses et les
        // creneaux deja rattaches.
        DB::table('delivery_zones')->where('name', 'Cotonou Centre')->update(['name' => 'Cotonou']);

        foreach (self::ZONES as $zone) {
            $existing = DB::table('delivery_zones')->where('name', $zone['name'])->first();

            if ($existing) {
                // Un tarif deja ajuste par l'administration ne doit pas etre
                // ecrase par une valeur indicative de migration.
                DB::table('delivery_zones')->where('id', $existing->id)->update(['is_active' => true]);
                continue;
            }

            $id = DB::table('delivery_zones')->insertGetId([
                'name' => $zone['name'],
                'base_delivery_fee' => $zone['fee'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->seedSlotsFor($id);
        }

        DB::table('delivery_zones')->whereIn('name', self::RETIRED_ZONES)->update(['is_active' => false]);
        DB::table('markets')->whereIn('name', self::RETIRED_MARKETS)->update(['is_active' => false]);

        // Les creneaux d'une zone retiree ne doivent plus etre reservables :
        // desactiver la zone ne suffit pas, l'API des creneaux interroge
        // `delivery_slots.is_active`.
        $retiredZoneIds = DB::table('delivery_zones')->whereIn('name', self::RETIRED_ZONES)->pluck('id');

        if ($retiredZoneIds->isNotEmpty()) {
            DB::table('delivery_slots')->whereIn('zone_id', $retiredZoneIds)->update(['is_active' => false]);
        }
    }

    /**
     * Creneaux d'une heure sur les horaires de marche, comme pour les zones
     * existantes. Une zone sans creneau serait choisissable mais jamais
     * livrable.
     */
    private function seedSlotsFor(int $zoneId): void
    {
        $cursor = \Illuminate\Support\Carbon::createFromTimeString(\App\Models\DeliverySlot::MARKET_OPENS_AT);
        $closing = \Illuminate\Support\Carbon::createFromTimeString(\App\Models\DeliverySlot::MARKET_CLOSES_AT);

        $rows = [];

        while ($cursor->lt($closing)) {
            $end = $cursor->copy()->addHour();

            $rows[] = [
                'zone_id' => $zoneId,
                'time_start' => $cursor->format('H:i'),
                'time_end' => $end->format('H:i'),
                'display_label' => $cursor->format('H:i').' - '.$end->format('H:i'),
                'max_orders_per_slot' => 20,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $cursor = $end;
        }

        DB::table('delivery_slots')->insert($rows);
    }

    /**
     * Volontairement non reversible.
     *
     * Reactiver Porto-Novo sans savoir si l'exploitation y est reellement
     * repartie ferait reapparaitre une commune qu'on ne livre pas, et
     * supprimer les huit communes de l'Atlantique detruirait les adresses que
     * des clients y auront enregistrees entre-temps.
     */
    public function down(): void
    {
        // Rien.
    }
};

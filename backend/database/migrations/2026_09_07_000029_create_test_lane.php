<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Une voie d'essai : eprouver le parcours complet sans faire circuler d'argent.
 *
 * Les cles FedaPay sont des cles LIVE. Le moindre passage de commande debite un
 * vrai compte Mobile Money — et une commande a 1 franc n'existe pas : les frais
 * de livraison portent le total a 801 francs a Cotonou.
 *
 * D'ou ce couple :
 *
 *   - un article a 0 franc,
 *   - une zone de livraison a 0 franc.
 *
 * Total nul, donc paiement acquitte d'office (voir OrderService::createOrder) :
 * la commande part en preparation immediatement, et tout le reste du parcours
 * — retrait, remplacement, depot, livraison, facture — se deroule normalement.
 *
 * La zone est nommee sans ambiguite : elle apparait dans le choix des zones au
 * checkout, et personne ne doit la prendre pour une commune desservie.
 */
return new class extends Migration
{
    private const ZONE = 'ZZZ — Zone de test (usage interne)';

    private const PRODUCT = 'ZZZ — Article de test Axizán (ne pas commander)';

    public function up(): void
    {
        if (DB::table('delivery_zones')->count() === 0) {
            return;
        }

        $zoneId = DB::table('delivery_zones')->where('name', self::ZONE)->value('id');

        if (! $zoneId) {
            $zoneId = DB::table('delivery_zones')->insertGetId([
                'name' => self::ZONE,
                'base_delivery_fee' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Sans creneau, la zone serait choisissable mais jamais livrable.
            $this->seedSlotsFor($zoneId);
        }

        // Le marche de gros dessert la zone d'essai : sans rattachement, aucun
        // preparateur ne verrait la commande de test.
        $marketId = DB::table('markets')
            ->where('name', 'like', 'Tokpa Dahô%')
            ->where('is_active', true)
            ->value('id');

        if ($marketId) {
            DB::table('delivery_zone_market')->insertOrIgnore([
                'delivery_zone_id' => $zoneId,
                'market_id' => $marketId,
            ]);
        }

        DB::table('products')->where('name', self::PRODUCT)->update([
            'base_price' => 0,
            'updated_at' => now(),
        ]);
    }

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
                'max_orders_per_slot' => 99,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $cursor = $end;
        }

        DB::table('delivery_slots')->insert($rows);
    }

    /**
     * Retirer la voie d'essai desactive la zone plutot que de la supprimer :
     * des commandes de test y pointent, et leur effacement casserait les
     * releves fournisseurs qui les comptent.
     */
    public function down(): void
    {
        DB::table('delivery_zones')->where('name', self::ZONE)->update(['is_active' => false]);
    }
};

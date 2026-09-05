<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perimetre de lancement : Cotonou et Abomey-Calavi.
 *
 * La couverture cible reste le Littoral et l'Atlantique. Mais ouvrir neuf
 * communes le premier jour signifierait promettre des livraisons a Ze ou Toffo
 * sans preparateur sur place ni tarif negocie — la meilleure facon de rater
 * les premieres commandes, celles dont on parle.
 *
 * Les sept autres communes ne sont pas supprimees : elles restent en base avec
 * leurs creneaux, desactivees. Les rouvrir sera un interrupteur, pas une
 * migration.
 */
return new class extends Migration
{
    private const LAUNCHING = ['Cotonou', 'Abomey-Calavi'];

    /** Communes de l'Atlantique gardees pour plus tard. */
    private const DEFERRED = [
        'Sô-Ava', 'Ouidah', 'Tori-Bossito', 'Kpomassè', 'Allada', 'Toffo', 'Zè',
    ];

    /** Marches qui ne desservent que des communes differees. */
    private const DEFERRED_MARKETS = ['Marché de Ouidah', 'Marché d’Allada'];

    public function up(): void
    {
        if (DB::table('delivery_zones')->count() === 0) {
            return;
        }

        DB::table('delivery_zones')->whereIn('name', self::LAUNCHING)->update(['is_active' => true]);
        DB::table('delivery_zones')->whereIn('name', self::DEFERRED)->update(['is_active' => false]);

        // Les creneaux suivent la zone : desactiver la zone seule laisserait
        // ses creneaux reservables par appel direct a l'API.
        $deferredZoneIds = DB::table('delivery_zones')->whereIn('name', self::DEFERRED)->pluck('id');

        if ($deferredZoneIds->isNotEmpty()) {
            DB::table('delivery_slots')->whereIn('zone_id', $deferredZoneIds)->update(['is_active' => false]);
        }

        $launchingZoneIds = DB::table('delivery_zones')->whereIn('name', self::LAUNCHING)->pluck('id');

        if ($launchingZoneIds->isNotEmpty()) {
            DB::table('delivery_slots')->whereIn('zone_id', $launchingZoneIds)->update(['is_active' => true]);
        }

        DB::table('markets')->whereIn('name', self::DEFERRED_MARKETS)->update(['is_active' => false]);
    }

    /**
     * Volontairement non reversible.
     *
     * Rouvrir sept communes se decide sur le terrain — quand un preparateur y
     * est rattache et qu'un tarif y est negocie — pas en annulant une
     * migration.
     */
    public function down(): void
    {
        // Rien.
    }
};

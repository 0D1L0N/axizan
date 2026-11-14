<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Une journee ou l'on ne livre pas.
 *
 * `zone_id` nul ferme toute la couverture ; renseigne, il ne ferme qu'une zone.
 * Une fermeture generale l'emporte toujours : si le pays est ferie, aucune zone
 * ne livre, meme sans fermeture propre.
 */
class DeliveryClosure extends Model
{
    use HasFactory;

    protected $fillable = [
        'closed_on',
        'zone_id',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'closed_on' => 'date',
    ];

    public function zone()
    {
        return $this->belongsTo(DeliveryZone::class, 'zone_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Livre-t-on ce jour-la, dans cette zone ?
     *
     * Une seule requete pour les deux cas — fermeture generale ou fermeture de
     * la zone — parce que la question se pose a chaque affichage de creneaux et
     * a chaque commande.
     */
    public static function closes($date, $zoneId = null): bool
    {
        return self::query()
            ->whereDate('closed_on', Carbon::parse($date)->toDateString())
            ->where(function ($sub) use ($zoneId) {
                $sub->whereNull('zone_id');

                if ($zoneId) {
                    $sub->orWhere('zone_id', $zoneId);
                }
            })
            ->exists();
    }

    /**
     * Le motif a afficher, si la journee est fermee.
     *
     * La fermeture generale passe en premier : c'est elle qui explique le mieux
     * pourquoi rien ne bouge nulle part.
     */
    public static function reasonFor($date, $zoneId = null): ?string
    {
        return self::query()
            ->whereDate('closed_on', Carbon::parse($date)->toDateString())
            ->where(function ($sub) use ($zoneId) {
                $sub->whereNull('zone_id');

                if ($zoneId) {
                    $sub->orWhere('zone_id', $zoneId);
                }
            })
            ->orderByRaw('CASE WHEN zone_id IS NULL THEN 0 ELSE 1 END')
            ->value('reason');
    }
}

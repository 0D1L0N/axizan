<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliverySlot extends Model
{
    use HasFactory;

    /**
     * Delai minimum entre la commande et le DEBUT du creneau choisi.
     *
     * Le preparateur doit avoir le temps d'aller au marche, d'acheter et de
     * remettre au livreur. Commande passee a 10h00 : le creneau 11h-12h est le
     * premier acceptable. A 10h01, il ne l'est plus — on ne promet pas ce qu'on
     * ne peut pas tenir.
     */
    public const LEAD_TIME_MINUTES = 60;

    /** Horaires des marches beninois : rien a acheter en dehors. */
    public const MARKET_OPENS_AT = '08:00';
    public const MARKET_CLOSES_AT = '19:00';

    /** On n'ouvre pas la reservation indefiniment : les prix bougent. */
    public const BOOKING_HORIZON_DAYS = 7;

    protected $fillable = [
        'zone_id',
        'day_of_week',
        'time_start',
        'time_end',
        'display_label',
        'max_orders_per_slot',
        'is_active',
        'priority',
        'additional_fee',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'additional_fee' => 'decimal:2',
    ];

    public function zone()
    {
        return $this->belongsTo(DeliveryZone::class, 'zone_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Places restantes a une date donnee.
     *
     * Il n'existe volontairement plus de version « sans date » : la capacite
     * d'un creneau n'a de sens que rapportee a un jour.
     */
    public function availableSpotsOn($date): int
    {
        return max(0, $this->max_orders_per_slot - $this->bookedCountOn($date));
    }

    // ---------------------------------------------------------------------
    // Disponibilite datee
    //
    // Un creneau n'est pas disponible dans l'absolu : il l'est *pour une date*.
    // Le compteur current_orders_count est cumulatif et ne se remet jamais a
    // zero — s'y fier reviendrait a fermer definitivement un creneau apres N
    // commandes. La capacite se compte donc sur les commandes du jour vise.
    // ---------------------------------------------------------------------

    /**
     * Instant de debut de ce creneau a la date donnee, en heure locale.
     */
    public function startsOn($date): Carbon
    {
        return Carbon::parse($date)->startOfDay()->setTimeFromTimeString($this->time_start);
    }

    /**
     * Le creneau respecte-t-il le delai minimum a cette date ?
     */
    public function isBookableOn($date, ?Carbon $now = null): bool
    {
        $now = ($now ?: Carbon::now())->copy();

        return $this->startsOn($date)->gte($now->addMinutes(self::LEAD_TIME_MINUTES));
    }

    /**
     * Nombre de commandes non annulees deja placees sur ce creneau, ce jour-la.
     */
    public function bookedCountOn($date): int
    {
        return $this->orders()
            ->whereDate('delivery_date', Carbon::parse($date)->toDateString())
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->count();
    }

    /**
     * Comptage groupe pour une date : evite une requete par creneau.
     *
     * @return array<int, int> [delivery_slot_id => nombre de commandes]
     */
    public static function bookedCountsOn($date): array
    {
        return Order::whereDate('delivery_date', Carbon::parse($date)->toDateString())
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->whereNotNull('delivery_slot_id')
            ->selectRaw('delivery_slot_id, COUNT(*) as total')
            ->groupBy('delivery_slot_id')
            ->pluck('total', 'delivery_slot_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * Date de livraison acceptable ? (aujourd'hui .. aujourd'hui + horizon)
     */
    public static function isDateWithinHorizon($date, ?Carbon $now = null): bool
    {
        $now = $now ?: Carbon::now();
        $target = Carbon::parse($date)->startOfDay();

        return $target->gte($now->copy()->startOfDay())
            && $target->lte($now->copy()->startOfDay()->addDays(self::BOOKING_HORIZON_DAYS));
    }
}

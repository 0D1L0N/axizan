<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class RecurringBasket extends Model
{
    /** Cadences proposees, et leur pas en jours. */
    public const FREQUENCIES = [
        'weekly' => 7,
        'biweekly' => 14,
        'monthly' => 30,
    ];

    protected $fillable = ['user_id', 'name', 'frequency', 'next_reminder_at', 'last_reminded_at', 'is_active'];

    protected $casts = [
        'next_reminder_at' => 'datetime',
        'last_reminded_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(RecurringBasketItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeDue($query, ?Carbon $now = null)
    {
        return $query->where('is_active', true)
            ->whereNotNull('next_reminder_at')
            ->where('next_reminder_at', '<=', $now ?: now());
    }

    /**
     * Programme la prochaine echeance a partir de maintenant.
     *
     * On repart de l'instant present, pas de l'echeance manquee : si le rappel
     * a pris trois jours de retard, on ne veut pas rattraper ce retard en
     * envoyant plusieurs rappels d'affilee.
     */
    public function scheduleNext(?Carbon $from = null): self
    {
        $step = self::FREQUENCIES[$this->frequency] ?? 30;

        $this->update([
            'last_reminded_at' => now(),
            'next_reminder_at' => ($from ?: now())->copy()->addDays($step),
        ]);

        return $this;
    }
}

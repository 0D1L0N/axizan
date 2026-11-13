<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Journal d'une commande : une ligne par transition, jamais modifiee.
 */
class OrderEvent extends Model
{
    // Un journal n'a pas d'updated_at : ses lignes ne bougent plus une fois
    // ecrites.
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'actor_id',
        'actor_role',
        'event',
        'from_status',
        'to_status',
        'reason',
        'context',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Enregistre une transition.
     *
     * L'acteur par defaut est l'utilisateur authentifie ; null signifie
     * explicitement « le systeme », pas « on ne sait pas ».
     */
    public static function record(
        Order $order,
        string $event,
        ?string $from = null,
        ?string $to = null,
        ?string $reason = null,
        array $context = [],
        ?User $actor = null
    ): self {
        $actor = $actor ?? auth()->user();

        return self::create([
            'order_id' => $order->id,
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->role instanceof \BackedEnum ? $actor->role->value : $actor?->role,
            'event' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'context' => $context ?: null,
            'created_at' => now(),
        ]);
    }
}

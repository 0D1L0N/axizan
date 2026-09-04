<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /**
     * Journalisation des transitions.
     *
     * Branchee sur l'evenement Eloquent plutot que dans chaque methode du
     * service : toute voie qui change le statut est couverte, y compris celles
     * qui n'existent pas encore et une correction faite a la main en console.
     */
    protected static function booted(): void
    {
        static::created(function (Order $order) {
            OrderEvent::record($order, 'order.created', null, self::statusValue($order->status), context: [
                'total' => (float) $order->total,
                'items' => $order->items()->count(),
            ]);
        });

        static::updated(function (Order $order) {
            if (! $order->wasChanged('status')) {
                return;
            }

            $from = self::statusValue($order->getOriginal('status'));
            $to = self::statusValue($order->status);

            OrderEvent::record($order, 'status.changed', $from, $to, $order->cancellation_reason);
        });
    }

    /** Le statut circule tantot en enum, tantot en chaine selon le chemin. */
    private static function statusValue($status): ?string
    {
        if ($status instanceof \BackedEnum) {
            return $status->value;
        }

        return $status === null ? null : (string) $status;
    }

    public function events()
    {
        return $this->hasMany(OrderEvent::class)->orderBy('created_at');
    }

    protected $fillable = [
        'order_number',
        'client_id',
        'preparateur_id',
        'livreur_id',
        'delivery_address_id',
        'status',
        'delivery_slot_id',
        'delivery_slot_time',
        'delivery_date',
        'estimated_delivery_time',
        'actual_delivery_time',
        'subtotal',
        'delivery_fee',
        'tax',
        'discount_amount',
        'total',
        'promo_code_id',
        'promo_discount',
        'payment_method',
        'payment_status',
        'payment_transaction_id',
        'client_notes',
        'preparateur_notes',
        'livreur_notes',
        'cancellation_reason',
        'accepted_at',
        'preparing_started_at',
        'ready_at',
        'delivery_started_at',
        'delivered_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'promo_discount' => 'decimal:2',
        'delivery_date' => 'date',
        'estimated_delivery_time' => 'datetime',
        'actual_delivery_time' => 'datetime',
        'accepted_at' => 'datetime',
        'preparing_started_at' => 'datetime',
        'ready_at' => 'datetime',
        'delivery_started_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function preparateur()
    {
        return $this->belongsTo(User::class, 'preparateur_id');
    }

    public function livreur()
    {
        return $this->belongsTo(User::class, 'livreur_id');
    }

    public function deliveryAddress()
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }

    public function deliverySlot()
    {
        return $this->belongsTo(DeliverySlot::class);
    }

    public function promo()
    {
        return $this->belongsTo(Promotion::class, 'promo_code_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Ce que l'achat au marche a reellement coute.
     *
     * null tant qu'aucune ligne n'a de prix reel saisi. Le distinguer de zero
     * compte : zero laisserait croire a une marge de 100 %.
     */
    public function purchaseCost(): ?int
    {
        $purchased = $this->items->filter(fn (OrderItem $item) => $item->isPurchased());

        if ($purchased->isEmpty()) {
            return null;
        }

        return \App\Support\Money::round($purchased->sum(fn (OrderItem $item) => (float) $item->actual_total_price));
    }

    /**
     * Marge brute : ce que le client a paye pour les articles, moins ce qu'ils
     * ont coute. Les frais de livraison et la taxe sont exclus — ils ne
     * financent pas l'achat.
     *
     * Ne vaut que si TOUTES les lignes ont ete saisies : une marge calculee
     * sur un panier a moitie renseigne est pire qu'une absence de chiffre.
     */
    public function grossMargin(): ?int
    {
        if ($this->items->isEmpty() || $this->items->contains(fn (OrderItem $item) => ! $item->isPurchased())) {
            return null;
        }

        return \App\Support\Money::round((float) $this->subtotal - (float) $this->purchaseCost());
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    // Scopes
    public function scopeByClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeByStatus($query, OrderStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', OrderStatus::PENDING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', OrderStatus::COMPLETED);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('delivery_date', $date);
    }

    // Helpers
    public function canBeAccepted()
    {
        return $this->status === OrderStatus::PENDING && $this->payment_status === 'completed';
    }

    public function canBePrepared()
    {
        return $this->status === OrderStatus::ACCEPTED;
    }

    public function canBeDelivered()
    {
        return in_array($this->status, [OrderStatus::READY, OrderStatus::IN_DELIVERY]);
    }

    public function canBeCancelled()
    {
        return !$this->status->isTerminal() && $this->status !== OrderStatus::IN_DELIVERY;
    }

    public function getStatusLabelAttribute()
    {
        return $this->status->getLabel();
    }

    public function getEstimatedPrepTimeAttribute()
    {
        return $this->items()
            ->with('product')
            ->get()
            ->max('product.preparation_time_minutes') ?? 30;
    }

    public static function generateOrderNumber()
    {
        $count = static::whereDate('created_at', today())->count() + 1;
        return sprintf('AXZ-%s-%05d', now()->format('Ymd'), $count);
    }
}

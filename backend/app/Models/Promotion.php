<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_order_amount',
        'max_usage_total',
        'max_usage_per_user',
        'usage_count',
        'applicable_to',
        'applicable_categories',
        'applicable_products',
        'start_date',
        'end_date',
        'is_active',
        'created_by_id',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'applicable_categories' => 'array',
        'applicable_products' => 'array',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'promo_code_id');
    }

    public function usages()
    {
        return $this->hasMany(PromotionUsage::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', strtoupper($code));
    }

    // Helpers
    public function isValid()
    {
        return $this->is_active
            && now()->isBetween($this->start_date, $this->end_date)
            && (!$this->max_usage_total || $this->usage_count < $this->max_usage_total);
    }

    public function canBeUsedBy(User $user)
    {
        if ($this->applicable_to === 'first_order' && $user->orders()->exists()) {
            return false;
        }

        if ($this->max_usage_per_user
            && $this->usages()->where('user_id', $user->id)->count() >= $this->max_usage_per_user) {
            return false;
        }

        return true;
    }

    public function calculateDiscount($orderTotal, $orderItems = null)
    {
        $discount = 0;

        if ($this->discount_type === 'percentage') {
            $discount = ($orderTotal * $this->discount_value) / 100;
        } elseif ($this->discount_type === 'fixed_amount') {
            $discount = $this->discount_value;
        }

        if ($this->max_discount_amount) {
            $discount = min($discount, $this->max_discount_amount);
        }

        return min($discount, $orderTotal);
    }

    public function recordUsage(User $user, Order $order, $discountAmount)
    {
        PromotionUsage::create([
            'promotion_id' => $this->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'discount_amount' => $discountAmount,
            'created_at' => now(),
        ]);

        $this->increment('usage_count');
    }
}

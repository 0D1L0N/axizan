<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category_id',
        'base_price',
        'discount_percentage',
        'discounted_price',
        'is_available',
        'daily_limit',
        'daily_sold',
        'image_url',
        'preparation_time_minutes',
        'is_featured',
        'display_order',
        'created_by_id',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function items()
    {
        return $this->hasMany(MenuItem::class);
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // Helpers
    public function getDisplayPriceAttribute()
    {
        return $this->discounted_price ?? $this->base_price;
    }

    public function getSavingsAttribute()
    {
        return $this->base_price - ($this->discounted_price ?? $this->base_price);
    }

    public function isDailySoldOut()
    {
        return $this->daily_limit && $this->daily_sold >= $this->daily_limit;
    }

    public function getRemainingDailyQuotaAttribute()
    {
        return $this->daily_limit ? ($this->daily_limit - $this->daily_sold) : null;
    }
}

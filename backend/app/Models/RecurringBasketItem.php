<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringBasketItem extends Model
{
    protected $fillable = ['recurring_basket_id', 'product_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:3'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

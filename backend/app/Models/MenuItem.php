<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $fillable = [
        'menu_id',
        'product_id',
        'quantity',
        'quantity_unit',
        'display_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

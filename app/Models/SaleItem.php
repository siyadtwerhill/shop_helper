<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'variant_id',
        'unit_id',
        'quantity',
        'base_quantity',
        'unit_price',
        'original_unit_price',
        'discount_amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'base_quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'original_unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPriceRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'unit_id',
        'min_quantity',
        'max_quantity',
        'price',
    ];

    protected $casts = [
        'min_quantity' => 'decimal:4',
        'max_quantity' => 'decimal:4',
        'price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }
}
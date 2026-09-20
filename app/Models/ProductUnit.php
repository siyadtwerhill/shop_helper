<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'unit_id',
        'conversion_factor',
        'selling_price',
        'purchase_price',
        'is_base',
        'is_sellable',
        'is_purchasable',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:4',
        'selling_price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'is_base' => 'boolean',
        'is_sellable' => 'boolean',
        'is_purchasable' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}

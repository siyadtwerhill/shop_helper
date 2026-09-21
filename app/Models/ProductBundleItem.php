<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductBundleItem extends Model
{
    use HasFactory;

    protected $fillable = ['product_bundle_id', 'component_product_id', 'unit_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:4'];

    public function bundle()
    {
        return $this->belongsTo(ProductBundle::class, 'product_bundle_id');
    }

    public function component()
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    public function unit()
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }
}

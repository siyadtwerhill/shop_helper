<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBarcode extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'product_variant_id', 'barcode', 'type', 'is_primary'];
    public function product() { return $this->belongsTo(Product::class); }
}

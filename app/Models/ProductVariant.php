<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'shop_owner_id',
        'sku',
        'attributes',
        'attributes_hash',
        'selling_price',
        'purchase_price',
        'image_path',
        'status',
    ];

    protected $hidden = ['attributes_hash'];

    protected $casts = [
        'attributes' => 'array',
        'selling_price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
    ];

    // current_stock is NOT appended; controllers set it via setAttribute
    // from a single grouped query (ProductStockService::variantStocks).
    protected $appends = ['display_label'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function barcodes()
    {
        return $this->hasMany(ProductBarcode::class, 'product_variant_id');
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class, 'variant_id');
    }

    /** "Black / S" style label from the attributes JSON. */
    public function getDisplayLabelAttribute(): string
    {
        $attrs = $this->getAttribute('attributes');
        return collect($attrs ?? [])->implode(' / ');
    }
}

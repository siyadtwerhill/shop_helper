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
        'sku',
        'attributes',
        'selling_price',
        'purchase_price',
        'image_path',
        'status',
    ];

    protected $casts = [
        'attributes' => 'array',
        'selling_price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
    ];

    protected $appends = ['display_label', 'current_stock'];

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

    /** "Black / S" style label from the attributes json. */
    public function getDisplayLabelAttribute(): string
    {
        $attrs = $this->getAttribute('attributes');
        return collect($attrs ?? [])->implode(' / ');
    }

    /** Computed from the ledger — variants don't have their own stock column (design #5's Stock column). */
    public function getCurrentStockAttribute(): string
    {
        return $this->inventoryMovements()->sum('base_quantity') ?: '0.0000';
    }
}

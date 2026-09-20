<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'variant_id',
        'unit_id',
        'quantity',
        'base_quantity',
        'movement_type',
        'reference_type',
        'reference_id',
        'note',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'base_quantity' => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** The product_units row this movement was recorded in (has the conversion_factor used). */
    public function unit()
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }
}
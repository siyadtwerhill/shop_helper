<?php

namespace App\Models\Concerns;

use App\Models\ProductUnit;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Add to Product.php with: use App\Models\Concerns\HasUnits; ... class Product extends Model { use HasUnits; ... }
 */
trait HasUnits
{
    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function sellableUnits(): HasMany
    {
        return $this->units()->where('is_sellable', true);
    }

    public function purchasableUnits(): HasMany
    {
        return $this->units()->where('is_purchasable', true);
    }

    /** The product_units row flagged as the base unit (conversion_factor = 1). */
    public function baseUnitRow(): HasMany
    {
        return $this->units()->where('is_base', true);
    }

    /** Denormalized shortcut set on products.base_unit_id for fast joins. */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'base_unit_id');
    }
}
<?php

namespace App\Models\Concerns;

use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Add to Product.php alongside HasUnits: use App\Models\Concerns\HasInventoryMovements;
 */
trait HasInventoryMovements
{
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
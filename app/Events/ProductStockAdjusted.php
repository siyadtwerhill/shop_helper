<?php

namespace App\Events;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;

class ProductStockAdjusted
{
    use Dispatchable;

    public function __construct(public Product $product, public InventoryMovement $movement)
    {
    }
}

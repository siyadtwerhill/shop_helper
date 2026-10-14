<?php

namespace Tests\Support;

trait CreatesProducts
{
    protected function giveStock(\App\Models\Product $p, string|float $qty, ?\App\Models\ProductVariant $v = null): void
    {
        $unit = $p->units()->where('is_base', true)->firstOrFail();
        app(\App\Services\InventoryMovementService::class)->openingStock($p, $unit, $qty, null, $v);
    }
}

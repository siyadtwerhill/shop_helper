<?php

namespace App\Listeners;

use App\Events\ProductStockAdjusted;
use App\Services\ProductActivityLogger;

class LogStockAdjustmentActivity
{
    public function __construct(private ProductActivityLogger $logger)
    {
    }

    public function handle(ProductStockAdjusted $event): void
    {
        $movement = $event->movement;
        $sign = bccomp((string) $movement->quantity, '0', 4) >= 0 ? '+' : '';
        $symbol = $movement->unit->unit->symbol ?? '';

        $this->logger->log(
            $event->product,
            'stock_adjusted',
            $movement->created_by,
            field: 'current_stock',
            newValue: (string) $movement->base_quantity,
            description: "Stock adjusted {$sign}{$movement->quantity} {$symbol}",
        );
    }
}

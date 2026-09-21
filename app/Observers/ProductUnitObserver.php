<?php

namespace App\Observers;

use App\Models\ProductUnit;
use App\Services\ProductActivityLogger;
use Illuminate\Support\Facades\Auth;

class ProductUnitObserver
{
    public function __construct(private ProductActivityLogger $logger)
    {
    }

    public function updated(ProductUnit $productUnit): void
    {
        if ($productUnit->wasChanged('selling_price')) {
            $old = $productUnit->getOriginal('selling_price');
            $new = $productUnit->selling_price;

            $this->logger->log(
                $productUnit->product,
                'price_changed',
                Auth::id(),
                field: 'selling_price',
                oldValue: (string) $old,
                newValue: (string) $new,
                description: "Price changed {$old} \u{2192} {$new}",
            );
        }

        if ($productUnit->wasChanged('purchase_price')) {
            $old = $productUnit->getOriginal('purchase_price');
            $new = $productUnit->purchase_price;

            $this->logger->log(
                $productUnit->product,
                'cost_changed',
                Auth::id(),
                field: 'purchase_price',
                oldValue: (string) $old,
                newValue: (string) $new,
                description: "Cost changed {$old} \u{2192} {$new}",
            );
        }
    }
}

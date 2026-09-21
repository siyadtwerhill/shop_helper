<?php

namespace App\Observers;

use App\Models\ProductBarcode;
use App\Services\ProductActivityLogger;
use Illuminate\Support\Facades\Auth;

class ProductBarcodeObserver
{
    public function __construct(private ProductActivityLogger $logger)
    {
    }

    public function created(ProductBarcode $barcode): void
    {
        if (! $barcode->product) {
            return; // variant-only barcode with no direct product relation loaded
        }

        $this->logger->log(
            $barcode->product,
            'barcode_added',
            Auth::id(),
            field: 'barcode',
            newValue: $barcode->barcode,
            description: "Barcode added ({$barcode->barcode})",
        );
    }
}

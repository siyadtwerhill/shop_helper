<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

class QrCodeGenerator
{
    /**
     * QR encodes the product's SKU — simplest payload that's
     * unambiguous, unique, and doesn't leak a full URL/ID scheme.
     * Note: QR generation disabled due to missing library.
     * Returns a placeholder path.
     */
    public function generateFor(Product $product): ?string
    {
        // QR code library not installed - return placeholder
        // To enable QR codes, install: composer require simplesoftwareio/simple-qrcode
        return null;
    }
}
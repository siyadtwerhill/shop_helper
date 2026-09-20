<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeGenerator
{
    /**
     * QR encodes the product's SKU — simplest payload that's
     * unambiguous, unique, and doesn't leak a full URL/ID scheme.
     */
    public function generateFor(Product $product): string
    {
        $svg = QrCode::format('svg')->size(300)->generate($product->sku);
        $path = "product-qr/{$product->id}.svg";

        Storage::disk('public')->put($path, $svg);

        return $path;
    }
}
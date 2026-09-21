<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Builds the data a printable label needs (spec §14: name, price when appropriate, barcode/QR).
 * Assumes Product already exposes a qr_url accessor and a barcodes() relation from Phase 1 —
 * rename below if your actual accessor/relation names differ.
 */
class LabelGeneratorService
{
    public function buildForProduct(Product $product): array
    {
        $primaryBarcode = $product->barcodes()->where('is_primary', true)->first();

        return [
            'product_id' => $product->id,
            'variant_id' => null,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => optional($product->baseUnit)->selling_price,
            'barcode' => $primaryBarcode?->barcode,
            'qr_code_url' => $product->qr_url ?? null,
        ];
    }

    public function buildForVariant(Product $product, ProductVariant $variant): array
    {
        $primaryBarcode = $variant->barcodes()->where('is_primary', true)->first()
            ?? $product->barcodes()->where('is_primary', true)->first();

        return [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'name' => "{$product->name} ({$variant->display_label})",
            'sku' => $variant->sku,
            'price' => $variant->selling_price ?? optional($product->baseUnit)->selling_price,
            'barcode' => $primaryBarcode?->barcode,
            'qr_code_url' => $product->qr_url ?? null,
        ];
    }
}

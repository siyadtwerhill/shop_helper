<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use InvalidArgumentException;
use RuntimeException;

/**
 * Central place for the spec's core rule: "Conversion determines inventory quantity.
 * Pricing determines money." All math uses bcmath so DECIMAL(15,4) values never drift
 * through float rounding (e.g. 0.1 + 0.2 float issues).
 */
class UnitConversionService
{
    private const SCALE = 4;

    /**
     * Convert a quantity given in $productUnit's unit into the product's base unit.
     * e.g. 5 "အိတ်" with conversion_factor 20.0000 -> 100.0000 KG
     */
    public function toBaseQuantity(ProductUnit $productUnit, string|float $quantity): string
    {
        $this->assertNonNegative($quantity);

        return bcmul(
            $this->normalize($quantity),
            $this->normalize($productUnit->conversion_factor),
            self::SCALE
        );
    }

    /**
     * Convert a base-unit quantity back into $productUnit's unit.
     * e.g. 97.5000 KG in the "500 G" unit (conversion_factor 0.5000) -> 195.0000
     */
    public function fromBaseQuantity(ProductUnit $productUnit, string|float $baseQuantity): string
    {
        $factor = $this->normalize($productUnit->conversion_factor);

        if (bccomp($factor, '0', self::SCALE) === 0) {
            throw new InvalidArgumentException(
                "Unit #{$productUnit->unit_id} on product #{$productUnit->product_id} has a zero conversion_factor."
            );
        }

        return bcdiv($this->normalize($baseQuantity), $factor, self::SCALE);
    }

    /**
     * Convert directly between two of a product's units (e.g. "500 G" -> "အိတ်")
     * by round-tripping through the base unit.
     */
    public function convertBetween(ProductUnit $from, ProductUnit $to, string|float $quantity): string
    {
        if ($from->product_id !== $to->product_id) {
            throw new InvalidArgumentException('Cannot convert between units belonging to different products.');
        }

        return $this->fromBaseQuantity($to, $this->toBaseQuantity($from, $quantity));
    }

    /** The product_units row flagged is_base = true. Throws if the product has none configured. */
    public function baseUnitFor(Product $product): ProductUnit
    {
        $base = $product->units()->where('is_base', true)->first();

        if (! $base) {
            throw new RuntimeException("Product #{$product->id} has no base unit configured.");
        }

        return $base;
    }

    /**
     * Enforce "one base unit per product": clears is_base on every other unit,
     * sets it (and conversion_factor = 1) on the given one, and syncs the
     * products.base_unit_id cache column used for fast joins.
     */
    public function setBaseUnit(Product $product, ProductUnit $productUnit): void
    {
        if ($productUnit->product_id !== $product->id) {
            throw new InvalidArgumentException('That unit does not belong to this product.');
        }

        $product->units()->where('id', '!=', $productUnit->id)->update(['is_base' => false]);

        $productUnit->forceFill([
            'is_base' => true,
            'conversion_factor' => '1.0000',
        ])->save();

        $product->forceFill(['base_unit_id' => $productUnit->id])->save();
    }

    private function assertNonNegative(string|float $quantity): void
    {
        if (bccomp($this->normalize($quantity), '0', self::SCALE) < 0) {
            throw new InvalidArgumentException('Quantity cannot be negative.');
        }
    }

    private function normalize(string|float $value): string
    {
        // bcmath needs plain decimal strings; casts from Eloquent already arrive as strings,
        // but guard against floats passed in directly (e.g. from request input).
        return is_float($value) ? number_format($value, self::SCALE, '.', '') : $value;
    }
}
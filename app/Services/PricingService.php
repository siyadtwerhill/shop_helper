<?php

namespace App\Services;

use App\Enums\PricingMode;
use App\Exceptions\MinimumMarginViolationException;
use App\Exceptions\PriceBelowMinimumException;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use InvalidArgumentException;

class PricingService
{
    /**
     * Resolve the price to charge for $quantity of $productUnit, given the product's
     * pricing_mode and the acting user's permissions, then run it through margin protection.
     * Returns ['price' => string, 'requires_approval' => bool] — requires_approval is true when
     * a manager approved a below-margin sale, so the caller can record that on the sale.
     */
    public function resolvePrice(
        Product $product,
        ProductUnit $productUnit,
        string|float $quantity,
        ?string $requestedPrice,
        User $user,
    ): array {
        $mode = PricingMode::from($product->pricing_mode);

        $price = match ($mode) {
            PricingMode::Fixed => $this->resolveFixedPrice($productUnit, $requestedPrice, $user),
            PricingMode::Negotiable => $this->resolveNegotiablePrice($requestedPrice, $user),
            PricingMode::PriceRange => $this->resolvePriceRangePrice($product, $productUnit, $requestedPrice),
            PricingMode::Wholesale => $this->wholesaleTierPrice($product, $productUnit, (string) $quantity)
                ?? (string) $productUnit->selling_price,
        };

        return $this->applyMarginProtection($product, $price, $user);
    }

    /** Fixed-price products don't budge unless the user has explicit override permission. */
    private function resolveFixedPrice(ProductUnit $productUnit, ?string $requestedPrice, User $user): string
    {
        $listPrice = (string) $productUnit->selling_price;

        if ($requestedPrice === null || bccomp($requestedPrice, $listPrice, 2) === 0) {
            return $listPrice;
        }

        if (! $user->can('override-fixed-price')) {
            throw new InvalidArgumentException(
                'This product is fixed-price. Repricing it requires the override-fixed-price permission.'
            );
        }

        return $requestedPrice;
    }

    /** Cashier enters an agreed price; requires negotiate-price permission (spec §5). */
    private function resolveNegotiablePrice(?string $requestedPrice, User $user): string
    {
        if ($requestedPrice === null) {
            throw new InvalidArgumentException('Negotiable pricing requires an agreed price.');
        }

        if (! $user->can('negotiate-price')) {
            throw new InvalidArgumentException('User lacks permission to negotiate prices.');
        }

        return $requestedPrice;
    }

    /** Listed price plus a configurable floor — requested price must not go below product.min_price. */
    private function resolvePriceRangePrice(Product $product, ProductUnit $productUnit, ?string $requestedPrice): string
    {
        $price = $requestedPrice ?? (string) $productUnit->selling_price;

        if ($product->min_price !== null && bccomp($price, (string) $product->min_price, 2) < 0) {
            throw new PriceBelowMinimumException(
                "Price {$price} is below product #{$product->id}'s configured minimum {$product->min_price}."
            );
        }

        return $price;
    }

    /** Quantity-tiered pricing (e.g. 1–9, 10–49, 50+). Falls back to the unit's list price if no tier matches. */
    public function wholesaleTierPrice(Product $product, ProductUnit $productUnit, string $quantity): ?string
    {
        $rule = $product->priceRules()
            ->where('unit_id', $productUnit->id)
            ->where('min_quantity', '<=', $quantity)
            ->where(function ($q) use ($quantity) {
                $q->whereNull('max_quantity')->orWhere('max_quantity', '>=', $quantity);
            })
            ->orderByDesc('min_quantity')
            ->first();

        return $rule?->price;
    }

    /**
     * cost_price + min_margin_percent -> the floor selling price. Returns null when either
     * field isn't configured, meaning margin protection is simply off for this product.
     */
    public function minimumSellingPrice(Product $product): ?string
    {
        if ($product->cost_price === null || $product->min_margin_percent === null) {
            return null;
        }

        $multiplier = bcadd('1', bcdiv((string) $product->min_margin_percent, '100', 6), 6);

        return bcmul((string) $product->cost_price, $multiplier, 2);
    }

    /**
     * Selling below the margin floor requires approve-below-margin-sale permission (spec §5:
     * "Selling below it can require manager approval"). Throws if unapproved, otherwise flags
     * requires_approval so the caller can log who approved it.
     */
    private function applyMarginProtection(Product $product, string $price, User $user): array
    {
        $floor = $this->minimumSellingPrice($product);

        if ($floor !== null && bccomp($price, $floor, 2) < 0) {
            if (! $user->can('approve-below-margin-sale')) {
                throw new MinimumMarginViolationException(
                    "Price {$price} is below the minimum margin price {$floor} and requires manager approval."
                );
            }

            return ['price' => $price, 'requires_approval' => true];
        }

        return ['price' => $price, 'requires_approval' => false];
    }
}
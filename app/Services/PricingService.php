<?php

namespace App\Services;

use App\Enums\PricingMode;
use App\Exceptions\MinimumMarginViolationException;
use App\Exceptions\PriceBelowMinimumException;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\User;
use InvalidArgumentException;

class PricingService
{
    /**
     * Resolve the server-authoritative POS price and enforce the configured margin floor.
     */
    public function resolvePrice(
        Product $product,
        ProductUnit $productUnit,
        string|float $quantity,
        ?string $requestedPrice,
        User $user,
        ?ProductVariant $variant = null,
        ?string $fixedPriceOverride = null,
    ): array {
        $mode = PricingMode::from($product->pricing_mode);
        $listPrice = $fixedPriceOverride ?? (string) ($variant?->selling_price ?? $productUnit->selling_price);
        $tier = $mode === PricingMode::Wholesale
            ? $product->priceRules()
                ->where('unit_id', $productUnit->id)
                ->where('min_quantity', '<=', $quantity)
                ->orderByDesc('min_quantity')
                ->first()
            : null;

        $price = match ($mode) {
            // The catalog price is authoritative; never accept a browser-supplied override.
            PricingMode::Fixed => $listPrice,
            PricingMode::Negotiable => $this->resolveNegotiablePrice($requestedPrice, $user),
            PricingMode::PriceRange => $this->resolvePriceRangePrice($product, $listPrice, $requestedPrice),
            // Below the first tier, use the ordinary selling price.
            PricingMode::Wholesale => (string) ($tier?->price ?? $listPrice),
        };

        return $this->applyMarginProtection($product, $productUnit, $variant, $price) + [
            'pricing_mode' => $mode->value,
            'quantity' => (string) $quantity,
            'tier_min_quantity' => $tier?->min_quantity,
            'tier_price' => $tier?->price,
        ];
    }

    /** Cashier enters an agreed price; requires negotiate-price permission (spec §5). */
    private function resolveNegotiablePrice(?string $requestedPrice, User $user): string
    {
        if ($requestedPrice === null) {
            throw new InvalidArgumentException('Negotiable pricing requires an agreed price.');
        }

        if (! $user->isShopOwner() && ! $user->isSuperAdmin() && ! $user->can('negotiate-price')) {
            throw new InvalidArgumentException('User lacks permission to negotiate prices.');
        }

        return $requestedPrice;
    }

    /** Listed price plus a configurable floor — requested price must not go below product.min_price. */
    private function resolvePriceRangePrice(Product $product, string $listPrice, ?string $requestedPrice): string
    {
        $price = $requestedPrice ?? $listPrice;

        if ($product->min_price !== null && bccomp($price, (string) $product->min_price, 2) < 0) {
            throw new PriceBelowMinimumException(
                "Price {$price} is below product #{$product->id}'s configured minimum {$product->min_price}."
            );
        }

        return $price;
    }

    /** Quantity-tiered pricing: the highest minimum quantity not exceeding the order quantity applies. */
    public function wholesaleTierPrice(Product $product, ProductUnit $productUnit, string $quantity): ?string
    {
        $rule = $product->priceRules()
            ->where('unit_id', $productUnit->id)
            ->where('min_quantity', '<=', $quantity)
            ->orderByDesc('min_quantity')
            ->first();

        return $rule?->price;
    }

    /** Computes the minimum price using either a percentage or fixed-money margin. */
    public function minimumSellingPrice(Product $product, ProductUnit $productUnit, ?ProductVariant $variant = null): ?string
    {
        $cost = $variant?->purchase_price ?? $productUnit->purchase_price ?? $product->cost_price;
        $type = $product->min_margin_type;
        $value = $product->min_margin_value;

        // Keep existing products working until their old percentage value is migrated through the form.
        if ($value === null && $product->min_margin_percent !== null) {
            $type ??= 'percentage';
            $value = $product->min_margin_percent;
        }

        if ($cost === null || $value === null) {
            return null;
        }

        if ($type === 'amount') {
            return bcadd((string) $cost, (string) $value, 2);
        }

        $multiplier = bcadd('1', bcdiv((string) $value, '100', 6), 6);

        return bcmul((string) $cost, $multiplier, 2);
    }

    /** Rejects below-floor pricing; exceptions need a separately audited approval workflow. */
    private function applyMarginProtection(Product $product, ProductUnit $productUnit, ?ProductVariant $variant, string $price): array
    {
        $floor = $this->minimumSellingPrice($product, $productUnit, $variant);

        if ($floor !== null && bccomp($price, $floor, 2) < 0) {
            throw new MinimumMarginViolationException(
                "Price {$price} is below the minimum selling price {$floor} set by the product margin."
            );
        }

        return ['price' => $price, 'requires_approval' => false];
    }
}

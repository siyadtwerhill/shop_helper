<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductBundle;

class ProductStockService
{
    /** What the shop can actually sell, in the product's base unit. */
    public function availableFor(Product $product): string
    {
        return $product->stock_mode === 'from_components'
            ? $this->bundleAvailable($product)
            : $this->dec($product->getRawOriginal('current_stock'));
    }

    /** Whole bundles that can be made from the product's bundle configuration. */
    public function bundleAvailable(Product $product): string
    {
        $bundle = $product->bundle ?? $product->bundle()->first();
        return $bundle ? $this->bundleAvailableFor($bundle) : '0';
    }

    /** Whole bundles that can be made: the limiting component, after unit conversion. */
    public function bundleAvailableFor(ProductBundle $bundle): string
    {
        $bundle->loadMissing('items.unit', 'items.component');
        if ($bundle->items->isEmpty()) return '0';

        $min = null;
        foreach ($bundle->items as $item) {
            $perBundle = bcmul($this->dec($item->quantity), $this->dec($item->unit?->conversion_factor ?? 1), 4);
            if (bccomp($perBundle, '0', 4) <= 0) return '0';

            $variantId = $item->component_variant_id;
            $have = $variantId
                ? $this->variantStock((int) $variantId)
                : $this->dec($item->component?->getRawOriginal('current_stock') ?? 0);

            $possible = bcdiv($have, $perBundle, 4);
            if ($min === null || bccomp($possible, $min, 4) < 0) $min = $possible;
        }

        return bccomp($min, '0', 4) <= 0 ? '0' : bcadd($min, '0', 0); // truncate = floor for positives
    }

    public function variantStock(int $variantId): string
    {
        return $this->dec(
            InventoryMovement::where('variant_id', $variantId)
                ->selectRaw('COALESCE(SUM(base_quantity), 0) as total')->value('total')
        );
    }

    /** One grouped query for a whole variant list: [variant_id => stock]. */
    public function variantStocks(array $variantIds): array
    {
        if (! $variantIds) return [];

        return InventoryMovement::whereIn('variant_id', $variantIds)
            ->selectRaw('variant_id, SUM(base_quantity) as total')
            ->groupBy('variant_id')->pluck('total', 'variant_id')
            ->map(fn ($v) => $this->dec($v))->all();
    }

    /** Stock counted at product level that no variant owns (legacy sales, or stock from before variants). */
    public function unassignedFor(Product $product): string
    {
        $assigned = InventoryMovement::where('product_id', $product->id)->whereNotNull('variant_id')
            ->selectRaw('COALESCE(SUM(base_quantity), 0) as t')->value('t');

        return bcsub($this->dec($product->getRawOriginal('current_stock')), $this->dec($assigned), 4);
    }

    /** Sets available_stock on each product (replaces the old appended accessor). */
    public function hydrate(iterable $products): void
    {
        foreach ($products as $p) {
            $p->setAttribute('available_stock', $this->availableFor($p));
        }
    }

    private function dec(mixed $v): string
    {
        if ($v === null || $v === '') return '0.0000';
        return is_string($v) ? $v : number_format((float) $v, 4, '.', '');
    }
}

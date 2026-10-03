<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductVariant;
use App\Models\SaleItem;
use Illuminate\Validation\ValidationException;

class BundleService
{
    /**
     * Validates components against the shop and returns rows ready for product_bundle_items.
     * Pass $bundleProductId to block a bundle from containing itself.
     */
    public function normalizeItems(int $shopId, array $items, bool $decimals, ?int $bundleProductId = null): array
    {
        if (! $items) $this->fail('bundle_items', 'Add at least one product to the bundle.');

        $rows = [];
        $seen = [];

        foreach ($items as $i => $item) {
            $f = "bundle_items.$i";
            $component = Product::where('shop_owner_id', $shopId)->find($item['component_product_id'] ?? 0);

            if (! $component) $this->fail("$f.component_product_id", 'Product not found.');
            if ($component->id === $bundleProductId) $this->fail("$f.component_product_id", 'A bundle cannot contain itself.');
            if ($component->status !== 'active') $this->fail("$f.component_product_id", "\"{$component->name}\" is inactive.");
            if ($component->stock_mode === 'from_components') $this->fail("$f.component_product_id", "\"{$component->name}\" is a bundle. Bundles can't contain bundles.");

            if (! $component->units()->whereKey($item['unit_id'] ?? 0)->exists()) {
                $this->fail("$f.unit_id", "That unit does not belong to \"{$component->name}\".");
            }

            $variantId = $item['component_variant_id'] ?? null;
            if ($component->stock_mode === 'from_variants') {
                if (! $variantId) $this->fail("$f.component_variant_id", "Choose a variant of \"{$component->name}\".");
                $ok = ProductVariant::where('product_id', $component->id)
                    ->where('status', 'active')->whereKey($variantId)->exists();
                if (! $ok) $this->fail("$f.component_variant_id", 'That variant is not available.');
            } elseif ($variantId) {
                $this->fail("$f.component_variant_id", "\"{$component->name}\" does not use variants.");
            }

            $key = $component->id . '|' . ($variantId ?? 0);
            if (isset($seen[$key])) $this->fail($f, 'This product is already in the bundle.');
            $seen[$key] = true;

            $q = (string) ($item['quantity'] ?? '');
            if (! preg_match('/^\d{1,11}(\.\d{1,4})?$/', $q) || bccomp($q, '0', 4) <= 0) {
                $this->fail("$f.quantity", 'Enter a quantity above zero.');
            }
            if (! $decimals && bccomp(bcmul($q, '1', 0), $q, 4) !== 0) {
                $this->fail("$f.quantity", 'This product type only allows whole quantities.');
            }

            $rows[] = [
                'component_product_id' => $component->id,
                'component_variant_id' => $variantId ? (int) $variantId : null,
                'unit_id'              => (int) $item['unit_id'],
                'quantity'             => $q,
            ];
        }

        return $rows;
    }

    /** Only a plain product with no history can be turned into a bundle. */
    public function assertConvertible(Product $product): void
    {
        $blocked = $product->stock_mode !== 'own'
            || $product->inventoryMovements()->exists()
            || $product->variants()->exists()
            || SaleItem::where('product_id', $product->id)->exists();

        abort_if($blocked, 422, "This product already has stock, variants or sales, so it can't become a bundle. Create a new bundle instead.");
    }

    public function create(Product $product, string $price, array $rows): ProductBundle
    {
        $bundle = $product->bundle()->create(['bundle_price' => $price]);
        $bundle->items()->createMany($rows);
        return $this->refresh($product, $bundle);
    }

    /** $price or $rows may be null to leave that part unchanged. */
    public function replace(ProductBundle $bundle, ?string $price, ?array $rows): ProductBundle
    {
        if ($price !== null) $bundle->update(['bundle_price' => $price]);
        if ($rows !== null) {
            $bundle->items()->delete();
            $bundle->items()->createMany($rows);
        }
        return $this->refresh($bundle->product, $bundle);
    }

    /** Normal total, cost, savings, and a below-cost flag. */
    public function summary(ProductBundle $bundle): array
    {
        $bundle->loadMissing('items.unit', 'items.variant', 'items.component');

        $normal   = '0';
        $cost     = '0';
        $complete = $bundle->items->isNotEmpty();

        foreach ($bundle->items as $item) {
            $qty     = (string) $item->quantity;
            $unit    = $item->unit;
            $factor  = (string) ($unit?->conversion_factor ?? 1);
            $variant = $item->variant;

            $price = $variant?->selling_price !== null
                ? bcmul((string) $variant->selling_price, $factor, 4)
                : ($unit?->selling_price !== null ? (string) $unit->selling_price : null);

            $unitCost = $variant?->purchase_price !== null
                ? bcmul((string) $variant->purchase_price, $factor, 4)
                : ($unit?->purchase_price !== null
                    ? (string) $unit->purchase_price
                    : ($item->component?->cost_price !== null
                        ? bcmul((string) $item->component->cost_price, $factor, 4)
                        : null));

            if ($price === null)    $complete = false;
            else                    $normal = bcadd($normal, bcmul($qty, $price, 4), 4);

            if ($unitCost === null) $complete = false;
            else                   $cost = bcadd($cost, bcmul($qty, $unitCost, 4), 4);
        }

        $bundlePrice = (string) $bundle->bundle_price;

        return [
            'normal_total' => bcadd($normal, '0', 2),
            'cost_total'   => $complete ? bcadd($cost, '0', 2) : null,
            'savings'      => bcsub($normal, $bundlePrice, 2),
            'below_cost'   => $complete && bccomp($bundlePrice, $cost, 2) < 0,
            'complete'     => $complete,
        ];
    }

    /** Mirror bundle_price onto the base unit's selling_price, and save component cost. */
    private function refresh(Product $product, ProductBundle $bundle): ProductBundle
    {
        $bundle->load('items.unit', 'items.variant', 'items.component');
        $s = $this->summary($bundle);

        $product->units()->where('is_base', true)->first()?->update([
            'selling_price'  => $bundle->bundle_price,
            'purchase_price' => $s['cost_total'],
        ]);
        $product->forceFill(['cost_price' => $s['cost_total']])->save();

        return $bundle;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}

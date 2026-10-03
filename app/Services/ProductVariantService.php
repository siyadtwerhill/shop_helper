<?php

namespace App\Services;

use App\Models\Blueprint;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductVariantService
{
    public function __construct(
        private BarcodeGenerator $barcodes,
        private InventoryMovementService $movements,
        private ProductStockService $stock,
        private ProductActivityLogger $activity,
    ) {}

    public function blueprintFor(Product $product): Blueprint
    {
        return $product->blueprint
            ?? Blueprint::where('shop_owner_id', $product->shop_owner_id)->where('is_default', true)->firstOrFail();
    }

    /** The variant options (axes) defined by the product blueprint, in display order. */
    public function axesFor(Product $product): array
    {
        return $this->blueprintFor($product)->fields()
            ->where('is_variant_axis', true)->where('hidden', false)
            ->with('fieldDefinition')->orderBy('sort_order')->get()
            ->filter(fn ($f) => $f->fieldDefinition)
            ->map(fn ($f) => [
                'key'      => $f->fieldDefinition->key,
                'label'    => $f->fieldDefinition->label,
                'required' => (bool) $f->required,
                'options'  => $this->optionValues($f->fieldDefinition->options ?? []),
                'strict'   => (bool) (($f->fieldDefinition->validation ?? [])['strict'] ?? false),
            ])->values()->all();
    }

    public function hashFor(array $attrs): string
    {
        $n = [];
        foreach ($attrs as $k => $v) {
            $v = mb_strtolower($this->clean($v));
            if ($v !== '') $n[$k] = $v;
        }
        ksort($n);
        return sha1(json_encode($n, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array $rows [{attributes:{}, selling_price?, purchase_price?, sku?, barcode?, opening_stock?, image_path?}]
     * @return array{created: array, skipped: array}
     */
    public function createMany(Product $product, array $rows, ?int $userId, bool $skipExisting = false): array
    {
        return DB::transaction(function () use ($product, $rows, $userId, $skipExisting) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $this->assertCanHaveVariants($locked);

            $bp              = $this->blueprintFor($locked);
            $perVariantPrice = (bool) (($bp->pricing_policy ?? [])['price_per_variant'] ?? false);
            $decimals        = (bool) (($bp->capabilities ?? [])['decimal_quantities'] ?? true);

            $axes = $this->axesFor($locked);
            abort_if(! $axes, 422, 'This product type has no variant options yet. Add options like Color or Size in the product type settings.');

            $base     = $locked->units()->where('is_base', true)->firstOrFail();
            $existing = ProductVariant::where('product_id', $locked->id)->pluck('attributes_hash')->filter()->flip();
            $seen     = [];
            $created  = [];
            $skipped  = [];

            foreach ($rows as $i => $row) {
                $attrs = $this->normalizeAttributes($axes, $row['attributes'] ?? [], "variants.$i.attributes");
                $hash  = $this->hashFor($attrs);

                if (isset($existing[$hash])) {
                    if ($skipExisting) { $skipped[] = $attrs; continue; }
                    throw ValidationException::withMessages(["variants.$i.attributes" => ['This combination already exists.']]);
                }
                if (isset($seen[$hash])) {
                    throw ValidationException::withMessages(["variants.$i.attributes" => ['This combination appears twice in the list.']]);
                }
                $seen[$hash] = true;

                $stock = (string) ($row['opening_stock'] ?? '0');
                if (! $decimals && bccomp(bcmul($stock, '1', 0), $stock, 4) !== 0) {
                    throw ValidationException::withMessages(["variants.$i.opening_stock" => ['This product type only allows whole quantities.']]);
                }

                $sku = ! empty($row['sku']) ? $this->clean($row['sku']) : $this->makeSku($locked, $attrs);
                if (! empty($row['sku']) && $this->skuTaken($locked->shop_owner_id, $sku)) {
                    throw ValidationException::withMessages(["variants.$i.sku" => ['This SKU is already used.']]);
                }

                $code = ! empty($row['barcode']) ? $this->clean($row['barcode']) : null;
                if ($code && ProductBarcode::where('shop_owner_id', $locked->shop_owner_id)->where('barcode', $code)->exists()) {
                    throw ValidationException::withMessages(["variants.$i.barcode" => ['This barcode is already used.']]);
                }

                $variant = ProductVariant::create([
                    'product_id'      => $locked->id,
                    'shop_owner_id'   => $locked->shop_owner_id,
                    'sku'             => $sku,
                    'attributes'      => $attrs,
                    'attributes_hash' => $hash,
                    'selling_price'   => $perVariantPrice ? ($row['selling_price'] ?? null) : null,
                    'purchase_price'  => $row['purchase_price'] ?? null,
                    'image_path'      => $row['image_path'] ?? null,
                    'status'          => 'active',
                ]);

                ProductBarcode::create([
                    'shop_owner_id'      => $locked->shop_owner_id,
                    'product_id'         => $locked->id,
                    'product_variant_id' => $variant->id,
                    'barcode'            => $code ?? $this->barcodes->generate(),
                    'type'               => $code ? 'manufacturer' : 'auto_generated',
                    'is_primary'         => true,
                ]);

                if (bccomp($stock, '0', 4) > 0) {
                    $this->movements->openingStock($locked, $base, $stock, $userId, $variant);
                }

                $created[] = $variant;
            }

            if ($created) {
                $this->activity->log($locked, 'variants_added', $userId, description: count($created) . ' variant(s) added');
            }

            return ['created' => $created, 'skipped' => $skipped];
        });
    }

    public function updateVariant(Product $product, ProductVariant $variant, array $data): ProductVariant
    {
        return DB::transaction(function () use ($product, $variant, $data) {
            $locked          = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $perVariantPrice = (bool) (($this->blueprintFor($locked)->pricing_policy ?? [])['price_per_variant'] ?? false);

            if (array_key_exists('attributes', $data)) {
                $attrs = $this->normalizeAttributes($this->axesFor($locked), $data['attributes'], 'attributes');
                $hash  = $this->hashFor($attrs);

                if (ProductVariant::where('product_id', $locked->id)->where('attributes_hash', $hash)->where('id', '!=', $variant->id)->exists()) {
                    throw ValidationException::withMessages(['attributes' => ['Another variant already has this combination.']]);
                }
                $data['attributes']      = $attrs;
                $data['attributes_hash'] = $hash;
            }
            if (! $perVariantPrice) unset($data['selling_price']);

            $variant->fill($data)->save();

            return $variant;
        });
    }

    /** @return string 'deleted' or 'archived' */
    public function removeVariant(Product $product, ProductVariant $variant, ?int $userId): string
    {
        return DB::transaction(function () use ($product, $variant, $userId) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            // Block removal if this variant is used as a bundle component.
            $usedIn = \App\Models\ProductBundleItem::where('component_variant_id', $variant->id)
                ->with('bundle.product:id,name')->get()
                ->pluck('bundle.product.name')->filter()->unique();
            if ($usedIn->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'variant' => ['This variant is used in bundles: ' . $usedIn->join(', ') . '.'],
                ]);
            }

            if (bccomp($this->stock->variantStock($variant->id), '0', 4) !== 0) {
                throw ValidationException::withMessages(['variant' => ["Adjust this variant's stock to zero before removing it."]]);
            }

            $hasHistory = InventoryMovement::where('variant_id', $variant->id)->exists()
                || SaleItem::where('variant_id', $variant->id)->exists();

            $variant->barcodes()->delete();
            $this->activity->log($locked, 'variant_removed', $userId, description: "Variant removed ({$variant->display_label})");

            if ($hasHistory) {
                $variant->forceFill([
                    'status'          => 'inactive',
                    'attributes_hash' => null,
                    'sku'             => substr($variant->sku, 0, 150) . '~del' . $variant->id,
                ])->save();
                $variant->delete();
                return 'archived';
            }

            if ($variant->image_path) Storage::disk('public')->delete($variant->image_path);
            $variant->forceDelete();
            return 'deleted';
        });
    }

    /* ---------------------------------------------------------------- */

    private function assertCanHaveVariants(Product $product): void
    {
        $caps = $this->blueprintFor($product)->capabilities ?? [];
        abort_if(empty($caps['variants']), 422, 'This product type does not allow variants.');
        abort_if($product->product_type === 'bundle' || $product->stock_mode === 'from_components', 422, "Bundles can't have variants.");

        if ($product->stock_mode === 'own') {
            abort_if(
                $product->inventoryMovements()->exists(),
                422,
                "This product already has stock history, so it can't become a variant product. Create a new variant product instead."
            );
            $product->forceFill(['product_type' => 'variant', 'stock_mode' => 'from_variants'])->save();
        }
    }

    private function normalizeAttributes(array $axes, mixed $input, string $field): array
    {
        $input   = is_array($input) ? $input : [];
        $unknown = array_diff(array_keys($input), array_column($axes, 'key'));
        if ($unknown) {
            throw ValidationException::withMessages([$field => ['Unknown options: ' . implode(', ', $unknown)]]);
        }

        $out = [];
        foreach ($axes as $axis) {
            $raw = $input[$axis['key']] ?? '';
            $val = is_scalar($raw) ? $this->clean($raw) : '';

            if ($val === '') {
                if ($axis['required']) {
                    throw ValidationException::withMessages(["$field.{$axis['key']}" => ["{$axis['label']} is required."]]);
                }
                continue;
            }
            if (mb_strlen($val) > 50) {
                throw ValidationException::withMessages(["$field.{$axis['key']}" => ["{$axis['label']} is too long."]]);
            }
            if ($axis['strict'] && $axis['options']
                && ! in_array(mb_strtolower($val), array_map('mb_strtolower', $axis['options']), true)) {
                throw ValidationException::withMessages(["$field.{$axis['key']}" => ["Choose one of the listed {$axis['label']} values."]]);
            }
            $out[$axis['key']] = $val;
        }

        if (! $out) {
            throw ValidationException::withMessages([$field => ['Choose at least one option.']]);
        }

        return $out;
    }

    private function makeSku(Product $product, array $attrs): string
    {
        $parts = array_map(function ($v) {
            $a = preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($v));
            return $a !== '' ? Str::upper(substr($a, 0, 3)) : 'V';
        }, array_values($attrs));

        $base = $product->sku . '-' . implode('-', $parts);
        $sku  = $base;
        for ($n = 2; $this->skuTaken($product->shop_owner_id, $sku); $n++) {
            $sku = $base . '-' . $n;
            if ($n > 100) throw ValidationException::withMessages(['sku' => ['Could not generate a unique SKU.']]);
        }
        return $sku;
    }

    private function skuTaken(int $shopId, string $sku): bool
    {
        return ProductVariant::withTrashed()->where('shop_owner_id', $shopId)->where('sku', $sku)->exists();
    }

    private function clean(mixed $v): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $v));
    }

    /** Options may be plain strings or {value, label, active}. */
    private function optionValues(array $options): array
    {
        return collect($options)->map(function ($o) {
            if (! is_array($o)) return (string) $o;
            return ($o['active'] ?? true) ? ($o['label'] ?? $o['value'] ?? null) : null;
        })->filter()->values()->all();
    }
}

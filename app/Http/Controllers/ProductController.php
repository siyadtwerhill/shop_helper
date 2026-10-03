<?php

namespace App\Http\Controllers;

use App\Models\Blueprint;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Services\BarcodeGenerator;
use App\Services\BlueprintValidationBuilder;
use App\Services\BundleService;
use App\Services\InventoryMovementService;
use App\Services\ProductActivityLogger;
use App\Services\ProductStockService;
use App\Services\ProductVariantService;
use App\Services\QrCodeGenerator;
use App\Services\UnitConversionService;
use App\Traits\ResolvesShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use ResolvesShop;

    private const ALL_MODES = ['fixed', 'negotiable', 'price_range', 'wholesale'];

    public function __construct(
        private BarcodeGenerator $barcodeGenerator,
        private QrCodeGenerator $qrCodeGenerator,
        private BlueprintValidationBuilder $fieldRules,
        private InventoryMovementService $movements,
        private UnitConversionService $conversion,
        private ProductActivityLogger $activity,
        private ProductStockService $stock,
        private ProductVariantService $variantService,
        private BundleService $bundles,
    ) {}

    public function index(Request $request)
    {
        $shop = $this->shop($request);
        $user = $request->user();

        $query = Product::where('shop_owner_id', $shop->id)
            ->with(['category:id,name', 'brand:id,name', 'barcodes', 'baseUnit.unit', 'branch:id,name', 'blueprint:id,name',
                    'bundle.items.unit', 'bundle.items.component:id,current_stock'])
            ->withCount('variants')
            ->withMin('variants', 'selling_price')
            ->withMax('variants', 'selling_price');

        // Branch heads see their branch plus shop-wide products.
        if ($user->isBranchHead() && $user->staff?->branch_id) {
            $branchId = $user->staff->branch_id;
            $query->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'));
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('sku', 'like', "%{$term}%")
                  ->orWhereHas('barcodes', fn ($b) => $b->where('barcode', $term));
            });
        }
        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);
        if ($request->filled('brand_id')) $query->where('brand_id', $request->brand_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->boolean('exclude_bundles')) $query->where('stock_mode', '!=', 'from_components');

        $sortBy = in_array($request->sort_by, ['created_at', 'name', 'current_stock'], true) ? $request->sort_by : 'created_at';
        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $page = $query->orderBy($sortBy, $sortDir)->orderBy('id', 'desc')->paginate($perPage);
        $this->stock->hydrate($page->getCollection());
        return response()->json(['products' => $page]);
    }

    public function show(Request $request, Product $product)
    {
        $this->ownedProduct($request, $product);

        $product->load([
            'category:id,name', 'brand:id,name', 'barcodes', 'units.unit', 'baseUnit.unit',
            'bundle.items.component', 'bundle.items.unit',
            'blueprint:id,name,version,capabilities',
        ]);
        $this->stock->hydrate([$product]);

        return response()->json(['product' => $product]);
    }

    public function store(Request $request)
    {
        $shop = $this->shop($request);

        // multipart sends custom_fields as a JSON string
        if (is_string($request->input('custom_fields'))) {
            $request->merge(['custom_fields' => json_decode($request->input('custom_fields'), true) ?? []]);
        }
        // multipart also sends variants as a JSON string
        if (is_string($request->input('variants'))) {
            $request->merge(['variants' => json_decode($request->input('variants'), true) ?? []]);
        }
        // multipart also sends bundle_items as a JSON string
        if (is_string($request->input('bundle_items'))) {
            $request->merge(['bundle_items' => json_decode($request->input('bundle_items'), true) ?? []]);
        }

        $blueprint = $this->blueprintFor($request, $shop);
        $policy = $blueprint->pricing_policy ?? [];
        $caps = $blueprint->capabilities ?? [];

        $data = $request->validate(array_merge([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('shop_owner_id', $shop->id)],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')->where('shop_owner_id', $shop->id)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('shop_owner_id', $shop->id)],
            'image' => 'nullable|image|max:2048',
            'status' => 'nullable|in:active,inactive',
            'product_type' => 'nullable|in:neutral,variant,bundle',
            'blueprint_id' => 'nullable|integer',
            'scanned_barcode' => ['nullable', 'string', 'max:64',
                Rule::unique('product_barcodes', 'barcode')->where('shop_owner_id', $shop->id)],

            'pricing_mode' => ['nullable', Rule::in($this->allowedModes($policy))],
            'cost_price' => [($policy['cost_required'] ?? false) && $request->input('product_type') !== 'bundle' ? 'required' : 'nullable', 'numeric', 'min:0'],
            'selling_price' => 'required|numeric|min:0',
            'min_margin_percent' => 'nullable|numeric|min:0|max:99.99',
            'min_price' => 'nullable|numeric|min:0',
            'min_stock' => 'nullable|numeric|min:0',

            'unit_id' => 'required|exists:units,id',
            'conversion_factor' => 'nullable|numeric', // accepted from old clients; the base unit is always 1
            'opening_stock' => 'nullable|numeric|min:0',

            'wholesale_tiers' => 'nullable|array',
            'wholesale_tiers.*.min_quantity' => 'required_with:wholesale_tiers|numeric|min:0',
            'wholesale_tiers.*.max_quantity' => 'nullable|numeric',
            'wholesale_tiers.*.price' => 'required_with:wholesale_tiers|numeric|min:0',

            'bundle_price' => 'nullable|numeric|min:0|required_if:product_type,bundle',
            'bundle_items' => 'nullable|array|required_if:product_type,bundle',
            'bundle_items.*.component_product_id' => 'required_with:bundle_items|integer',
            'bundle_items.*.component_variant_id' => 'nullable|integer',
            'bundle_items.*.unit_id' => 'required_with:bundle_items|integer',
            'bundle_items.*.quantity' => ['required_with:bundle_items', 'regex:/^\d{1,11}(\.\d{1,4})?$/'],

            'variants' => 'nullable|array|max:200',
            'variants.*.attributes' => 'required_with:variants|array',
            'variants.*.sku' => ['nullable', 'regex:/^[A-Za-z0-9._-]{1,60}$/'],
            'variants.*.selling_price' => 'nullable|numeric|min:0',
            'variants.*.purchase_price' => 'nullable|numeric|min:0',
            'variants.*.barcode' => 'nullable|string|max:64',
            'variants.*.opening_stock' => 'nullable|numeric|min:0',
        ], $this->fieldRules->customFieldRules($blueprint)));

        $this->fieldRules->assertKnownKeys($blueprint, $data['custom_fields'] ?? []);

        $type = $data['product_type'] ?? 'neutral';
        abort_if($type === 'variant' && empty($caps['variants']), 422, 'This product type does not allow variants.');
        abort_if($type === 'bundle' && empty($caps['bundles']), 422, 'This product type does not allow bundles.');
        abort_if(! empty($data['variants']) && $type !== 'variant', 422, 'Variants can only be added to a variant product.');

        $decimals = (bool) ($caps['decimal_quantities'] ?? false);
        if (! empty($data['opening_stock'])) {
            abort_if($type !== 'neutral', 422, 'Set opening stock on each variant, or sell bundles from their components.');
            abort_if(! $decimals && floor((float) $data['opening_stock']) != (float) $data['opening_stock'], 422, 'This product type only allows whole quantities.');
        }

        $bundleRows = $type === 'bundle'
            ? $this->bundles->normalizeItems($shop->id, $data['bundle_items'] ?? [], $decimals)
            : [];

        $product = DB::transaction(function () use ($data, $shop, $request, $blueprint, $type, $policy, $bundleRows) {
            $user = $request->user();
            $branchId = $data['branch_id'] ?? null;
            if ($user->isBranchHead() && $user->staff?->branch_id) {
                $branchId = $user->staff->branch_id;
            }

            $product = Product::create([
                'shop_owner_id' => $shop->id,
                'branch_id' => $branchId,
                'category_id' => $data['category_id'] ?? null,
                'brand_id' => $data['brand_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'status' => $data['status'] ?? 'active',
                'product_type' => $type,
                'blueprint_id' => $blueprint->id,
                'blueprint_version' => $blueprint->version,
                'stock_mode' => $this->stockModeFor($type),
                'custom_fields' => $data['custom_fields'] ?? [],
                'pricing_mode' => $data['pricing_mode'] ?? ($policy['default_mode'] ?? 'fixed'),
                'cost_price' => $data['cost_price'] ?? null,
                'min_margin_percent' => $data['min_margin_percent'] ?? ($policy['min_margin_percent'] ?? null),
                'min_price' => $data['min_price'] ?? null,
                'min_stock' => $data['min_stock'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if ($request->hasFile('image')) {
                $product->update(['image_path' => $request->file('image')->store('products', 'public')]);
            }

            ProductBarcode::create([
                'shop_owner_id' => $shop->id,
                'product_id' => $product->id,
                'barcode' => $data['scanned_barcode'] ?? $this->barcodeGenerator->generate(),
                'type' => isset($data['scanned_barcode']) ? 'scanned' : 'auto_generated',
                'is_primary' => true,
            ]);

            try {
                if ($qrPath = $this->qrCodeGenerator->generateFor($product)) {
                    $product->update(['qr_path' => $qrPath]);
                }
            } catch (\Throwable $e) {
                report($e); // QR is optional, but never fail silently
            }

            // The base unit always has conversion factor 1.
            $unit = $product->units()->create([
                'unit_id' => $data['unit_id'],
                'conversion_factor' => 1,
                'selling_price' => $data['selling_price'],
                'purchase_price' => $data['cost_price'] ?? null,
                'is_base' => true,
                'is_sellable' => true,
                'is_purchasable' => true,
            ]);
            $product->update(['base_unit_id' => $unit->id]);

            if ($product->pricing_mode === 'wholesale') {
                foreach ($data['wholesale_tiers'] ?? [] as $tier) {
                    $product->priceRules()->create([
                        'unit_id' => $unit->id,
                        'min_quantity' => $tier['min_quantity'],
                        'max_quantity' => $tier['max_quantity'] ?? null,
                        'price' => $tier['price'],
                    ]);
                }
            }

            if (! empty($data['opening_stock']) && (float) $data['opening_stock'] > 0) {
                $this->movements->openingStock($product, $unit, $data['opening_stock'], $user->id);
            }

            if ($type === 'bundle') {
                $this->bundles->create($product, (string) ($data['bundle_price'] ?? $data['selling_price']), $bundleRows);
            }

            $this->activity->log($product, 'created', $user->id, description: "Product created ({$product->sku})");

            // Create variants supplied in the same request (all-or-nothing with the product row).
            if ($type === 'variant' && ! empty($data['variants'])) {
                $this->variantService->createMany($product, $data['variants'], $user->id);
            }

            return $product;
        });

        return response()->json([
            'message' => 'Product created.',
            'product' => $product->fresh(['category', 'brand', 'barcodes', 'baseUnit.unit']),
        ], 201);
    }

    public function update(Request $request, Product $product)
    {
        $shop = $this->shop($request);
        $this->ownedProduct($request, $product);

        if (is_string($request->input('custom_fields'))) {
            $request->merge(['custom_fields' => json_decode($request->input('custom_fields'), true) ?? []]);
        }

        $blueprint = $product->blueprint ?? $this->blueprintFor($request, $shop, useDefault: true);
        $policy = $blueprint->pricing_policy ?? [];

        $data = $request->validate(array_merge([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('shop_owner_id', $shop->id)],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')->where('shop_owner_id', $shop->id)],
            'image' => 'nullable|image|max:2048',
            'remove_image' => 'sometimes|boolean',
            'status' => 'sometimes|in:active,inactive',
            // a mode removed from the blueprint later stays valid for products already using it
            'pricing_mode' => ['sometimes', Rule::in(array_merge($this->allowedModes($policy), [$product->pricing_mode]))],
            'cost_price' => [($policy['cost_required'] ?? false) ? 'required' : 'nullable', 'numeric', 'min:0'],
            'min_margin_percent' => 'nullable|numeric|min:0|max:99.99',
            'min_price' => 'nullable|numeric|min:0',
            'min_stock' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'conversion_factor' => 'nullable|numeric', // ignored: the base unit is always 1
            'unit_id' => 'nullable|exists:units,id',
            'current_stock' => 'nullable|numeric|min:0',
            'opening_stock' => 'nullable|numeric|min:0',
        ], $this->fieldRules->customFieldRules($blueprint, partial: true)));

        $this->fieldRules->assertKnownKeys($blueprint, $data['custom_fields'] ?? []);

        $sellingPrice = $data['selling_price'] ?? null;
        $catalogUnitId = $data['unit_id'] ?? null;
        $desiredStock = $data['current_stock'] ?? $data['opening_stock'] ?? null;

        abort_if(
            $desiredStock !== null && $product->stock_mode !== 'own'
                && (float) $desiredStock !== (float) $product->getRawOriginal('current_stock'),
            422,
            'Stock for this product comes from its variants or components. Change it there.'
        );

        if ($request->hasFile('image')) {
            if ($product->image_path) Storage::disk('public')->delete($product->image_path);
            $data['image_path'] = $request->file('image')->store('products', 'public');
        } elseif ($request->boolean('remove_image') && $product->image_path) {
            Storage::disk('public')->delete($product->image_path);
            $data['image_path'] = null;
        }

        if (isset($data['custom_fields'])) {
            $data['custom_fields'] = array_merge($product->custom_fields ?? [], $data['custom_fields']);
        }

        unset($data['selling_price'], $data['conversion_factor'], $data['unit_id'],
              $data['current_stock'], $data['opening_stock'], $data['remove_image'], $data['image']);

        $decimals = (bool) (($blueprint->capabilities ?? [])['decimal_quantities'] ?? false);

        DB::transaction(function () use ($request, $product, $data, $sellingPrice, $catalogUnitId, $desiredStock, $decimals) {
            $user = $request->user();
            $stockBefore = (string) ($product->getRawOriginal('current_stock') ?? '0');

            $product->fill($data);
            $product->updated_by = $user->id;
            $product->save();

            $base = $product->units()->where('is_base', true)->first() ?? $product->units()->first();

            if (! $base && $catalogUnitId) {
                $base = $product->units()->create([
                    'unit_id' => $catalogUnitId, 'conversion_factor' => 1,
                    'selling_price' => $sellingPrice ?? 0,
                    'purchase_price' => $data['cost_price'] ?? $product->cost_price,
                    'is_base' => true, 'is_sellable' => true, 'is_purchasable' => true,
                ]);
            } elseif ($base && $catalogUnitId && (int) $base->unit_id !== (int) $catalogUnitId) {
                abort_if(
                    $product->inventoryMovements()->exists(),
                    422,
                    "The unit can't be changed after stock has moved. Add the new unit as an extra unit instead."
                );
                $existing = $product->units()->where('unit_id', $catalogUnitId)->first();
                if ($existing) {
                    $this->conversion->setBaseUnit($product, $existing);
                    $base = $existing->fresh();
                } else {
                    $base->update(['unit_id' => $catalogUnitId]);
                }
            }

            if (! $base) return;

            if ($desiredStock !== null) {
                $delta = bcsub((string) $desiredStock, $stockBefore, 4);
                if (bccomp($delta, '0', 4) !== 0) {
                    abort_if(! $decimals && floor((float) $delta) != (float) $delta, 422, 'This product type only allows whole quantities.');
                    $this->movements->adjust(
                        $product, $base, $delta,
                        referenceType: 'product_edit', createdBy: $user->id, note: 'Stock updated from product edit',
                    );
                }
            }

            $updates = [];
            if ($sellingPrice !== null) $updates['selling_price'] = $sellingPrice;
            if (array_key_exists('cost_price', $data)) $updates['purchase_price'] = $data['cost_price'];
            if ($updates) $base->update($updates);

            if ((int) $product->base_unit_id !== (int) $base->id) {
                $product->forceFill(['base_unit_id' => $base->id])->save();
            }

            foreach (array_keys(array_diff_key($product->getChanges(), ['updated_at' => 1, 'updated_by' => 1])) as $field) {
                $new = $product->getAttribute($field);
                $this->activity->log(
                    $product, 'updated', $user->id,
                    field: $field,
                    newValue: is_scalar($new) ? (string) $new : json_encode($new),
                );
            }
        });

        return response()->json([
            'message' => 'Product updated.',
            'product' => $product->fresh(['category', 'brand', 'barcodes', 'baseUnit.unit', 'units.unit']),
        ]);
    }

    public function destroy(Request $request, Product $product)
    {
        $this->ownedProduct($request, $product);

        $names = $product->usedInBundles()->with('bundle.product:id,name')->get()
            ->pluck('bundle.product.name')->filter()->unique()->values();
        abort_if($names->isNotEmpty(), 422, 'This product is used in bundles: ' . $names->join(', ') . '. Remove it from them first.');

        $product->update(['status' => 'archived', 'updated_by' => $request->user()->id]);
        $this->activity->log($product, 'archived', $request->user()->id);
        $product->delete();

        return response()->json(['message' => 'Product archived.']);
    }

    public function lookupByBarcode(Request $request)
    {
        $shop = $this->shop($request);
        $code = trim($request->validate(['barcode' => 'required|string'])['barcode']);

        $barcode = ProductBarcode::where('shop_owner_id', $shop->id)
            ->where('barcode', $code)
            ->with('product.category:id,name', 'product.brand:id,name', 'product.baseUnit.unit')
            ->first();

        if (! $barcode?->product) {
            return response()->json(['found' => false, 'barcode' => $code]);
        }

        return response()->json([
            'found' => true,
            'product' => $barcode->product,
            'variant_id' => $barcode->product_variant_id,
        ]);
    }

    /* ---------------------------------------------------------------- */

    private function blueprintFor(Request $request, $shop, bool $useDefault = false): Blueprint
    {
        $id = $useDefault ? null : $request->input('blueprint_id');
        $query = Blueprint::where('shop_owner_id', $shop->id)->where('status', 'active');
        $bp = $id ? $query->find($id) : $query->where('is_default', true)->first();

        abort_unless($bp, 422, $id
            ? 'That product type is not available.'
            : 'This shop has no default product type yet. Run: php artisan blueprints:backfill');

        return $bp;
    }

    private function allowedModes(array $policy): array
    {
        return ! empty($policy['allowed_modes']) ? $policy['allowed_modes'] : self::ALL_MODES;
    }

    private function stockModeFor(string $type): string
    {
        return match ($type) {
            'variant' => 'from_variants',
            'bundle' => 'from_components',
            default => 'own',
        };
    }

}

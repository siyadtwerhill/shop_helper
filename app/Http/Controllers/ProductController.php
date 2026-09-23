<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Services\BarcodeGenerator;
use App\Services\InventoryMovementService;
use App\Services\QrCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function __construct(
        private BarcodeGenerator $barcodeGenerator,
        private QrCodeGenerator $qrCodeGenerator,
    ) {}

    /**
     * Resolve the shop for the current user. Shop owners have it directly;
     * staff / branch heads reach it through their staff record.
     */
    private function shop(Request $request)
    {
        $user = $request->user();
        $shop = $user->shopOwner ?? $user->staff?->shopOwner;

        abort_unless($shop, 403, 'No shop is linked to this account.');

        return $shop;
    }

    /**
     * Route-model binding finds any product by id, so every action that
     * receives a {product} must confirm it belongs to the caller's shop.
     * 404 (not 403) so product ids of other shops aren't confirmed to exist.
     */
    private function ownedProduct(Request $request, Product $product): Product
    {
        abort_unless($product->shop_owner_id === $this->shop($request)->id, 404);

        return $product;
    }

    public function index(Request $request)
    {
        $shop = $this->shop($request);

        $query = Product::where('shop_owner_id', $shop->id)
            ->with(['category', 'brand', 'barcodes', 'baseUnit.unit'])
            // variant count + price range for the list's Price column
            ->withCount('variants')
            ->withMin('variants', 'selling_price')
            ->withMax('variants', 'selling_price');

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

        // Whitelisted sorting — never pass raw request input to orderBy().
        $sortBy = in_array($request->sort_by, ['created_at', 'name', 'current_stock'], true)
            ? $request->sort_by
            : 'created_at';
        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        return response()->json([
            'products' => $query
                ->orderBy($sortBy, $sortDir)
                ->orderBy('id', 'desc') // stable order for pagination
                ->paginate($perPage),
        ]);
    }

    public function show(Request $request, Product $product)
    {
        $this->ownedProduct($request, $product);

        return response()->json([
            'product' => $product->load(['category', 'brand', 'barcodes', 'units.unit', 'baseUnit.unit']),
        ]);
    }

    public function store(Request $request)
    {
        $shop = $this->shop($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'image' => 'nullable|image|max:2048',
            'status' => 'nullable|in:active,inactive,archived',
            'scanned_barcode' => 'nullable|string|max:64|unique:product_barcodes,barcode',

            // Pricing — essential, always sent
            'pricing_mode' => 'required|in:fixed,negotiable,price_range,wholesale',
            'cost_price' => 'nullable|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'min_margin_percent' => 'nullable|numeric|min:0|max:100',
            'min_price' => 'nullable|numeric|min:0',

            // Base unit — required by the schema, but the frontend fills this
            // with a sensible default unless the user opens Additional Settings.
            'unit_id' => 'required|exists:units,id',
            'conversion_factor' => 'nullable|numeric|min:0.0001',
            'opening_stock' => 'nullable|numeric|min:0',

            'wholesale_tiers' => 'nullable|array',
            'wholesale_tiers.*.min_quantity' => 'required_with:wholesale_tiers|numeric|min:0',
            'wholesale_tiers.*.max_quantity' => 'nullable|numeric',
            'wholesale_tiers.*.price' => 'required_with:wholesale_tiers|numeric|min:0',
        ]);

        $product = DB::transaction(function () use ($data, $shop, $request) {
            $product = Product::create([
                'shop_owner_id' => $shop->id,
                'category_id' => $data['category_id'] ?? null,
                'brand_id' => $data['brand_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'status' => $data['status'] ?? 'active',
                'pricing_mode' => $data['pricing_mode'],
                'cost_price' => $data['cost_price'] ?? null,
                'min_margin_percent' => $data['min_margin_percent'] ?? null,
                'min_price' => $data['min_price'] ?? null,
            ]);

            if ($request->hasFile('image')) {
                $product->update(['image_path' => $request->file('image')->store('products', 'public')]);
            }

            $barcodeValue = $data['scanned_barcode'] ?? $this->barcodeGenerator->generate();
            $barcodeType = isset($data['scanned_barcode']) ? 'scanned' : 'auto_generated';

            ProductBarcode::create([
                'product_id' => $product->id,
                'barcode' => $barcodeValue,
                'type' => $barcodeType,
                'is_primary' => true,
            ]);

            // QR encodes the SKU, generated after the product has an id/sku
            // Skip QR generation if library is not available
            try {
                $qrPath = $this->qrCodeGenerator->generateFor($product);
                if ($qrPath) {
                    $product->update(['qr_path' => $qrPath]);
                }
            } catch (\Exception $e) {
                // QR generation failed - continue without QR
            }

            // Every product needs a base unit to be sellable, so this is
            // folded into product creation rather than a later optional step.
            $unit = $product->units()->create([
                'unit_id' => $data['unit_id'],
                'conversion_factor' => $data['conversion_factor'] ?? 1,
                'selling_price' => $data['selling_price'],
                'purchase_price' => $data['cost_price'] ?? null,
                'is_base' => true,
                'is_sellable' => true,
                'is_purchasable' => true,
            ]);
            $product->update(['base_unit_id' => $unit->id]);

            foreach ($data['wholesale_tiers'] ?? [] as $tier) {
                $product->priceRules()->create([
                    'unit_id' => $unit->id,
                    'min_quantity' => $tier['min_quantity'],
                    'max_quantity' => $tier['max_quantity'] ?? null,
                    'price' => $tier['price'],
                ]);
            }

            if (!empty($data['opening_stock']) && (float) $data['opening_stock'] > 0) {
                app(\App\Services\InventoryMovementService::class)->openingStock(
                    $product, $unit, $data['opening_stock'], $request->user()->id
                );
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
        $this->ownedProduct($request, $product);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'image' => 'nullable|image|max:2048',
            'remove_image' => 'sometimes|boolean',
            'status' => 'sometimes|in:active,inactive,archived',
            'pricing_mode' => 'sometimes|in:fixed,negotiable,price_range,wholesale',
            'cost_price' => 'nullable|numeric|min:0',
            'min_margin_percent' => 'nullable|numeric|min:0|max:100',
            'min_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'conversion_factor' => 'nullable|numeric|min:0.0001',
            'unit_id' => 'nullable|exists:units,id',
            'current_stock' => 'nullable|numeric|min:0',
            'opening_stock' => 'nullable|numeric|min:0',
        ]);

        $sellingPrice = array_key_exists('selling_price', $data) ? $data['selling_price'] : null;
        $conversionFactor = array_key_exists('conversion_factor', $data) ? $data['conversion_factor'] : null;
        $catalogUnitId = $data['unit_id'] ?? null;
        $desiredStock = $data['current_stock'] ?? $data['opening_stock'] ?? null;

        if ($request->hasFile('image')) {
            if ($product->image_path) Storage::disk('public')->delete($product->image_path);
            $data['image_path'] = $request->file('image')->store('products', 'public');
        } elseif ($request->boolean('remove_image') && $product->image_path) {
            Storage::disk('public')->delete($product->image_path);
            $data['image_path'] = null;
        }

        unset(
            $data['selling_price'],
            $data['conversion_factor'],
            $data['unit_id'],
            $data['current_stock'],
            $data['opening_stock'],
            $data['remove_image'],
            $data['image'],
        );

        DB::transaction(function () use ($request, $product, $data, $sellingPrice, $conversionFactor, $catalogUnitId, $desiredStock) {
            $stockBefore = (string) ($product->getRawOriginal('current_stock') ?? '0');

            $product->update($data);

            $base = $product->units()->where('is_base', true)->first()
                ?? $product->units()->first();

            if (! $base && $catalogUnitId) {
                $base = $product->units()->create([
                    'unit_id' => $catalogUnitId,
                    'conversion_factor' => 1,
                    'selling_price' => $sellingPrice ?? 0,
                    'purchase_price' => $data['cost_price'] ?? $product->cost_price,
                    'is_base' => true,
                    'is_sellable' => true,
                    'is_purchasable' => true,
                ]);
                $product->update(['base_unit_id' => $base->id]);
            }

            // current_stock is stored in base units. Apply the delta before
            // conversion_factor changes, or 2.5× conversion would inflate the adjustment.
            if ($desiredStock !== null && $base) {
                $delta = bcsub((string) $desiredStock, $stockBefore, 4);
                if (bccomp($delta, '0', 4) !== 0) {
                    app(InventoryMovementService::class)->adjust(
                        $product,
                        $base,
                        $delta,
                        referenceType: 'product_edit',
                        createdBy: $request->user()->id,
                        note: 'Stock updated from product edit',
                    );
                    $base->refresh();
                }
            }

            if (! $base) {
                return;
            }

            $unitUpdates = [];
            if ($sellingPrice !== null) {
                $unitUpdates['selling_price'] = $sellingPrice;
            }
            if (array_key_exists('cost_price', $data)) {
                $unitUpdates['purchase_price'] = $data['cost_price'];
            }
            if ($conversionFactor !== null) {
                $unitUpdates['conversion_factor'] = $conversionFactor;
            }

            if ($catalogUnitId) {
                $existing = $product->units()->where('unit_id', $catalogUnitId)->first();
                if ($existing && (int) $existing->id !== (int) $base->id) {
                    $existing->update(array_merge($unitUpdates, ['is_base' => true]));
                    $base->update(['is_base' => false]);
                    $product->update(['base_unit_id' => $existing->id]);
                    return;
                }
                $unitUpdates['unit_id'] = $catalogUnitId;
            }

            if ($unitUpdates) {
                $base->update($unitUpdates);
            }

            if ($product->base_unit_id !== $base->id) {
                $product->update(['base_unit_id' => $base->id]);
            }
        });

        return response()->json([
            'message' => 'Product updated.',
            'product' => $product->fresh(['category', 'brand', 'barcodes', 'baseUnit.unit', 'units.unit']),
        ]);
    }

    /**
     * Archive, never hard-delete, once a product could plausibly have
     * transaction history — matches the spec's "archive instead of
     * destructive deletion" rule.
     */
    public function destroy(Request $request, Product $product)
    {
        $this->ownedProduct($request, $product);

        $product->update(['status' => 'archived']);
        $product->delete(); // soft delete

        return response()->json(['message' => 'Product archived.']);
    }

    /**
     * Camera scanner lookup — the barcode found by the scanner is
     * checked against product_barcodes; front end decides whether to
     * open the product or offer "Create Product" with the barcode pre-filled.
     */
    public function lookupByBarcode(Request $request)
    {
        $data = $request->validate(['barcode' => 'required|string']);

        $barcode = ProductBarcode::where('barcode', $data['barcode'])
            ->whereHas('product', fn ($q) => $q->where('shop_owner_id', $this->shop($request)->id))
            ->with('product.category', 'product.brand')
            ->first();

        if (!$barcode) {
            return response()->json(['found' => false, 'barcode' => $data['barcode']]);
        }

        return response()->json(['found' => true, 'product' => $barcode->product]);
    }
}

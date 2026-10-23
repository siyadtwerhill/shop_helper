<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Unit;
use App\Models\SaleItem;
use App\Services\BundleService;
use App\Services\ProductActivityLogger;
use App\Services\ProductStockService;
use App\Traits\ResolvesShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductBundleController extends Controller
{
    use ResolvesShop;

    public function __construct(
        private BundleService $bundles,
        private ProductStockService $stock,
        private ProductActivityLogger $activity,
    ) {}

    public function show(Request $request, Product $product)
    {
        $this->ownedProduct($request, $product);
        return $this->payload($product);
    }

    /** Turns an unused plain product into a bundle (new bundles are normally created via ProductController::store). */
    public function store(Request $request, Product $product)
    {
        $shop = $this->shop($request);
        $this->ownedProduct($request, $product);
        $this->authorizeAction($request, 'products.edit');
        abort_if($product->bundle()->exists(), 409, 'This product is already a bundle.');

        $data = $this->validated($request);
        $bundleUnit = $this->bundleUnit($shop->id);

        DB::transaction(function () use ($product, $data, $shop, $request, $bundleUnit) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $caps   = $locked->blueprint?->capabilities ?? [];
            abort_if(empty($caps['bundles']), 422, 'This product type does not allow bundles.');
            $this->bundles->assertConvertible($locked);

            $decimals = (bool) ($caps['decimal_quantities'] ?? true);
            $rows = $this->bundles->normalizeItems($shop->id, $data['items'], $decimals, $locked->id);

            $locked->forceFill(['product_type' => 'bundle', 'stock_mode' => 'from_components', 'pricing_mode' => 'fixed'])->save();
            $locked->units()->where('is_base', true)->firstOrFail()->update([
                'unit_id' => $bundleUnit->id,
                'conversion_factor' => 1,
            ]);
            $this->bundles->create($locked, (string) $data['bundle_price'], $rows);
            $this->activity->log($locked, 'bundle_created', $request->user()->id, description: count($rows) . ' components');
        });

        return $this->payload($product->fresh(), 201);
    }

    /** Price and/or components. Omit "items" to change only the price. */
    public function update(Request $request, Product $product)
    {
        $shop = $this->shop($request);
        $this->ownedProduct($request, $product);
        $this->authorizeAction($request, 'products.edit');
        $bundle = $product->bundle()->firstOrFail();

        $data = $this->validated($request, itemsRequired: false);
        $bundleUnit = $this->bundleUnit($shop->id);

        DB::transaction(function () use ($product, $bundle, $data, $shop, $request, $bundleUnit) {
            $locked   = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $decimals = (bool) (($locked->blueprint?->capabilities ?? [])['decimal_quantities'] ?? true);

            $rows = isset($data['items'])
                ? $this->bundles->normalizeItems($shop->id, $data['items'], $decimals, $locked->id)
                : null;

            $this->bundles->replace($bundle, (string) $data['bundle_price'], $rows);
            $locked->forceFill(['pricing_mode' => 'fixed'])->save();
            $locked->units()->where('is_base', true)->firstOrFail()->update([
                'unit_id' => $bundleUnit->id,
                'conversion_factor' => 1,
            ]);
            $this->activity->log($locked, 'bundle_updated', $request->user()->id);
        });

        return $this->payload($product->fresh());
    }

    public function destroy(Request $request, Product $product)
    {
        $this->ownedProduct($request, $product);
        $this->authorizeAction($request, 'products.delete');
        $bundle = $product->bundle()->firstOrFail();

        abort_if(
            SaleItem::where('product_id', $product->id)->exists(),
            422,
            "This bundle has been sold, so its product can't be removed. Mark it inactive instead."
        );

        DB::transaction(function () use ($product, $bundle, $request) {
            $bundle->items()->delete();
            $bundle->delete();
            $product->forceFill(['product_type' => 'neutral', 'stock_mode' => 'own'])->save();
            $product->update(['status' => 'archived', 'updated_by' => $request->user()->id]);
            $this->activity->log($product, 'archived', $request->user()->id);
            $product->delete();
        });

        return response()->json(null, 204);
    }

    private function validated(Request $request, bool $itemsRequired = true): array
    {
        return $request->validate([
            'bundle_price'                    => 'required|numeric|min:0',
            'items'                           => [$itemsRequired ? 'required' : 'sometimes', 'array', 'min:1', 'max:50'],
            'items.*.component_product_id'    => 'required|integer',
            'items.*.component_variant_id'    => 'nullable|integer',
            'items.*.unit_id'                 => 'required|integer',
            'items.*.quantity'                => ['required', 'regex:/^\d{1,11}(\.\d{1,4})?$/'],
        ]);
    }

    private function payload(Product $product, int $status = 200)
    {
        $bundle = $product->bundle()->with([
            'items.component:id,name,sku,status,stock_mode,cost_price,current_stock',
            'items.component.units.unit',
            'items.component.variants',
            'items.unit.unit',
            'items.variant',
        ])->first();

        if (! $bundle) return response()->json(['bundle' => null]);

        $components = $bundle->items->pluck('component')->filter();
        $this->stock->hydrate($components);
        $variantStocks = $this->stock->variantStocks(
            $components->flatMap(fn ($component) => $component->variants->pluck('id'))->all()
        );
        foreach ($components as $component) {
            foreach ($component->variants as $variant) {
                $variant->setAttribute('current_stock', $variantStocks[$variant->id] ?? '0.0000');
            }
        }

        return response()->json([
            'bundle'          => $bundle,
            'summary'         => $this->bundles->summary($bundle),
            'available_stock' => $this->stock->bundleAvailableFor($bundle),
        ], $status);
    }

    private function bundleUnit(int $shopId): Unit
    {
        $unit = Unit::availableTo($shopId)
            ->where('type', 'quantity')
            ->where(fn ($query) => $query->whereRaw('UPPER(symbol) = ?', ['DZ'])->orWhereRaw('LOWER(name) = ?', ['dozen']))
            ->orderByDesc('is_system')
            ->first();
        abort_if(! $unit, 422, 'The DZ (Dozen) unit is not available for bundles.');
        return $unit;
    }
}

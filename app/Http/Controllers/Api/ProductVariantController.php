<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryMovementService;
use App\Services\ProductStockService;
use App\Services\ProductVariantService;
use App\Traits\ResolvesShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductVariantController extends Controller
{
    use ResolvesShop;

    public function __construct(
        private ProductVariantService $variants,
        private ProductStockService $stock,
        private InventoryMovementService $movements,
    ) {}

    public function index(Request $request, Product $product)
    {
        $this->ownedProduct($request, $product);

        return $this->withStock($product->variants()->with('barcodes')->orderBy('id')->get());
    }

    /** Variant options (axes), price policy, and unassigned stock — for the variant screen. */
    public function axes(Request $request, Product $product)
    {
        $this->ownedProduct($request, $product);

        $bp = $this->variants->blueprintFor($product);

        return response()->json([
            'can_have_variants' => ! empty(($bp->capabilities ?? [])['variants'])
                && $product->stock_mode !== 'from_components',
            'axes'              => $this->variants->axesFor($product),
            'price_per_variant' => (bool) (($bp->pricing_policy ?? [])['price_per_variant'] ?? false),
            'unassigned_stock'  => $product->stock_mode === 'from_variants'
                ? $this->stock->unassignedFor($product) : '0.0000',
        ]);
    }

    /** Single variant — kept for the old single-variant form. */
    public function store(Request $request, Product $product)
    {
        $this->ownedProduct($request, $product);
        $this->authorizeAction($request, 'products.edit');

        $data = $request->validate([
            'sku'           => ['nullable', 'regex:/^[A-Za-z0-9._-]{1,60}$/'],
            'attributes'    => 'required|array|min:1',
            'selling_price' => 'nullable|numeric|min:0',
            'purchase_price'=> 'nullable|numeric|min:0',
            'barcode'       => 'nullable|string|max:64',
            'opening_stock' => 'nullable|numeric|min:0',
            'image'         => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('variants', 'public');
        }
        unset($data['image']);

        $result = $this->variants->createMany($product, [$data], $request->user()->id);

        return response()->json(
            $this->withStock(collect($result['created'])->load('barcodes'))->first(),
            201
        );
    }

    /** The grid: many variants in one request, all or nothing. */
    public function bulk(Request $request, Product $product)
    {
        $this->ownedProduct($request, $product);
        $this->authorizeAction($request, 'products.edit');

        $data = $request->validate([
            'skip_existing'              => 'sometimes|boolean',
            'variants'                   => 'required|array|min:1|max:200',
            'variants.*.attributes'      => 'required|array|min:1',
            'variants.*.sku'             => ['nullable', 'regex:/^[A-Za-z0-9._-]{1,60}$/'],
            'variants.*.selling_price'   => 'nullable|numeric|min:0',
            'variants.*.purchase_price'  => 'nullable|numeric|min:0',
            'variants.*.barcode'         => 'nullable|string|max:64',
            'variants.*.opening_stock'   => 'nullable|numeric|min:0',
        ]);

        $result = $this->variants->createMany(
            $product,
            $data['variants'],
            $request->user()->id,
            (bool) ($data['skip_existing'] ?? false)
        );

        return response()->json([
            'created' => $this->withStock(collect($result['created'])->load('barcodes')),
            'skipped' => $result['skipped'],
        ], 201);
    }

    public function update(Request $request, Product $product, $variantId)
    {
        $this->ownedProduct($request, $product);
        $this->authorizeAction($request, 'products.edit');
        $variant = ProductVariant::where('product_id', $product->id)->findOrFail($variantId);

        $data = $request->validate([
            'attributes'    => 'sometimes|array|min:1',
            'selling_price' => 'nullable|numeric|min:0',
            'purchase_price'=> 'nullable|numeric|min:0',
            'status'        => 'sometimes|in:active,inactive',
            'image'         => 'nullable|image|max:2048',
            'remove_image'  => 'sometimes|boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($variant->image_path) Storage::disk('public')->delete($variant->image_path);
            $data['image_path'] = $request->file('image')->store('variants', 'public');
        } elseif ($request->boolean('remove_image') && $variant->image_path) {
            Storage::disk('public')->delete($variant->image_path);
            $data['image_path'] = null;
        }
        unset($data['image'], $data['remove_image']);

        $variant = $this->variants->updateVariant($product, $variant, $data);

        return response()->json(
            $this->withStock(collect([$variant->load('barcodes')]))->first()
        );
    }

    public function destroy(Request $request, Product $product, $variantId)
    {
        $this->ownedProduct($request, $product);
        $this->authorizeAction($request, 'products.edit');
        $variant = ProductVariant::where('product_id', $product->id)->findOrFail($variantId);

        $this->variants->removeVariant($product, $variant, $request->user()->id);

        return response()->json(null, 204);
    }

    /** Moves stock counted before variants existed onto a specific variant. */
    public function assignStock(Request $request, Product $product, $variantId)
    {
        $this->ownedProduct($request, $product);
        $this->authorizeAction($request, 'products.edit');
        $variant = ProductVariant::where('product_id', $product->id)->findOrFail($variantId);

        $data = $request->validate([
            'quantity' => ['required', 'regex:/^\d{1,11}(\.\d{1,4})?$/'],
        ]);

        $this->movements->assignUnassignedStock($product, $variant, $data['quantity'], $request->user()->id);

        return response()->json([
            'unassigned_stock' => $this->stock->unassignedFor($product->fresh()),
        ]);
    }

    /** One grouped query instead of one per variant. */
    private function withStock($variants)
    {
        $stocks = $this->stock->variantStocks($variants->pluck('id')->all());
        foreach ($variants as $v) {
            $v->setAttribute('current_stock', $stocks[$v->id] ?? '0.0000');
        }
        return $variants->values();
    }
}

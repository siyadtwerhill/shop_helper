<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Services\ProductActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductVariantController extends Controller
{
    public function __construct(private ProductActivityLogger $logger)
    {
    }

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

    public function index(Request $request, Product $product)
    {
        $this->ownedProduct($request, $product);
        return $product->variants()->with('barcodes')->get();
    }

    public function store(Request $request, Product $product)
    {
        $this->ownedProduct($request, $product);

        $data = $request->validate([
            'sku' => 'required|string|max:100|unique:product_variants,sku',
            'attributes' => 'required|array|min:1', // e.g. {"color": "Black", "size": "S"}
            'selling_price' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'barcode' => 'nullable|string|max:100',
            'image' => 'nullable|image|max:2048',
        ]);

        $variant = DB::transaction(function () use ($product, $data, $request) {
            $variant = $product->variants()->create([
                'sku' => $data['sku'],
                'attributes' => $data['attributes'],
                'selling_price' => $data['selling_price'] ?? null,
                'purchase_price' => $data['purchase_price'] ?? null,
                'image_path' => $request->hasFile('image') ? $request->file('image')->store('variants', 'public') : null,
            ]);

            if (! empty($data['barcode'])) {
                ProductBarcode::create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'barcode' => $data['barcode'],
                    'type' => 'manual',
                    'is_primary' => true,
                ]);
            }

            return $variant;
        });

        $this->logger->log($product, 'variant_added', $request->user()->id, description: "Variant added ({$variant->display_label})");

        return response()->json($variant->load('barcodes'), 201);
    }

    public function update(Request $request, Product $product, $variantId)
    {
        $this->ownedProduct($request, $product);
        $variant = ProductVariant::where('product_id', $product->id)->findOrFail($variantId);

        $data = $request->validate([
            'attributes' => 'sometimes|array|min:1',
            'selling_price' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:active,inactive,archived',
            'image' => 'nullable|image|max:2048',
            'remove_image' => 'sometimes|boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($variant->image_path) Storage::disk('public')->delete($variant->image_path);
            $data['image_path'] = $request->file('image')->store('variants', 'public');
        } elseif ($request->boolean('remove_image') && $variant->image_path) {
            Storage::disk('public')->delete($variant->image_path);
            $data['image_path'] = null;
        }

        unset($data['remove_image'], $data['image']);

        $variant->update($data);

        return response()->json($variant);
    }

    public function destroy(Request $request, Product $product, $variantId)
    {
        $this->ownedProduct($request, $product);
        $variant = ProductVariant::where('product_id', $product->id)->findOrFail($variantId);

        if ($variant->image_path) {
            Storage::disk('public')->delete($variant->image_path);
        }

        $variant->delete();

        return response()->json(null, 204);
    }
}

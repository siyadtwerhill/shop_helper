<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Services\ProductActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    public function __construct(private ProductActivityLogger $logger)
    {
    }

    public function index(Product $product)
    {
        return $product->variants()->with('barcodes')->get();
    }

    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'sku' => 'required|string|max:100|unique:product_variants,sku',
            'attributes' => 'required|array|min:1', // e.g. {"color": "Black", "size": "S"}
            'selling_price' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'barcode' => 'nullable|string|max:100',
        ]);

        $variant = DB::transaction(function () use ($product, $data) {
            $variant = $product->variants()->create([
                'sku' => $data['sku'],
                'attributes' => $data['attributes'],
                'selling_price' => $data['selling_price'] ?? null,
                'purchase_price' => $data['purchase_price'] ?? null,
            ]);

            if (! empty($data['barcode'])) {
                ProductBarcode::create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'barcode' => $data['barcode'],
                    'type' => 'scanned',
                    'is_primary' => true,
                ]);
            }

            return $variant;
        });

        $this->logger->log($product, 'variant_added', $request->user()->id, description: "Variant added ({$variant->display_label})");

        return response()->json($variant->load('barcodes'), 201);
    }

    public function update(Request $request, Product $product, ProductVariant $variant)
    {
        abort_if($variant->product_id !== $product->id, 404);

        $data = $request->validate([
            'attributes' => 'sometimes|array|min:1',
            'selling_price' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:active,inactive,archived',
        ]);

        $variant->update($data);

        return response()->json($variant);
    }

    public function destroy(Product $product, ProductVariant $variant)
    {
        abort_if($variant->product_id !== $product->id, 404);

        $variant->delete();

        return response()->json(null, 204);
    }
}

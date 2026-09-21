<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductBundleController extends Controller
{
    public function __construct(private ProductActivityLogger $logger)
    {
    }

    public function show(Product $product)
    {
        return $product->bundle()->with('items.component', 'items.unit')->firstOrFail();
    }

    /** $product must already exist as a plain Product row — this turns it into a bundle. */
    public function store(Request $request, Product $product)
    {
        abort_if($product->bundle()->exists(), 409, 'This product is already a bundle.');

        $data = $request->validate([
            'bundle_price' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.component_product_id' => 'required|exists:products,id|different:product', // guard against self-reference below too
            'items.*.unit_id' => 'required|exists:product_units,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
        ]);

        foreach ($data['items'] as $item) {
            if ((int) $item['component_product_id'] === $product->id) {
                abort(422, 'A bundle cannot contain itself as a component.');
            }
        }

        $bundle = DB::transaction(function () use ($product, $data) {
            $bundle = $product->bundle()->create(['bundle_price' => $data['bundle_price']]);
            $bundle->items()->createMany($data['items']);

            return $bundle;
        });

        $this->logger->log($product, 'bundle_created', $request->user()->id, description: "Bundle created with {$bundle->items()->count()} components");

        return response()->json($bundle->load('items.component', 'items.unit'), 201);
    }

    public function update(Request $request, Product $product)
    {
        $bundle = $product->bundle()->firstOrFail();

        $data = $request->validate(['bundle_price' => 'required|numeric|min:0']);
        $bundle->update($data);

        return response()->json($bundle);
    }

    public function destroy(Product $product)
    {
        $bundle = $product->bundle()->firstOrFail();
        $bundle->delete(); // items cascade via FK

        return response()->json(null, 204);
    }
}

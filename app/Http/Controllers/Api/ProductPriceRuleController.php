<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPriceRule;
use Illuminate\Http\Request;

class ProductPriceRuleController extends Controller
{
    public function index(Product $product)
    {
        return $product->priceRules()->with('unit')->orderBy('unit_id')->orderBy('min_quantity')->get();
    }

    public function store(Request $request, Product $product)
    {
        abort_unless($request->user()->can('manage-price-rules'), 403);

        $data = $request->validate([
            'unit_id' => 'required|exists:product_units,id',
            'min_quantity' => 'required|numeric|min:0',
            'max_quantity' => 'nullable|numeric|gt:min_quantity',
            'price' => 'required|numeric|min:0',
        ]);

        $rule = $product->priceRules()->create($data);

        return response()->json($rule->load('unit'), 201);
    }

    public function update(Request $request, Product $product, ProductPriceRule $priceRule)
    {
        abort_unless($request->user()->can('manage-price-rules'), 403);
        abort_if($priceRule->product_id !== $product->id, 404);

        $data = $request->validate([
            'min_quantity' => 'sometimes|numeric|min:0',
            'max_quantity' => 'nullable|numeric',
            'price' => 'sometimes|numeric|min:0',
        ]);

        $priceRule->update($data);

        return response()->json($priceRule->load('unit'));
    }

    public function destroy(Request $request, Product $product, ProductPriceRule $priceRule)
    {
        abort_unless($request->user()->can('manage-price-rules'), 403);
        abort_if($priceRule->product_id !== $product->id, 404);

        $priceRule->delete();

        return response()->json(null, 204);
    }
}
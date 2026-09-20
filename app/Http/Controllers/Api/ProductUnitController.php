<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Services\UnitConversionService;
use Illuminate\Http\Request;

class ProductUnitController extends Controller
{
    public function __construct(private UnitConversionService $conversion)
    {
    }

    public function index(Product $product)
    {
        return $product->units()->with('unit')->get();
    }

    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'conversion_factor' => 'required|numeric|min:0.0001',
            'selling_price' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'is_base' => 'sometimes|boolean',
            'is_sellable' => 'sometimes|boolean',
            'is_purchasable' => 'sometimes|boolean',
        ]);

        $wantsBase = $data['is_base'] ?? false;
        unset($data['is_base']);

        /** @var ProductUnit $productUnit */
        $productUnit = $product->units()->create($data);

        if ($wantsBase || $product->units()->count() === 1) {
            $this->conversion->setBaseUnit($product, $productUnit);
            $productUnit->refresh();
        }

        return response()->json($productUnit->load('unit'), 201);
    }

    public function update(Request $request, Product $product, ProductUnit $productUnit)
    {
        abort_if($productUnit->product_id !== $product->id, 404);

        $data = $request->validate([
            'conversion_factor' => 'sometimes|numeric|min:0.0001',
            'selling_price' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'is_base' => 'sometimes|boolean',
            'is_sellable' => 'sometimes|boolean',
            'is_purchasable' => 'sometimes|boolean',
        ]);

        $wantsBase = $data['is_base'] ?? null;
        unset($data['is_base']);

        $productUnit->update($data);

        if ($wantsBase === true) {
            $this->conversion->setBaseUnit($product, $productUnit);
            $productUnit->refresh();
        }

        return response()->json($productUnit->load('unit'));
    }

    public function destroy(Product $product, ProductUnit $productUnit)
    {
        abort_if($productUnit->product_id !== $product->id, 404);
        abort_if($productUnit->is_base, 409, 'Cannot delete the base unit. Assign a new base unit first.');

        $productUnit->delete();

        return response()->json(null, 204);
    }
}
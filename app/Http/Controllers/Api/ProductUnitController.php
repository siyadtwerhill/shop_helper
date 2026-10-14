<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductBundleItem;
use App\Models\ProductPriceRule;
use App\Models\ProductUnit;
use App\Services\UnitConversionService;
use App\Traits\ResolvesShop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductUnitController extends Controller
{
    use ResolvesShop;

    public function __construct(private UnitConversionService $conversion) {}

    public function index(Request $request, Product $product)
    {
        $this->ownedProduct($request, $product);

        return $product->units()->with('unit')->get();
    }

    public function store(Request $request, Product $product)
    {
        $this->ownedProduct($request, $product);
        $this->authorizeAction($request, 'products.edit');

        $data = $request->validate([
            // Prevent duplicate unit on the same product (would 500 on the DB unique index).
            'unit_id' => ['required', 'exists:units,id',
                Rule::unique('product_units', 'unit_id')->where('product_id', $product->id)],
            'conversion_factor' => 'required|numeric|min:0.0001',
            'selling_price'  => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'is_base'        => 'sometimes|boolean',
            'is_sellable'    => 'sometimes|boolean',
            'is_purchasable' => 'sometimes|boolean',
        ]);

        $wantsBase = $data['is_base'] ?? false;
        unset($data['is_base']);

        $productUnit = $product->units()->create($data);

        if ($wantsBase || $product->units()->count() === 1) {
            $this->conversion->setBaseUnit($product, $productUnit);
            $productUnit->refresh();
        }

        return response()->json($productUnit->load('unit'), 201);
    }

    public function update(Request $request, Product $product, ProductUnit $productUnit)
    {
        $this->ownedProduct($request, $product);
        $this->authorizeAction($request, 'products.edit');
        abort_if($productUnit->product_id !== $product->id, 404);

        $data = $request->validate([
            'conversion_factor' => 'sometimes|numeric|min:0.0001',
            'selling_price'  => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'is_base'        => 'sometimes|boolean',
            'is_sellable'    => 'sometimes|boolean',
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

    public function destroy(Request $request, Product $product, ProductUnit $productUnit)
    {
        $this->ownedProduct($request, $product);
        $this->authorizeAction($request, 'products.edit');
        abort_if($productUnit->product_id !== $product->id, 404);
        abort_if($productUnit->is_base, 409, 'Cannot delete the base unit. Assign a new base unit first.');

        // Block deletion when this unit is referenced by ledger rows, bundle items, or price rules.
        $used = InventoryMovement::where('unit_id', $productUnit->id)->exists()
            || ProductBundleItem::where('unit_id', $productUnit->id)->exists()
            || ProductPriceRule::where('unit_id', $productUnit->id)->exists();

        abort_if($used, 422, 'This unit is used by stock movements, bundles or price rules and cannot be removed.');

        $productUnit->delete();

        return response()->json(null, 204);
    }
}

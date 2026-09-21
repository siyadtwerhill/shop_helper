<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SaleItem;
use App\Services\BundleSaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BundleSaleItemController extends Controller
{
    public function __construct(private BundleSaleService $bundleSales)
    {
    }

    /**
     * Sells N of a bundle: records one sale_items row for the bundle itself (at bundle_price,
     * no inventory movement) and deducts every component's stock via BundleSaleService.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'sale_id' => 'required|integer',
            'product_id' => 'required|exists:products,id',
            'unit_id' => 'required|exists:product_units,id',
            'quantity' => 'required|numeric|min:0.0001',
        ]);

        $product = Product::findOrFail($data['product_id']);
        $bundle = $product->bundle()->firstOrFail();
        $productUnit = $product->units()->findOrFail($data['unit_id']);

        $saleItem = DB::transaction(function () use ($data, $product, $bundle, $productUnit, $request) {
            $saleItem = SaleItem::create([
                'sale_id' => $data['sale_id'],
                'product_id' => $product->id,
                'unit_id' => $productUnit->id,
                'quantity' => (string) $data['quantity'],
                'base_quantity' => (string) $data['quantity'], // bundle unit is nominal; no conversion needed
                'unit_price' => (string) $bundle->bundle_price,
                'original_unit_price' => (string) $bundle->bundle_price,
                'discount_amount' => '0.00',
            ]);

            $this->bundleSales->deductComponents(
                $bundle,
                $data['quantity'],
                createdBy: $request->user()->id,
                referenceType: SaleItem::class,
                referenceId: $saleItem->id,
            );

            return $saleItem;
        });

        return response()->json($saleItem, 201);
    }
}

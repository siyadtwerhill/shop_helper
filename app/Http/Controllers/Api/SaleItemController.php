<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\SaleItemService;
use Illuminate\Http\Request;

class SaleItemController extends Controller
{
    public function __construct(private SaleItemService $service)
    {
    }

    /** POS "Scan Product" -> ring up a sale item (spec §4, §13). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'sale_id' => 'required|integer|exists:sales,id',
            'product_id' => 'required|exists:products,id',
            'unit_id' => 'required|exists:product_units,id',
            'quantity' => 'required|numeric|min:0.0001',
            'unit_price' => 'required|numeric|min:0',
            'original_unit_price' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
        ]);

        $product = Product::findOrFail($data['product_id']);
        $productUnit = $product->units()->findOrFail($data['unit_id']);

        $saleItem = $this->service->sell(
            $product,
            $productUnit,
            $data['quantity'],
            (string) $data['unit_price'],
            (int) $data['sale_id'],
            isset($data['original_unit_price']) ? (string) $data['original_unit_price'] : null,
            isset($data['discount_amount']) ? (string) $data['discount_amount'] : '0.00',
            $request->user()->id,
        );

        return response()->json($saleItem, 201);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductBarcode;
use Illuminate\Http\Request;

class ProductScannerController extends Controller
{
    /**
     * Camera scan flow (spec §4): open camera -> scan -> find product.
     * Found -> return the product. Not found -> 404 with the scanned code echoed back
     * so the frontend can prefill "Create Product" with it, per the spec's branch flow.
     */
    public function lookup(Request $request)
    {
        $data = $request->validate(['code' => 'required|string']);

        $barcode = ProductBarcode::where('barcode', $data['code'])->with('product')->first();

        if (! $barcode) {
            return response()->json([
                'found' => false,
                'scanned_code' => $data['code'],
            ], 404);
        }

        return response()->json([
            'found' => true,
            'product' => $barcode->product,
            'matched_via' => $barcode->type,
        ]);
    }
}
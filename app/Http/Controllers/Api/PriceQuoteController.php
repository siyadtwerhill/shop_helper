<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exceptions\MinimumMarginViolationException;
use App\Exceptions\PriceBelowMinimumException;
use App\Models\Product;
use App\Services\PricingService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PriceQuoteController extends Controller
{
    public function __construct(private PricingService $pricing)
    {
    }

    /** POS calls this before ringing up a sale item, to resolve what price to actually charge. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'unit_id' => 'required|exists:product_units,id',
            'quantity' => 'required|numeric|min:0.0001',
            'requested_price' => 'nullable|numeric|min:0',
        ]);

        $product = Product::findOrFail($data['product_id']);
        $productUnit = $product->units()->findOrFail($data['unit_id']);

        try {
            $result = $this->pricing->resolvePrice(
                $product,
                $productUnit,
                $data['quantity'],
                isset($data['requested_price']) ? (string) $data['requested_price'] : null,
                $request->user(),
            );
        } catch (PriceBelowMinimumException|MinimumMarginViolationException|InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exceptions\MinimumMarginViolationException;
use App\Exceptions\PriceBelowMinimumException;
use App\Models\Product;
use App\Services\PricingService;
use App\Services\ProductStockService;
use App\Traits\ResolvesShop;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PriceQuoteController extends Controller
{
    use ResolvesShop;

    public function __construct(private PricingService $pricing, private ProductStockService $stock)
    {
    }

    /** POS calls this before ringing up a sale item, to resolve what price to actually charge. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'unit_id' => 'required|exists:product_units,id',
            'quantity' => 'required|numeric|min:0.0001',
            'requested_price' => 'nullable|decimal:0,2|min:0',
            'variant_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
        ]);

        $product = $this->ownedProduct($request, Product::findOrFail($data['product_id']));
        $productUnit = $product->units()->findOrFail($data['unit_id']);
        $shop = $request->user()->shopOwner ?? $request->user()->staff?->shopOwner;
        abort_unless($shop, 403);
        $branchId = $this->stock->resolveBranchId($request, (int) $shop->id, required: true, requestedBranchId: $data['branch_id'] ?? null);
        $this->stock->assertVisibleAtBranch($product, $branchId, strict: true);

        $variant = null;
        if (! empty($data['variant_id'])) {
            abort_if($product->stock_mode !== 'from_variants', 422, 'This product does not use variants.');
            $variant = $product->variants()->where('status', 'active')->findOrFail($data['variant_id']);
        }

        $bundle = $product->bundle()->first();

        try {
            $result = $this->pricing->resolvePrice(
                $product,
                $productUnit,
                $data['quantity'],
                isset($data['requested_price']) ? (string) $data['requested_price'] : null,
                $request->user(),
                $variant,
                $bundle ? (string) $bundle->bundle_price : null,
            );
        } catch (PriceBelowMinimumException|MinimumMarginViolationException|InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}

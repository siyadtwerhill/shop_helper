<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleItemService;
use App\Services\ProductStockService;
use App\Services\PricingService;
use App\Exceptions\MinimumMarginViolationException;
use App\Exceptions\PriceBelowMinimumException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosSaleController extends Controller
{
    public function __construct(
        private SaleItemService $saleItems,
        private ProductStockService $stock,
        private PricingService $pricing,
    ) {
    }

    public function store(Request $request)
    {
        $shop = $request->user()->shopOwner ?? $request->user()->staff?->shopOwner;
        abort_unless($shop, 403);

        $data = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.unit_id'    => 'required|exists:product_units,id',
            'items.*.quantity'   => 'required|numeric|min:0.0001',
            'items.*.requested_price' => 'nullable|decimal:0,2|min:0',
            'items.*.variant_id' => 'nullable|integer',
            'discount_amount'    => 'nullable|numeric|min:0',
            'branch_id'          => 'nullable|integer',
        ]);

        $user = $request->user();
        $branchId = $this->stock->resolveBranchId(
            $request,
            (int) $shop->id,
            required: true,
            requestedBranchId: isset($data['branch_id']) ? (int) $data['branch_id'] : null,
        );

        // Wholesale tiers apply to the total quantity of a product/unit across
        // the sale, including when the cashier selected multiple variants.
        $pricingQuantities = [];
        foreach ($data['items'] as $item) {
            $key = $item['product_id'].':'.$item['unit_id'];
            $pricingQuantities[$key] = bcadd($pricingQuantities[$key] ?? '0.0000', (string) $item['quantity'], 4);
        }

        $sale = DB::transaction(function () use ($data, $shop, $request, $branchId, $pricingQuantities) {
            $sale = Sale::create([
                'shop_owner_id'   => $shop->id,
                'branch_id'       => $branchId,
                'total_discount'  => $data['discount_amount'] ?? 0,
                'subtotal'        => 0,
                'total_amount'    => 0,
                'status'          => 'completed',
                'created_by'      => $request->user()->id,
            ]);

            $subtotal = '0.00';
            foreach ($data['items'] as $itemIndex => $item) {
                // Scope to shop so cross-shop product IDs return 404 rather than 500.
                $product = Product::where('shop_owner_id', $shop->id)->findOrFail($item['product_id']);
                $this->stock->assertVisibleAtBranch($product, $branchId, strict: true);
                $unit    = $product->units()->findOrFail($item['unit_id']);

                // Resolve variant when sent.
                $variant = null;
                if (! empty($item['variant_id'])) {
                    abort_if(
                        $product->stock_mode !== 'from_variants',
                        422,
                        "\"{$product->name}\" does not use variants."
                    );
                    $variant = $product->variants()->where('status', 'active')->find($item['variant_id']);
                    abort_if(! $variant, 422, 'That variant is not available.');
                }

                abort_if(
                    $product->stock_mode === 'from_variants' && ! $variant && config('stock.require_variant'),
                    422,
                    "Choose a variant for \"{$product->name}\"."
                );

                $bundle = $product->bundle()->first();
                try {
                    $quote = $this->pricing->resolvePrice(
                        $product,
                        $unit,
                        $pricingQuantities[$item['product_id'].':'.$item['unit_id']],
                        isset($item['requested_price']) ? (string) $item['requested_price'] : null,
                        $request->user(),
                        $variant,
                        $bundle ? (string) $bundle->bundle_price : null,
                    );
                } catch (PriceBelowMinimumException|MinimumMarginViolationException|\InvalidArgumentException $e) {
                    throw ValidationException::withMessages([
                        "items.{$itemIndex}.requested_price" => [$e->getMessage()],
                    ]);
                }
                $unitPrice = $quote['price'];

                if ($bundle) {
                    $this->saleItems->sellBundle(
                        $product, $bundle, $unit, $item['quantity'],
                        $unitPrice, $sale->id, $request->user()->id,
                        branchId: $branchId,
                    );
                } else {
                    $this->saleItems->sell(
                        $product,
                        $unit,
                        $item['quantity'],
                        $unitPrice,
                        $sale->id,
                        createdBy: $request->user()->id,
                        variant: $variant,
                        branchId: $branchId,
                    );
                }

                $subtotal = bcadd($subtotal, bcmul((string) $item['quantity'], $unitPrice, 2), 2);
            }

            $discount = (string) ($data['discount_amount'] ?? '0.00');
            $sale->update(['subtotal' => $subtotal, 'total_amount' => bcsub($subtotal, $discount, 2)]);

            return $sale;
        });

        return response()->json($sale->load('items'), 201);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleItemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosSaleController extends Controller
{
    public function __construct(
        private SaleItemService $saleItems,
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
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.variant_id' => 'nullable|integer',
            'discount_amount'    => 'nullable|numeric|min:0',
        ]);

        $sale = DB::transaction(function () use ($data, $shop, $request) {
            $sale = Sale::create([
                'shop_owner_id'   => $shop->id,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'subtotal'        => 0,
                'total_amount'    => 0,
                'status'          => 'completed',
                'created_by'      => $request->user()->id,
            ]);

            $subtotal = '0.00';
            foreach ($data['items'] as $item) {
                // Scope to shop so cross-shop product IDs return 404 rather than 500.
                $product = Product::where('shop_owner_id', $shop->id)->findOrFail($item['product_id']);
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

                if ($bundle) {
                    $this->saleItems->sellBundle(
                        $product, $bundle, $unit, $item['quantity'],
                        (string) $item['unit_price'], $sale->id, $request->user()->id,
                    );
                } else {
                    $this->saleItems->sell(
                        $product,
                        $unit,
                        $item['quantity'],
                        (string) $item['unit_price'],
                        $sale->id,
                        createdBy: $request->user()->id,
                        variant: $variant,
                    );
                }

                $subtotal = bcadd($subtotal, bcmul((string) $item['quantity'], (string) $item['unit_price'], 2), 2);
            }

            $discount = (string) ($data['discount_amount'] ?? '0.00');
            $sale->update(['subtotal' => $subtotal, 'total_amount' => bcsub($subtotal, $discount, 2)]);

            return $sale;
        });

        return response()->json($sale->load('items'), 201);
    }
}

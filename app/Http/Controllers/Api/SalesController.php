<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\InventoryMovementService;
use App\Traits\ResolvesShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    use ResolvesShop;

    public function __construct(private InventoryMovementService $movements) {}

    public function overview(Request $request)
    {
        $shop = $this->shop($request);
        $this->authorizeAction($request, 'sales.view');

        $done = fn () => Sale::where('shop_owner_id', $shop->id)->where('status', 'completed');
        $today = now()->startOfDay();
        $month = now()->startOfMonth();

        $top = SaleItem::whereHas('sale', fn ($q) => $q->where('shop_owner_id', $shop->id)->where('status', 'completed'))
            ->with('product:id,name')
            ->selectRaw('product_id, SUM(base_quantity) as total_quantity, SUM(quantity * unit_price) as total_revenue')
            ->groupBy('product_id')->orderByDesc('total_quantity')->limit(5)->get();

        return response()->json([
            'metrics' => [
                'total_sales' => $done()->sum('total_amount'),
                'today_sales' => $done()->where('created_at', '>=', $today)->sum('total_amount'),
                'month_sales' => $done()->where('created_at', '>=', $month)->sum('total_amount'),
                'total_orders' => $done()->count(),
                'today_orders' => $done()->where('created_at', '>=', $today)->count(),
            ],
            'recent_sales' => $done()->with(['items.product:id,name', 'createdBy:id,name'])->latest('id')->take(10)->get(),
            'top_products' => $top,
        ]);
    }

    public function index(Request $request)
    {
        $shop = $this->shop($request);
        $this->authorizeAction($request, 'sales.view');

        return Sale::where('shop_owner_id', $shop->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->with(['items.product:id,name', 'createdBy:id,name'])
            ->latest('id')->paginate(20);
    }

    public function show(Request $request, Sale $sale)
    {
        abort_unless($sale->shop_owner_id === $this->shop($request)->id, 404);
        $this->authorizeAction($request, 'sales.view');

        return $sale->load(['items.product:id,name,sku', 'createdBy:id,name']);
    }

    public function void(Request $request, Sale $sale)
    {
        $shop = $this->shop($request);
        abort_unless($sale->shop_owner_id === $shop->id, 404);
        $this->authorizeAction($request, 'sales.delete');

        $data = $request->validate(['reason' => 'required|string|max:255']);

        DB::transaction(function () use ($sale, $data, $request) {
            $locked = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === 'voided', 409, 'This sale is already voided.');
            abort_if($locked->status !== 'completed', 422, 'Only completed sales can be voided.');

            $itemIds = $locked->items()->pluck('id');

            $sold = InventoryMovement::where('movement_type', 'sale')
                ->where(function ($q) use ($locked, $itemIds) {
                    $q->where(fn ($a) => $a->where('reference_type', SaleItem::class)->whereIn('reference_id', $itemIds))
                      ->orWhere(fn ($b) => $b->where('reference_type', Sale::class)->where('reference_id', $locked->id));
                })->get();

            foreach ($sold as $m) {
                $product = Product::withTrashed()->findOrFail($m->product_id);

                if ($product->stock_mode === 'from_components') {
                    continue;
                }

                $this->movements->saleReturn(
                    $product,
                    ProductUnit::findOrFail($m->unit_id),
                    ltrim((string) $m->quantity, '-'),
                    Sale::class,
                    $locked->id,
                    $request->user()->id,
                    $m->variant_id ? ProductVariant::withTrashed()->find($m->variant_id) : null,
                );
            }

            $locked->forceFill([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $request->user()->id,
                'void_reason' => $data['reason'],
            ])->save();
        });

        return response()->json($sale->fresh());
    }
}

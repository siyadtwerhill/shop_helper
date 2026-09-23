<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    /** Sales overview with metrics */
    public function overview(Request $request)
    {
        $shop = $request->user()->shopOwner ?? $request->user()->staff?->shopOwner;
        abort_unless($shop, 403);

        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        $sales = Sale::where('shop_owner_id', $shop->id)
            ->where('status', 'completed');

        $totalSales = $sales->clone()->sum('total_amount');
        $todaySales = $sales->clone()->where('created_at', '>=', $today)->sum('total_amount');
        $monthSales = $sales->clone()->where('created_at', '>=', $thisMonth)->sum('total_amount');
        $orderCount = $sales->clone()->count();
        $todayOrderCount = $sales->clone()->where('created_at', '>=', $today)->count();

        // Recent sales
        $recentSales = Sale::where('shop_owner_id', $shop->id)
            ->where('status', 'completed')
            ->with(['items.product:id,name', 'createdBy:id,name'])
            ->latest()
            ->take(10)
            ->get();

        // Top selling products
        $topProducts = SaleItem::whereHas('sale', fn ($q) => $q->where('shop_owner_id', $shop->id))
            ->with('product:id,name')
            ->selectRaw('product_id, SUM(quantity) as total_quantity, SUM(quantity * unit_price) as total_revenue')
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get();

        return response()->json([
            'metrics' => [
                'total_sales' => $totalSales,
                'today_sales' => $todaySales,
                'month_sales' => $monthSales,
                'total_orders' => $orderCount,
                'today_orders' => $todayOrderCount,
            ],
            'recent_sales' => $recentSales,
            'top_products' => $topProducts,
        ]);
    }

    /** List all sales */
    public function index(Request $request)
    {
        $shop = $request->user()->shopOwner ?? $request->user()->staff?->shopOwner;
        abort_unless($shop, 403);

        return Sale::where('shop_owner_id', $shop->id)
            ->with(['items.product:id,name', 'createdBy:id,name'])
            ->latest()
            ->paginate(20);
    }

    /** Show single sale */
    public function show(Request $request, Sale $sale)
    {
        $shop = $request->user()->shopOwner ?? $request->user()->staff?->shopOwner;
        abort_unless($shop, 403);
        abort_unless($sale->shop_owner_id === $shop->id, 403);

        return $sale->load(['items.product', 'createdBy']);
    }

    /** Create sale (manual entry) */
    public function store(Request $request)
    {
        $shop = $request->user()->shopOwner ?? $request->user()->staff?->shopOwner;
        abort_unless($shop, 403);

        $data = $request->validate([
            'total_amount' => 'required|numeric|min:0',
            'total_discount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string',
            'status' => 'nullable|in:pending,completed,cancelled',
        ]);

        $sale = Sale::create([
            'shop_owner_id' => $shop->id,
            'subtotal' => $data['total_amount'],
            'total_amount' => $data['total_amount'],
            'total_discount' => $data['total_discount'] ?? 0,
            'payment_method' => $data['payment_method'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'created_by' => $request->user()->id,
        ]);

        return response()->json($sale, 201);
    }

    /** Update sale */
    public function update(Request $request, Sale $sale)
    {
        $shop = $request->user()->shopOwner ?? $request->user()->staff?->shopOwner;
        abort_unless($shop, 403);
        abort_unless($sale->shop_owner_id === $shop->id, 403);

        $data = $request->validate([
            'total_amount' => 'nullable|numeric|min:0',
            'total_discount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string',
            'status' => 'nullable|in:pending,completed,cancelled',
        ]);

        $sale->update($data);

        return response()->json($sale);
    }

    /** Delete sale */
    public function destroy(Request $request, Sale $sale)
    {
        $shop = $request->user()->shopOwner ?? $request->user()->staff?->shopOwner;
        abort_unless($shop, 403);
        abort_unless($sale->shop_owner_id === $shop->id, 403);

        $sale->delete();

        return response()->json(null, 204);
    }
}

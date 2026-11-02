<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductBundle;
use App\Services\ProductStockService;
use App\Traits\ResolvesShop;
use Illuminate\Http\Request;

class BundleListController extends Controller
{
    use ResolvesShop;

    public function __construct(private ProductStockService $stock) {}

    /** Bundle definitions are part of the shared shop catalog. */
    public function index(Request $request)
    {
        $shop = $this->shop($request);
        $branchId = $this->stock->resolveBranchId($request, (int) $shop->id);

        $bundles = ProductBundle::whereHas('product', function ($q) use ($shop, $branchId) {
                $q->where('shop_owner_id', $shop->id)
                    ->when($branchId !== null, fn ($scope) => $scope->where(fn ($visible) => $visible->whereNull('branch_id')->orWhere('branch_id', $branchId)));
            })
            ->with([
                'product:id,name,sku,status,image_path,branch_id',
                'items.component:id,name,shop_owner_id,stock_mode',
                'items.unit.unit',
                'items.variant',
            ])
            ->latest('id')
            ->get();

        foreach ($bundles as $b) {
            $b->setAttribute('available_stock', $this->stock->bundleAvailableFor($b, $branchId));
        }

        return response()->json($bundles);
    }
}

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

    /** All bundles across the shop, with branch filtering for branch heads. */
    public function index(Request $request)
    {
        $shop = $this->shop($request);
        $user = $request->user();

        $bundles = ProductBundle::whereHas('product', function ($q) use ($shop, $user) {
                $q->where('shop_owner_id', $shop->id);
                if ($user->isBranchHead() && $user->staff?->branch_id) {
                    $bid = $user->staff->branch_id;
                    $q->where(fn ($b) => $b->where('branch_id', $bid)->orWhereNull('branch_id'));
                }
            })
            ->with([
                'product:id,name,sku,status,image_path,branch_id',
                'items.component:id,name,current_stock',
                'items.unit.unit',
                'items.variant',
            ])
            ->latest('id')
            ->get();

        foreach ($bundles as $b) {
            $b->setAttribute('available_stock', $this->stock->bundleAvailableFor($b));
        }

        return response()->json($bundles);
    }
}

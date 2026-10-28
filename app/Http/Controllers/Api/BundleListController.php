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

        $bundles = ProductBundle::whereHas('product', fn ($q) => $q->where('shop_owner_id', $shop->id))
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

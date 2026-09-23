<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductBundle;
use Illuminate\Http\Request;

class BundleListController extends Controller
{
    /** All bundles across the shop (design #4). */
    public function index(Request $request)
    {
        $shop = $request->user()->shopOwner ?? $request->user()->staff?->shopOwner;
        abort_unless($shop, 403);

        return ProductBundle::whereHas('product', fn ($q) => $q->where('shop_owner_id', $shop->id))
            ->with(['product:id,name,image_path,status', 'items.component:id,name', 'items.unit'])
            ->latest()
            ->get();
    }
}

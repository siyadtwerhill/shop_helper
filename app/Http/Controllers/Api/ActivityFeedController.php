<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductActivityLog;
use Illuminate\Http\Request;

class ActivityFeedController extends Controller
{
    /** Shop-wide activity feed (design #3) — every product's log, newest first. */
    public function index(Request $request)
    {
        $shop = $request->user()->shopOwner ?? $request->user()->staff?->shopOwner;
        abort_unless($shop, 403);

        $query = ProductActivityLog::whereHas('product', fn ($q) => $q->where('shop_owner_id', $shop->id))
            ->with(['product:id,name', 'user:id,name'])
            ->latest();

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        return $query->paginate(20);
    }
}

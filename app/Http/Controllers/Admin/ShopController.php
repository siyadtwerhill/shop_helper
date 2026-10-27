<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopOwner;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['status' => 'nullable|in:all,active,suspended']);

        $shops = ShopOwner::with(['user:id,name,email', 'plan:id,name', 'subscription:subscriptions.id,subscriptions.shop_owner_id,subscriptions.status'])
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'suspended', fn ($query) => $query->where('is_active', false))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search')->toString();
                $query->where(function ($query) use ($term) {
                    $query->where('shop_name', 'like', "%{$term}%")
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%"));
                });
            })
            ->latest()
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        $counts = ShopOwner::selectRaw(
            'COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active, SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as suspended'
        )->first();

        return response()->json([
            'shops' => $shops,
            'counts' => [
                'all' => (int) $counts->total,
                'active' => (int) $counts->active,
                'suspended' => (int) $counts->suspended,
            ],
        ]);
    }

    public function updateStatus(Request $request, ShopOwner $shop)
    {
        $data = $request->validate(['is_active' => 'required|boolean']);
        $shop->update($data);

        return response()->json([
            'shop' => $shop->load(['user:id,name,email', 'plan:id,name', 'subscription:subscriptions.id,subscriptions.shop_owner_id,subscriptions.status']),
            'message' => $shop->is_active ? 'Shop activated.' : 'Shop suspended.',
        ]);
    }
}

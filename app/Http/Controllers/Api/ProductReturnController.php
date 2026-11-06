<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\ReturnProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductReturnController extends Controller
{
    private ReturnProcessor $returnProcessor;

    public function __construct(ReturnProcessor $returnProcessor)
    {
        $this->returnProcessor = $returnProcessor;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $shopOwnerId = $user->shop_owner_id ?? $user->shopOwner?->id;

        $query = ProductReturn::with(['sale', 'customer', 'branch', 'processedBy'])
            ->where('shop_owner_id', $shopOwnerId);

        // Branch isolation for non-owner users
        if (!$user->hasRole('Shop Owner') && $user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }
        if ($request->has('q')) {
            $query->where('return_number', 'like', '%' . $request->q . '%');
        }

        $returns = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($returns);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $shopOwnerId = $user->shop_owner_id ?? $user->shopOwner?->id;

        $return = ProductReturn::with(['sale', 'sale.items', 'items', 'items.product', 'items.variant', 'customer', 'branch', 'processedBy', 'refunds'])
            ->where('shop_owner_id', $shopOwnerId)
            ->where('id', $id)
            ->firstOrFail();

        // Branch isolation check
        if (!$user->hasRole('Shop Owner') && $user->branch_id && $return->branch_id != $user->branch_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($return);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|exists:sale_items,id',
            'items.*.quantity_returned' => 'required|numeric|min:0.01',
            'items.*.reason' => 'nullable|string',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $shopOwnerId = $user->shop_owner_id ?? $user->shopOwner?->id;

        $sale = Sale::where('shop_owner_id', $shopOwnerId)
            ->where('id', $request->sale_id)
            ->firstOrFail();

        // Branch isolation check
        if (!$user->hasRole('Shop Owner') && $user->branch_id && $sale->branch_id != $user->branch_id) {
            return response()->json(['message' => 'Unauthorized to return from this sale'], 403);
        }

        try {
            $return = DB::transaction(function () use ($request, $sale, $user) {
                return $this->returnProcessor->processReturn($sale, $request->items, $request->reason, $request->notes, $user);
            });

            return response()->json($return->load('items', 'sale'), 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,reject,voided',
            'notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $shopOwnerId = $user->shop_owner_id ?? $user->shopOwner?->id;

        $return = ProductReturn::where('shop_owner_id', $shopOwnerId)
            ->where('id', $id)
            ->firstOrFail();

        // Branch isolation check
        if (!$user->hasRole('Shop Owner') && $user->branch_id && $return->branch_id != $user->branch_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Business rules
        if ($return->status === 'voided') {
            return response()->json(['message' => 'Cannot modify a voided return'], 400);
        }
        if ($return->status === 'approved' && $request->status === 'pending') {
            return response()->json(['message' => 'Cannot revert approved return to pending'], 400);
        }

        try {
            $return = DB::transaction(function () use ($return, $request, $user) {
                return $this->returnProcessor->updateReturnStatus($return, $request->status, $request->notes, $user);
            });

            return response()->json($return->load('items', 'refunds'));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $shopOwnerId = $user->shop_owner_id ?? $user->shopOwner?->id;

        $return = ProductReturn::where('shop_owner_id', $shopOwnerId)
            ->where('id', $id)
            ->firstOrFail();

        // Branch isolation check
        if (!$user->hasRole('Shop Owner') && $user->branch_id && $return->branch_id != $user->branch_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Can only delete pending returns
        if ($return->status !== 'pending') {
            return response()->json(['message' => 'Can only delete pending returns'], 400);
        }

        $return->delete();

        return response()->json(['message' => 'Return deleted']);
    }
}

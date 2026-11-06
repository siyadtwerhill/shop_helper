<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductReturn;
use App\Models\Refund;
use App\Services\RefundProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RefundController extends Controller
{
    private RefundProcessor $refundProcessor;

    public function __construct(RefundProcessor $refundProcessor)
    {
        $this->refundProcessor = $refundProcessor;
    }

    public function index(Request $request, $returnId)
    {
        $user = $request->user();
        $shopOwnerId = $user->shop_owner_id ?? $user->shopOwner?->id;

        $return = ProductReturn::where('shop_owner_id', $shopOwnerId)
            ->where('id', $returnId)
            ->firstOrFail();

        // Branch isolation check
        if (!$user->hasRole('Shop Owner') && $user->branch_id && $return->branch_id != $user->branch_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $refunds = Refund::with(['processedBy'])
            ->where('product_return_id', $returnId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($refunds);
    }

    public function store(Request $request, $returnId)
    {
        $request->validate([
            'refund_method' => 'required|in:cash,card,mobile_wallet,bank_transfer',
            'reference_number' => 'nullable|string',
        ]);

        $user = $request->user();
        $shopOwnerId = $user->shop_owner_id ?? $user->shopOwner?->id;

        $return = ProductReturn::where('shop_owner_id', $shopOwnerId)
            ->where('id', $returnId)
            ->firstOrFail();

        // Branch isolation check
        if (!$user->hasRole('Shop Owner') && $user->branch_id && $return->branch_id != $user->branch_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Business rules
        if ($return->status !== 'approved') {
            return response()->json(['message' => 'Return must be approved before refunding'], 400);
        }
        if ($return->refunds()->where('status', 'completed')->exists()) {
            return response()->json(['message' => 'Refund already processed for this return'], 400);
        }

        try {
            $refund = DB::transaction(function () use ($return, $request, $user) {
                return $this->refundProcessor->processRefund($return, $request->refund_method, $request->reference_number, $user);
            });

            return response()->json($refund->load('processedBy'), 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $shopOwnerId = $user->shop_owner_id ?? $user->shopOwner?->id;

        $refund = Refund::with(['productReturn', 'productReturn.sale', 'processedBy'])
            ->where('shop_owner_id', $shopOwnerId)
            ->where('id', $id)
            ->firstOrFail();

        // Branch isolation check
        if (!$user->hasRole('Shop Owner') && $user->branch_id && $refund->branch_id != $user->branch_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($refund);
    }
}

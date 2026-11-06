<?php

namespace App\Services;

use App\Models\ProductReturn;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

class RefundProcessor
{
    public function processRefund(ProductReturn $return, string $refundMethod, ?string $referenceNumber, $user): Refund
    {
        $shopOwnerId = $return->shop_owner_id;
        $branchId = $return->branch_id;

        // Generate refund number
        $refundNumber = $this->generateRefundNumber($shopOwnerId, $branchId);

        // Create refund record
        $refund = Refund::create([
            'shop_owner_id' => $shopOwnerId,
            'branch_id' => $branchId,
            'product_return_id' => $return->id,
            'refund_number' => $refundNumber,
            'refund_method' => $refundMethod,
            'refund_amount' => $return->total_refund_amount,
            'status' => 'processing',
            'reference_number' => $referenceNumber,
            'processed_by' => $user->id,
            'processed_at' => now(),
        ]);

        // Simulate refund processing (in real implementation, this would call payment gateway)
        // For now, we'll mark it as completed
        $refund->status = 'completed';
        $refund->save();

        return $refund;
    }

    private function generateRefundNumber(int $shopOwnerId, int $branchId): string
    {
        $date = now()->format('Ymd');

        // Use a simple timestamp-based number for refunds
        // In production, you might want a sequence table like returns
        $timestamp = now()->format('His') . rand(100, 999);

        return sprintf('RFD-B%d-%s-%s', $branchId, $date, $timestamp);
    }
}

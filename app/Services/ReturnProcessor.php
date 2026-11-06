<?php

namespace App\Services;

use App\Models\ProductReturn;
use App\Models\ReturnItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\InventoryMovement;
use App\Services\InventoryMovementService;
use App\Services\ProductStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lock;

class ReturnProcessor
{
    public function __construct(
        private InventoryMovementService $movements,
        private ProductStockService $stock,
    ) {
    }

    public function processReturn(Sale $sale, array $items, ?string $reason, ?string $notes, $user): ProductReturn
    {
        $shopOwnerId = $sale->shop_owner_id;
        $branchId = $sale->branch_id;

        // Generate return number with branch-specific sequence
        $returnNumber = $this->generateReturnNumber($shopOwnerId, $branchId);

        // Calculate total refund amount
        $totalRefundAmount = 0;
        $returnItems = [];

        foreach ($items as $item) {
            $saleItem = SaleItem::where('sale_id', $sale->id)
                ->where('id', $item['sale_item_id'])
                ->firstOrFail();

            // Validate quantity
            $quantityReturned = (float) $item['quantity_returned'];
            if ($quantityReturned > $saleItem->quantity) {
                throw new \Exception("Cannot return more than purchased quantity for item: {$saleItem->product->name}");
            }

            // Check already returned quantity
            $alreadyReturned = ReturnItem::where('sale_item_id', $saleItem->id)
                ->whereHas('productReturn', fn($q) => $q->where('status', '!=', 'voided'))
                ->sum('quantity_returned');

            if ($quantityReturned + $alreadyReturned > $saleItem->quantity) {
                throw new \Exception("Exceeds returnable quantity for item: {$saleItem->product->name}");
            }

            // Calculate refund amount (use original sale price)
            $unitPrice = $saleItem->unit_price;
            $refundAmount = $quantityReturned * $unitPrice;
            $totalRefundAmount += $refundAmount;

            $returnItems[] = [
                'sale_item_id' => $saleItem->id,
                'product_id' => $saleItem->product_id,
                'variant_id' => $saleItem->variant_id,
                'quantity_returned' => $quantityReturned,
                'unit_price' => $unitPrice,
                'refund_amount' => $refundAmount,
                'reason' => $item['reason'] ?? null,
            ];
        }

        // Create return record
        $return = ProductReturn::create([
            'shop_owner_id' => $shopOwnerId,
            'branch_id' => $branchId,
            'sale_id' => $sale->id,
            'customer_id' => $sale->customer_id,
            'return_number' => $returnNumber,
            'status' => 'pending',
            'reason' => $reason,
            'notes' => $notes,
            'total_refund_amount' => $totalRefundAmount,
        ]);

        // Create return items
        foreach ($returnItems as $item) {
            ReturnItem::create([
                'product_return_id' => $return->id,
                ...$item,
            ]);
        }

        return $return;
    }

    public function updateReturnStatus(ProductReturn $return, string $status, ?string $notes, $user): ProductReturn
    {
        $return->status = $status;
        $return->notes = $notes ?? $return->notes;
        $return->processed_by = $user->id;
        $return->processed_at = now();

        if ($status === 'approved') {
            // Add stock back to inventory
            $this->restockItems($return, $user);
        } elseif ($status === 'voided') {
            // Remove stock if it was previously approved
            $this->removeStockForVoidedReturn($return);
        }

        $return->save();

        return $return;
    }

    private function restockItems(ProductReturn $return, $user): void
    {
        $return->loadMissing(['items.saleItem.unit', 'items.product', 'items.variant']);

        foreach ($return->items as $item) {
            // Restock through the canonical movement service: it records the
            // 'sale_return' ledger row with the correct unit_id + base_quantity
            // and updates branch stock in a single step (matches the sale path).
            $this->movements->saleReturn(
                $item->product,
                $item->saleItem->unit,
                $item->quantity_returned,
                referenceType: ProductReturn::class,
                referenceId: $return->id,
                createdBy: $user->id,
                variant: $item->variant,
                branchId: $return->branch_id,
            );
        }
    }

    private function removeStockForVoidedReturn(ProductReturn $return): void
    {
        // Find and reverse the 'sale_return' movements recorded on approval.
        $movements = InventoryMovement::with('product')
            ->where('reference_id', $return->id)
            ->where('reference_type', ProductReturn::class)
            ->where('movement_type', 'sale_return')
            ->get();

        foreach ($movements as $movement) {
            // Reverse the base-unit stock that approval added back in.
            $this->stock->applyMovement(
                $movement->product,
                (int) $movement->branch_id,
                bcmul($movement->base_quantity, '-1', 4),
            );

            $movement->delete();
        }
    }

    private function generateReturnNumber(int $shopOwnerId, int $branchId): string
    {
        $date = now()->format('Ymd');

        // Lock the sequence row to prevent concurrent duplicates
        $sequence = DB::transaction(function () use ($shopOwnerId, $branchId, $date) {
            $sequence = DB::table('return_sequences')
                ->where('shop_owner_id', $shopOwnerId)
                ->where('branch_id', $branchId)
                ->where('sequence_date', $date)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                $sequenceId = DB::table('return_sequences')->insertGetId([
                    'shop_owner_id' => $shopOwnerId,
                    'branch_id' => $branchId,
                    'sequence_date' => $date,
                    'last_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $sequence = (object) ['id' => $sequenceId, 'last_number' => 0];
            }

            $nextNumber = $sequence->last_number + 1;

            DB::table('return_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'last_number' => $nextNumber,
                    'updated_at' => now(),
                ]);

            return $nextNumber;
        });

        return sprintf('RET-B%d-%s-%06d', $branchId, $date, $sequence);
    }
}

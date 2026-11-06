<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\Receipt;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ReceiptIssuer
{
    public function issue(Sale $sale, User $issuer, string $type = 'sale'): Receipt
    {
        $existing = Receipt::where('shop_owner_id', $sale->shop_owner_id)->where('sale_id', $sale->id)->where('receipt_type', $type)->first();
        if ($existing) return $existing;

        $branch = Branch::where('shop_owner_id', $sale->shop_owner_id)->whereKey($sale->branch_id)->lockForUpdate()->firstOrFail();
        $issuedAt = $type === 'sale' ? ($sale->created_at ?? now()) : now();
        $date = Carbon::parse($issuedAt)->toDateString();
        $sequence = DB::table('receipt_sequences')->where('shop_owner_id', $sale->shop_owner_id)->where('branch_id', $branch->id)->where('sequence_date', $date)->lockForUpdate()->first();
        if (! $sequence) {
            DB::table('receipt_sequences')->insert(['shop_owner_id' => $sale->shop_owner_id, 'branch_id' => $branch->id, 'sequence_date' => $date, 'last_number' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $number = 1;
        } else {
            $number = (int) $sequence->last_number + 1;
            DB::table('receipt_sequences')->where('id', $sequence->id)->update(['last_number' => $number, 'updated_at' => now()]);
        }

        $sale->loadMissing(['shopOwner', 'createdBy', 'items.product', 'items.unit.unit']);
        $items = $sale->items->map(function ($item) {
            $variant = $item->variant_id ? ProductVariant::withTrashed()->find($item->variant_id) : null;
            $quantity = (float) $item->quantity;
            $unitPrice = (float) $item->unit_price;
            $lineDiscount = (float) $item->discount_amount;
            return [
                'product_name' => $item->product?->name ?? 'Product',
                'sku' => $item->product?->sku,
                'variant' => $variant?->display_label,
                'quantity' => $item->quantity,
                'unit' => $item->unit?->unit?->symbol ?: ($item->unit?->unit?->name ?: 'unit'),
                'unit_price' => $item->unit_price,
                'discount' => $item->discount_amount,
                'line_total' => number_format(max(0, $quantity * $unitPrice - $lineDiscount), 2, '.', ''),
            ];
        })->values()->all();
        $original = $type === 'refund' ? Receipt::where('shop_owner_id', $sale->shop_owner_id)->where('sale_id', $sale->id)->where('receipt_type', 'sale')->value('receipt_number') : null;
        $snapshot = [
            'document_type' => $type,
            'original_receipt_number' => $original,
            'shop' => ['name' => $sale->shopOwner?->shop_name ?? 'Shop', 'address' => $sale->shopOwner?->address, 'phone' => $sale->shopOwner?->phone],
            'branch' => ['name' => $branch->name, 'address' => $branch->address, 'phone' => $branch->phone],
            'sale' => ['id' => $sale->id, 'status_at_issue' => $sale->status, 'issued_at' => Carbon::parse($issuedAt)->toIso8601String(), 'cashier' => $sale->createdBy?->name ?? $issuer->name, 'payment_method' => $sale->payment_method ?? 'cash'],
            'items' => $items,
            'subtotal' => $sale->subtotal,
            'discount' => $sale->total_discount,
            'tax' => '0.00',
            'total' => $sale->total_amount,
            'amount_paid' => $sale->total_amount,
            'amount_received' => $sale->amount_received ?? $sale->total_amount,
            'change' => number_format(max(0, (float) ($sale->amount_received ?? $sale->total_amount) - (float) $sale->total_amount), 2, '.', ''),
            'refund_reason' => $type === 'refund' ? $sale->void_reason : null,
            'currency' => 'MMK',
            'footer' => 'Thank you for shopping with us!',
        ];

        return Receipt::create([
            'shop_owner_id' => $sale->shop_owner_id,
            'branch_id' => $branch->id,
            'sale_id' => $sale->id,
            'receipt_number' => sprintf('RCT-B%03d-%s-%06d', $branch->id, Carbon::parse($issuedAt)->format('Ymd'), $number),
            'receipt_type' => $type,
            'status' => 'issued',
            'issued_at' => $issuedAt,
            'issued_by' => $issuer->id,
            'document_snapshot' => $snapshot,
        ]);
    }
}
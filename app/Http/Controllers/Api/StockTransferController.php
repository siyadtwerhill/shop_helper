<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Staff;
use App\Services\StockLocationService;
use App\Services\UnitConversionService;
use App\Traits\ResolvesShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferController extends Controller
{
    use ResolvesShop;

    public function index(Request $request, StockLocationService $locations)
    {
        $shop = $this->shop($request);
        $locations->ensureFeatureEnabled($shop);
        $query = StockTransfer::where('shop_owner_id', $shop->id)
            ->with(['items.product:id,name,sku', 'items.variant:id,attributes', 'items.productUnit.unit:id,name,symbol']);
        if ($request->user()->isBranchHead()) {
            $branchId = (int) $request->user()->staff?->branch_id;
            $query->where(fn ($q) => $q
                ->where(fn ($s) => $s->where('source_type', 'branch')->where('source_id', $branchId))
                ->orWhere(fn ($d) => $d->where('destination_type', 'branch')->where('destination_id', $branchId)));
        }
        return $query->latest('id')->paginate(25);
    }

    public function store(Request $request, StockLocationService $locations)
    {
        $shop = $this->shop($request);
        $locations->ensureFeatureEnabled($shop);
        $data = $request->validate([
            'source_type' => 'required|in:branch,warehouse', 'source_id' => 'required|integer',
            'destination_type' => 'required|in:branch,warehouse', 'destination_id' => 'required|integer',
            'note' => 'nullable|string|max:2000', 'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer', 'items.*.product_unit_id' => 'required|integer',
            'items.*.variant_id' => 'nullable|integer', 'items.*.quantity' => ['required', 'regex:/^\\d{1,11}(\\.\\d{1,4})?$/', 'gt:0'],
        ]);
        abort_if($data['source_type'] === $data['destination_type'] && (int) $data['source_id'] === (int) $data['destination_id'], 422, 'Choose two different stock locations.');
        $locations->validateLocation($request, $shop, $data['source_type'], (int) $data['source_id']);
        $locations->validateLocation($request, $shop, $data['destination_type'], (int) $data['destination_id']);
        if ($request->user()->role === 'staff') {
            $assignedBranchId = (int) $request->user()->staff?->branch_id;
            $touchesAssignedBranch = ($data['source_type'] === 'branch' && (int) $data['source_id'] === $assignedBranchId)
                || ($data['destination_type'] === 'branch' && (int) $data['destination_id'] === $assignedBranchId);
            abort_unless($touchesAssignedBranch, 403, 'A Branch Head transfer must involve the assigned branch.');
        }

        $transfer = DB::transaction(function () use ($request, $shop, $data): StockTransfer {
            $transfer = StockTransfer::create([
                'shop_owner_id' => $shop->id, 'source_type' => $data['source_type'], 'source_id' => $data['source_id'],
                'destination_type' => $data['destination_type'], 'destination_id' => $data['destination_id'],
                'status' => 'draft', 'note' => $data['note'] ?? null, 'created_by' => $request->user()->id,
            ]);
            foreach ($data['items'] as $row) {
                $product = Product::where('shop_owner_id', $shop->id)->find($row['product_id']);
                if (! $product) throw ValidationException::withMessages(['items' => ['A selected product does not belong to this shop.']]);
                if (! $product->track_stock) throw ValidationException::withMessages(['items' => ["Stock tracking is disabled for {$product->name}."]]);
                if ($product->stock_mode === 'from_components') throw ValidationException::withMessages(['items' => ["Bundle stock is calculated from its components: {$product->name}."]]);
                foreach ([[$data['source_type'], (int) $data['source_id']], [$data['destination_type'], (int) $data['destination_id']]] as [$locationType, $locationId]) {
                    if ($locationType === 'branch' && $product->branch_id !== null && (int) $product->branch_id !== $locationId) {
                        throw ValidationException::withMessages(['items' => ["{$product->name} is restricted to a different branch."]]);
                    }
                }
                $unit = ProductUnit::where('product_id', $product->id)->find($row['product_unit_id']);
                if (! $unit) throw ValidationException::withMessages(['items' => ["Choose a valid unit for {$product->name}."]]);
                $variant = empty($row['variant_id']) ? null : ProductVariant::where('product_id', $product->id)->find($row['variant_id']);
                if (! empty($row['variant_id']) && ! $variant) throw ValidationException::withMessages(['items' => ["The selected variant does not belong to {$product->name}."]]);
                if ($variant && $product->stock_mode !== 'from_variants') throw ValidationException::withMessages(['items' => ["{$product->name} does not use variants."]]);
                if ($product->stock_mode === 'from_variants' && ! $variant) throw ValidationException::withMessages(['items' => ["Choose a variant for {$product->name}."]]);
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id, 'product_id' => $product->id,
                    'variant_id' => $variant?->id, 'product_unit_id' => $unit->id, 'quantity' => $row['quantity'],
                ]);
            }
            return $transfer;
        });

        return response()->json(['transfer' => $transfer->load(['items.product:id,name,sku', 'items.variant:id,attributes'])], 201);
    }

    public function dispatch(Request $request, StockTransfer $transfer, StockLocationService $locations, UnitConversionService $conversion)
    {
        abort_unless((int) $transfer->shop_owner_id === (int) $this->shop($request)->id, 404);
        abort_unless($transfer->status === 'draft', 422, 'Only a draft transfer can be dispatched.');
        $locations->validateLocation($request, $this->shop($request), $transfer->source_type, (int) $transfer->source_id);

        DB::transaction(function () use ($request, $transfer, $locations, $conversion): void {
            $transfer = StockTransfer::whereKey($transfer->id)->lockForUpdate()->with('items')->firstOrFail();
            abort_unless($transfer->status === 'draft', 422, 'This transfer has already been dispatched.');
            $shop = $this->shop($request);
            $locations->validateLocation($request, $shop, $transfer->source_type, (int) $transfer->source_id);
            $locations->validateLocation($request, $shop, $transfer->destination_type, (int) $transfer->destination_id);
            $this->assertBranchHeadTransferScope($request, $transfer);
            foreach ($transfer->items as $item) {
                $product = Product::whereKey($item->product_id)->where('shop_owner_id', $transfer->shop_owner_id)->firstOrFail();
                $unit = ProductUnit::whereKey($item->product_unit_id)->where('product_id', $product->id)->firstOrFail();
                $variant = $item->variant_id ? ProductVariant::whereKey($item->variant_id)->where('product_id', $product->id)->firstOrFail() : null;
                $base = $conversion->toBaseQuantity($unit, (string) $item->quantity);
                $locations->apply($product, $unit, $variant, $transfer->source_type, (int) $transfer->source_id,
                    (string) $item->quantity, bcmul($base, '-1', 4), 'transfer_out', 'stock_transfer', $transfer->id, $request->user()->id);
            }
            $transfer->update(['status' => 'in_transit', 'dispatched_by' => $request->user()->id, 'dispatched_at' => now()]);
        });

        return response()->json(['message' => 'Transfer dispatched. Stock is now in transit.', 'transfer' => $transfer->fresh('items.product')]);
    }

    public function receive(Request $request, StockTransfer $transfer, StockLocationService $locations, UnitConversionService $conversion)
    {
        abort_unless((int) $transfer->shop_owner_id === (int) $this->shop($request)->id, 404);
        abort_unless($transfer->status === 'in_transit', 422, 'Only an in-transit transfer can be received.');
        $locations->validateLocation($request, $this->shop($request), $transfer->destination_type, (int) $transfer->destination_id);

        DB::transaction(function () use ($request, $transfer, $locations, $conversion): void {
            $transfer = StockTransfer::whereKey($transfer->id)->lockForUpdate()->with('items')->firstOrFail();
            abort_unless($transfer->status === 'in_transit', 422, 'This transfer has already been received or cancelled.');
            $shop = $this->shop($request);
            $locations->validateLocation($request, $shop, $transfer->source_type, (int) $transfer->source_id);
            $locations->validateLocation($request, $shop, $transfer->destination_type, (int) $transfer->destination_id);
            $this->assertBranchHeadTransferScope($request, $transfer);
            foreach ($transfer->items as $item) {
                $product = Product::whereKey($item->product_id)->where('shop_owner_id', $transfer->shop_owner_id)->firstOrFail();
                $unit = ProductUnit::whereKey($item->product_unit_id)->where('product_id', $product->id)->firstOrFail();
                $variant = $item->variant_id ? ProductVariant::whereKey($item->variant_id)->where('product_id', $product->id)->firstOrFail() : null;
                $base = $conversion->toBaseQuantity($unit, (string) $item->quantity);
                $locations->apply($product, $unit, $variant, $transfer->destination_type, (int) $transfer->destination_id,
                    (string) $item->quantity, $base, 'transfer_in', 'stock_transfer', $transfer->id, $request->user()->id);
            }
            $transfer->update(['status' => 'received', 'received_by' => $request->user()->id, 'received_at' => now()]);
        });

        return response()->json(['message' => 'Transfer received and stock added.', 'transfer' => $transfer->fresh('items.product')]);
    }

    public function cancel(Request $request, StockTransfer $transfer)
    {
        abort_unless((int) $transfer->shop_owner_id === (int) $this->shop($request)->id, 404);
        $this->assertBranchHeadTransferScope($request, $transfer);
        abort_unless($transfer->status === 'draft', 422, 'A dispatched transfer cannot be deleted; receive it or use a corrective transfer.');
        $transfer->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Draft transfer cancelled.']);
    }

    private function assertBranchHeadTransferScope(Request $request, StockTransfer $transfer): void
    {
        if ($request->user()->role !== 'staff') return;
        $assignedBranchId = (int) $request->user()->staff?->branch_id;
        abort_unless($assignedBranchId > 0, 403, 'Assign this employee to a branch before processing transfers.');
        abort_unless(
            ($transfer->source_type === 'branch' && (int) $transfer->source_id === $assignedBranchId)
            || ($transfer->destination_type === 'branch' && (int) $transfer->destination_id === $assignedBranchId),
            403,
            'A Branch Head can only process transfers involving the assigned branch.'
        );
    }
}

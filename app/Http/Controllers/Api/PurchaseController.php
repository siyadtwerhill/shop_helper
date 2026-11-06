<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Services\StockLocationService;
use App\Services\UnitConversionService;
use App\Traits\ResolvesShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    use ResolvesShop;

    public function index(Request $request, StockLocationService $locations)
    {
        $shop = $this->shop($request);
        $locations->ensureFeatureEnabled($shop);
        $query = Purchase::where('shop_owner_id', $shop->id)->with(['supplier:id,name', 'items.product:id,name,sku', 'items.variant:id,attributes', 'items.productUnit.unit:id,name,symbol']);
        if ($request->user()->role === 'staff') {
            $branchId = (int) $request->user()->staff?->branch_id;
            abort_unless($branchId > 0, 403, 'Assign this employee to a branch before showing purchase records.');
            $query->where('location_type', 'branch')->where('location_id', $branchId);
        }
        return $query->latest('id')->paginate(25);
    }

    public function store(Request $request, StockLocationService $locations)
    {
        $shop = $this->shop($request);
        $locations->ensureFeatureEnabled($shop);
        $data = $request->validate([
            'supplier_id' => 'required|integer',
            'location_type' => 'required|in:branch,warehouse',
            'location_id' => 'required|integer',
            'reference' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.product_unit_id' => 'required|integer',
            'items.*.variant_id' => 'nullable|integer',
            'items.*.quantity' => ['required', 'regex:/^\\d{1,11}(\\.\\d{1,4})?$/', 'gt:0'],
            'items.*.unit_cost' => ['required', 'regex:/^\\d{1,13}(\\.\\d{1,2})?$/', 'numeric', 'min:0'],
        ]);
        $locations->validateLocation($request, $shop, $data['location_type'], (int) $data['location_id']);
        if ($request->user()->role === 'staff') {
            abort_if($data['location_type'] !== 'branch' || (int) $data['location_id'] !== (int) $request->user()->staff?->branch_id, 403, 'Employees can only create purchases for their assigned branch.');
        }
        abort_unless(Supplier::where('shop_owner_id', $shop->id)->where('is_active', true)->whereKey($data['supplier_id'])->exists(), 422, 'Choose an active supplier from this shop.');

        $purchase = DB::transaction(function () use ($request, $shop, $data): Purchase {
            $purchase = Purchase::create([
                'shop_owner_id' => $shop->id, 'supplier_id' => $data['supplier_id'],
                'location_type' => $data['location_type'], 'location_id' => $data['location_id'],
                'reference' => $data['reference'] ?? null, 'note' => $data['note'] ?? null,
                'status' => 'draft', 'created_by' => $request->user()->id,
            ]);
            $total = '0.00';
            foreach ($data['items'] as $row) {
                $product = Product::where('shop_owner_id', $shop->id)->find($row['product_id']);
                if (! $product) throw ValidationException::withMessages(['items' => ['A selected product does not belong to this shop.']]);
                if (! $product->track_stock) throw ValidationException::withMessages(['items' => ["Stock tracking is disabled for {$product->name}."]]);
                if ($product->stock_mode === 'from_components') throw ValidationException::withMessages(['items' => ["Bundles cannot be purchased as stocked items: {$product->name}."]]);
                if ($data['location_type'] === 'branch' && $product->branch_id !== null && (int) $product->branch_id !== (int) $data['location_id']) {
                    throw ValidationException::withMessages(['items' => ["{$product->name} is restricted to a different branch."]]);
                }
                $unit = ProductUnit::where('product_id', $product->id)->find($row['product_unit_id']);
                if (! $unit || ! $unit->is_purchasable) throw ValidationException::withMessages(['items' => ["{$product->name} does not allow purchases in the selected unit."]]);
                $variant = empty($row['variant_id']) ? null : ProductVariant::where('product_id', $product->id)->find($row['variant_id']);
                if (! empty($row['variant_id']) && ! $variant) throw ValidationException::withMessages(['items' => ["The selected variant does not belong to {$product->name}."]]);
                if ($variant && $product->stock_mode !== 'from_variants') throw ValidationException::withMessages(['items' => ["{$product->name} does not use variants."]]);
                if ($product->stock_mode === 'from_variants' && ! $variant) throw ValidationException::withMessages(['items' => ["Choose a variant for {$product->name}."]]);
                $lineTotal = bcmul((string) $row['quantity'], (string) $row['unit_cost'], 2);
                $total = bcadd($total, $lineTotal, 2);
                PurchaseItem::create([
                    'purchase_id' => $purchase->id, 'product_id' => $product->id,
                    'variant_id' => $variant?->id, 'product_unit_id' => $unit->id,
                    'quantity' => $row['quantity'], 'unit_cost' => $row['unit_cost'], 'line_total' => $lineTotal,
                ]);
            }
            $purchase->update(['total_amount' => $total]);
            return $purchase;
        });

        return response()->json(['purchase' => $purchase->load(['supplier:id,name', 'items.product:id,name,sku', 'items.variant:id,attributes', 'items.productUnit.unit:id,name,symbol'])], 201);
    }

    public function receive(Request $request, Purchase $purchase, StockLocationService $locations, UnitConversionService $conversion)
    {
        $shop = $this->shop($request);
        abort_unless((int) $purchase->shop_owner_id === (int) $shop->id, 404);
        abort_unless($purchase->status === 'draft', 422, 'Only a draft purchase can be received.');
        $locations->validateLocation($request, $shop, $purchase->location_type, (int) $purchase->location_id);
        if ($request->user()->role === 'staff') {
            abort_if($purchase->location_type !== 'branch' || (int) $purchase->location_id !== (int) $request->user()->staff?->branch_id, 403);
        }

        DB::transaction(function () use ($request, $purchase, $locations, $conversion): void {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->with('items')->firstOrFail();
            abort_unless($purchase->status === 'draft', 422, 'This purchase has already been processed.');
            foreach ($purchase->items as $item) {
                $product = Product::whereKey($item->product_id)->where('shop_owner_id', $purchase->shop_owner_id)->firstOrFail();
                $unit = ProductUnit::whereKey($item->product_unit_id)->where('product_id', $product->id)->firstOrFail();
                $variant = $item->variant_id ? ProductVariant::whereKey($item->variant_id)->where('product_id', $product->id)->firstOrFail() : null;
                $base = $conversion->toBaseQuantity($unit, (string) $item->quantity);
                $locations->apply($product, $unit, $variant, $purchase->location_type, (int) $purchase->location_id,
                    (string) $item->quantity, $base, 'purchase', 'purchase', $purchase->id, $request->user()->id);
                if ($variant) $variant->update(['purchase_price' => $item->unit_cost]);
                else $unit->update(['purchase_price' => $item->unit_cost]);
            }
            $purchase->update(['status' => 'received', 'received_by' => $request->user()->id, 'received_at' => now()]);
        });

        return response()->json(['message' => 'Purchase received and stock added.', 'purchase' => $purchase->fresh(['supplier:id,name', 'items.product:id,name,sku'])]);
    }

    public function destroy(Request $request, Purchase $purchase)
    {
        abort_unless((int) $purchase->shop_owner_id === (int) $this->shop($request)->id, 404);
        if ($request->user()->role === 'staff') {
            abort_if($purchase->location_type !== 'branch' || (int) $purchase->location_id !== (int) $request->user()->staff?->branch_id, 403);
        }
        abort_unless($purchase->status === 'draft', 422, 'Received purchase history cannot be deleted.');
        $purchase->delete();
        return response()->json(['message' => 'Draft purchase deleted.']);
    }
}

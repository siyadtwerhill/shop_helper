<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Models\InventoryMovement;
use App\Services\StockLocationService;
use App\Traits\ResolvesShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    use ResolvesShop;

    public function index(Request $request, StockLocationService $locations)
    {
        $shop = $this->shop($request);
        $locations->ensureFeatureEnabled($shop);
        $warehouses = Warehouse::where('shop_owner_id', $shop->id)
            ->withCount('stocks')->orderBy('name')->get();

        return response()->json(['warehouses' => $warehouses]);
    }

    public function store(Request $request, StockLocationService $locations)
    {
        abort_if($request->user()->isBranchHead(), 403, 'Branch Heads cannot manage warehouse records.');
        $shop = $this->shop($request);
        $locations->ensureFeatureEnabled($shop);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['nullable', 'string', 'max:50', Rule::unique('warehouses')->where('shop_owner_id', $shop->id)],
            'address' => 'nullable|string|max:255',
        ]);
        return response()->json(['warehouse' => Warehouse::create($data + ['shop_owner_id' => $shop->id, 'is_active' => true])], 201);
    }

    public function stocks(Request $request, Warehouse $warehouse, StockLocationService $locations)
    {
        $shop = $this->shop($request);
        $locations->ensureFeatureEnabled($shop);
        abort_unless((int) $warehouse->shop_owner_id === (int) $shop->id, 404);
        $stocks = WarehouseProductStock::where('warehouse_id', $warehouse->id)
            ->with(['product:id,name,sku,track_stock', 'product.variants:id,product_id,attributes'])
            ->whereHas('product', fn ($query) => $query->where('status', 'active'))
            ->orderBy('product_id')->paginate(50);
        $variantIds = $stocks->getCollection()
            ->flatMap(fn ($stock) => $stock->product?->variants?->pluck('id') ?? [])
            ->unique()->values();
        if ($variantIds->isNotEmpty()) {
            $variantBalances = InventoryMovement::where('warehouse_id', $warehouse->id)
                ->whereIn('variant_id', $variantIds)
                ->select('variant_id', DB::raw('SUM(base_quantity) as current_stock'))
                ->groupBy('variant_id')->pluck('current_stock', 'variant_id');
            $stocks->getCollection()->each(function ($stock) use ($variantBalances) {
                $stock->product?->variants?->each(function ($variant) use ($variantBalances) {
                    $variant->setAttribute('current_stock', (string) ($variantBalances[$variant->id] ?? 0));
                });
            });
        }
        return response()->json(['warehouse' => $warehouse, 'stocks' => $stocks]);
    }

    public function movements(Request $request, Warehouse $warehouse, StockLocationService $locations)
    {
        $shop = $this->shop($request);
        $locations->ensureFeatureEnabled($shop);
        abort_unless((int) $warehouse->shop_owner_id === (int) $shop->id, 404);
        return InventoryMovement::where('warehouse_id', $warehouse->id)
            ->whereHas('product', fn ($query) => $query->withTrashed()->where('shop_owner_id', $shop->id))
            ->with(['product' => fn ($query) => $query->withTrashed()->select('id', 'name', 'sku', 'base_unit_id'), 'product.baseUnit.unit:id,name,symbol', 'variant:id,attributes', 'creator:id,name'])
            ->latest('id')->paginate(30);
    }

    public function update(Request $request, Warehouse $warehouse, StockLocationService $locations)
    {
        $shop = $this->shop($request);
        abort_if($request->user()->isBranchHead(), 403, 'Branch Heads cannot manage warehouse records.');
        $locations->ensureFeatureEnabled($shop);
        abort_unless((int) $warehouse->shop_owner_id === (int) $shop->id, 404);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => ['nullable', 'string', 'max:50', Rule::unique('warehouses')->where('shop_owner_id', $warehouse->shop_owner_id)->ignore($warehouse->id)],
            'address' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);
        if (array_key_exists('is_active', $data) && ! $data['is_active'] && $warehouse->is_active) {
            $hasStock = DB::table('warehouse_product_stocks')->where('warehouse_id', $warehouse->id)->where('current_stock', '>', 0)->exists();
            $hasOpenTransfer = DB::table('stock_transfers')->whereIn('status', ['draft', 'in_transit'])
                ->where(fn ($query) => $query
                    ->where(fn ($source) => $source->where('source_type', 'warehouse')->where('source_id', $warehouse->id))
                    ->orWhere(fn ($destination) => $destination->where('destination_type', 'warehouse')->where('destination_id', $warehouse->id)))
                ->exists();
            $hasDraftPurchase = DB::table('purchases')->where('location_type', 'warehouse')->where('location_id', $warehouse->id)->where('status', 'draft')->exists();
            if ($hasStock || $hasOpenTransfer || $hasDraftPurchase) {
                return response()->json(['message' => 'Move all stock and finish open transfers before deactivating this warehouse.'], 422);
            }
        }
        $warehouse->update($data);
        return response()->json(['warehouse' => $warehouse->fresh()]);
    }

    public function destroy(Request $request, Warehouse $warehouse, StockLocationService $locations)
    {
        abort_if($request->user()->isBranchHead(), 403, 'Branch Heads cannot manage warehouse records.');
        $shop = $this->shop($request);
        $locations->ensureFeatureEnabled($shop);
        abort_unless((int) $warehouse->shop_owner_id === (int) $shop->id, 404);
        if (DB::table('warehouse_product_stocks')->where('warehouse_id', $warehouse->id)->exists()
            || DB::table('inventory_movements')->where('warehouse_id', $warehouse->id)->exists()
            || DB::table('purchases')->where('location_type', 'warehouse')->where('location_id', $warehouse->id)->exists()
            || DB::table('stock_transfers')->where(fn ($query) => $query
                ->where(fn ($source) => $source->where('source_type', 'warehouse')->where('source_id', $warehouse->id))
                ->orWhere(fn ($destination) => $destination->where('destination_type', 'warehouse')->where('destination_id', $warehouse->id)))
                ->exists()) {
            return response()->json(['message' => 'This warehouse has stock or movement history. Deactivate it instead of deleting it.'], 422);
        }
        $warehouse->delete();
        return response()->json(['message' => 'Warehouse deleted.']);
    }
}

<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\ShopOwner;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockLocationService
{
    public function __construct(private ProductStockService $branchStock) {}

    public function ensureFeatureEnabled(ShopOwner $shop): void
    {
        $shop->loadMissing('plan.modules');
        $mode = app(BranchModeService::class);
        abort_unless($mode->inventoryLogisticsEnabledFor($shop), 403, 'Your plan must include the Inventory and Branches modules and allow branch storage.');
        $mode->ensureInitialBranch($shop);
        abort_unless(Branch::where('shop_owner_id', $shop->id)->where('is_active', true)->exists(), 422, 'Create an active branch before using warehouses and transfers.');
    }

    public function validateLocation(Request $request, ShopOwner $shop, string $type, int $id): Branch|Warehouse
    {
        $this->ensureFeatureEnabled($shop);
        if ($type === 'branch') {
            $branch = Branch::where('shop_owner_id', $shop->id)->where('is_active', true)->find($id);
            abort_unless($branch, 422, 'Choose an active branch belonging to this shop.');
            $this->branchStock->resolveBranchId($request, (int) $shop->id, required: true, requestedBranchId: (int) $branch->id);
            return $branch;
        }
        if ($type === 'warehouse') {
            $warehouse = Warehouse::where('shop_owner_id', $shop->id)->where('is_active', true)->find($id);
            abort_unless($warehouse, 422, 'Choose an active warehouse belonging to this shop.');
            return $warehouse;
        }
        throw ValidationException::withMessages(['location_type' => ['Location must be a branch or warehouse.']]);
    }

    public function available(Product $product, string $type, int $id, ?ProductVariant $variant = null): string
    {
        if ($type === 'branch') {
            return $variant
                ? $this->branchVariantStock($variant, $id)
                : $this->branchStock->availableFor($product, $id);
        }
        if ($variant) {
            return $this->decimal(InventoryMovement::where('warehouse_id', $id)->where('variant_id', $variant->id)->sum('base_quantity'));
        }
        return $this->decimal(WarehouseProductStock::where('warehouse_id', $id)->where('product_id', $product->id)->value('current_stock'));
    }

    public function apply(
        Product $product,
        ProductUnit $unit,
        ?ProductVariant $variant,
        string $locationType,
        int $locationId,
        string $quantity,
        string $signedBase,
        string $movementType,
        ?string $referenceType,
        ?int $referenceId,
        ?int $userId,
        ?string $note = null,
    ): InventoryMovement {
        $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
        abort_unless((int) $locked->shop_owner_id === (int) $product->shop_owner_id, 404);
        abort_unless((int) $unit->product_id === (int) $locked->id, 422, 'The selected unit does not belong to this product.');
        if ($variant) {
            abort_unless((int) $variant->product_id === (int) $locked->id, 422, 'The selected variant does not belong to this product.');
            abort_unless($locked->stock_mode === 'from_variants', 422, 'This product does not use variants.');
        }
        abort_if($locked->stock_mode === 'from_components', 422, 'Bundle stock is calculated from its components.');

        if (bccomp($signedBase, '0', 4) < 0) {
            $next = bcadd($this->available($locked, $locationType, $locationId, $variant), $signedBase, 4);
            if (bccomp($next, '0', 4) < 0) {
                throw ValidationException::withMessages(['quantity' => ['Not enough stock at the source location for this operation.']]);
            }
        }

        if ($locationType === 'branch') {
            if ($locked->branch_id !== null && (int) $locked->branch_id !== $locationId) {
                throw ValidationException::withMessages(['product_id' => ['This product is assigned to a different branch.']]);
            }
            $this->branchStock->applyMovement($locked, $locationId, $signedBase);
        } else {
            if (! Warehouse::where('id', $locationId)->where('shop_owner_id', $locked->shop_owner_id)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['location_id' => ['The warehouse is not active in this shop.']]);
            }
            $stock = WarehouseProductStock::where('warehouse_id', $locationId)->where('product_id', $locked->id)->lockForUpdate()->first();
            if (! $stock) {
                WarehouseProductStock::create([
                    'shop_owner_id' => $locked->shop_owner_id,
                    'warehouse_id' => $locationId,
                    'product_id' => $locked->id,
                    'current_stock' => '0.0000',
                ]);
                $stock = WarehouseProductStock::where('warehouse_id', $locationId)->where('product_id', $locked->id)->lockForUpdate()->firstOrFail();
            }
            $next = bcadd($this->decimal($stock->current_stock), $signedBase, 4);
            if (bccomp($next, '0', 4) < 0) {
                throw ValidationException::withMessages(['quantity' => ['The warehouse does not have enough stock for this operation.']]);
            }
            $stock->update(['current_stock' => $next]);
        }

        return InventoryMovement::create([
            'product_id' => $locked->id,
            'branch_id' => $locationType === 'branch' ? $locationId : null,
            'warehouse_id' => $locationType === 'warehouse' ? $locationId : null,
            'variant_id' => $variant?->id,
            'unit_id' => $unit->id,
            'quantity' => ltrim($quantity, '-'),
            'base_quantity' => $signedBase,
            'movement_type' => $movementType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $note,
            'created_by' => $userId,
        ]);
    }

    private function branchVariantStock(ProductVariant $variant, int $branchId): string
    {
        return $this->decimal(InventoryMovement::where('branch_id', $branchId)->where('variant_id', $variant->id)->sum('base_quantity'));
    }

    private function decimal(mixed $value): string
    {
        return $value === null || $value === '' ? '0.0000' : (is_string($value) ? $value : number_format((float) $value, 4, '.', ''));
    }
}

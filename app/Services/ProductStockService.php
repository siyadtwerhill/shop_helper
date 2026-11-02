<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductStock;
use App\Services\BranchModeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductStockService
{
    /** Resolve a stock branch from trusted staff context or a shop-owner selection. */
    public function resolveBranchId(
        Request $request,
        int $shopOwnerId,
        bool $required = false,
        ?int $requestedBranchId = null,
    ): ?int {
        $shop = \App\Models\ShopOwner::with('plan.modules')->find($shopOwnerId);
        $branchMode = $shop && app(BranchModeService::class)->enabledFor($shop);
        if ($shop && app(BranchModeService::class)->allowsBranchStorage($shop)) {
            app(BranchModeService::class)->ensureInitialBranch($shop);
        }

        $user = $request->user();
        $user?->unsetRelation('staff');
        $staff = $user?->staff;
        $assignedBranchId = $staff?->branch_id ? (int) $staff->branch_id : null;
        $isBranchHead = $user?->isBranchHead() ?? false;

        if ($isBranchHead && ! $assignedBranchId) {
            abort(403, 'Your Branch Head assignment is missing. Contact the shop owner.');
        }
        if ($branchMode && $staff && ! $assignedBranchId) {
            abort(403, 'Your employee account needs a branch assignment before using branch data. Contact the shop owner.');
        }

        $providedBranchId = $requestedBranchId
            ?? ($request->filled('branch_id') ? (int) $request->input('branch_id') : null)
            ?? ($request->filled('stock_branch_id') ? (int) $request->input('stock_branch_id') : null)
            ?? ($request->filled('branch_id') ? (int) $request->query('branch_id') : null);

        if ($assignedBranchId) {
            if ($providedBranchId !== null && $providedBranchId !== $assignedBranchId) {
                abort(403, 'You can only use your assigned branch.');
            }
            $providedBranchId = $assignedBranchId;
        }

        if ($providedBranchId === null && $required) {
            $activeBranches = DB::table('branches')
                ->where('shop_owner_id', $shopOwnerId)
                ->where('is_active', true)
                ->orderBy('id')
                ->pluck('id');

            if ($activeBranches->isNotEmpty() && ($activeBranches->count() === 1 || ! $branchMode)) {
                $providedBranchId = (int) $activeBranches->first();
            } elseif ($activeBranches->isEmpty()) {
                throw ValidationException::withMessages([
                    'branch_id' => ['Create an active branch before recording branch stock.'],
                ]);
            } elseif ($staff) {
                throw ValidationException::withMessages([
                    'branch_id' => ['Your employee account needs an assigned branch for stock operations.'],
                ]);
            } else {
                throw ValidationException::withMessages([
                    'branch_id' => ['Choose the branch for this stock operation.'],
                ]);
            }
        }

        if ($providedBranchId === null) {
            if ($required) {
                throw ValidationException::withMessages([
                    'branch_id' => ['Your employee account needs an assigned branch for stock operations.'],
                ]);
            }

            return null;
        }

        $valid = DB::table('branches')
            ->where('id', $providedBranchId)
            ->where('shop_owner_id', $shopOwnerId)
            ->where('is_active', true)
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'branch_id' => ['Choose an active branch belonging to your shop.'],
            ]);
        }

        return $providedBranchId;
    }

    /** Product-level balance, scoped to one branch or summed across the shop. */
    public function availableFor(Product $product, ?int $branchId = null): string
    {
        if ($product->stock_mode === 'from_components') {
            return $this->bundleAvailable($product, $branchId);
        }

        $query = ProductStock::query()
            ->where('product_id', $product->id)
            ->where('shop_owner_id', $product->shop_owner_id);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $this->dec($query->sum('current_stock'));
    }

    /** Whole bundles that can be made from their components at the selected branch. */
    public function bundleAvailable(Product $product, ?int $branchId = null): string
    {
        $bundle = $product->bundle ?? $product->bundle()->first();

        return $bundle ? $this->bundleAvailableFor($bundle, $branchId) : '0.0000';
    }

    public function bundleAvailableFor(ProductBundle $bundle, ?int $branchId = null): string
    {
        $bundle->loadMissing('items.unit', 'items.component');
        if ($bundle->items->isEmpty()) return '0.0000';

        // A combined stock view must add each branch's buildable bundle count.
        // Taking the minimum of shop-wide component totals can falsely combine
        // components that are physically stored at different branches.
        if ($branchId === null) {
            $shopOwnerId = $bundle->product?->shop_owner_id ?? $bundle->product()->value('shop_owner_id');
            $branchIds = DB::table('branches')
                ->where('shop_owner_id', $shopOwnerId)
                ->where('is_active', true)
                ->pluck('id');
            $total = '0.0000';
            foreach ($branchIds as $id) {
                $total = bcadd($total, $this->bundleAvailableFor($bundle, (int) $id), 4);
            }
            return $total;
        }

        $min = null;
        foreach ($bundle->items as $item) {
            $perBundle = bcmul($this->dec($item->quantity), $this->dec($item->unit?->conversion_factor ?? 1), 4);
            if (bccomp($perBundle, '0', 4) <= 0) return '0.0000';

            $variantId = $item->component_variant_id;
            $have = $variantId
                ? $this->variantStock((int) $variantId, $branchId)
                : $this->availableFor($item->component, $branchId);

            $possible = bcdiv($have, $perBundle, 4);
            if ($min === null || bccomp($possible, $min, 4) < 0) $min = $possible;
        }

        return $min === null || bccomp($min, '0', 4) <= 0 ? '0.0000' : bcadd($min, '0', 0);
    }

    public function variantStock(int $variantId, ?int $branchId = null): string
    {
        $query = InventoryMovement::query()->where('variant_id', $variantId);
        $this->scopeNewBranchMovements($query, $branchId);

        return $this->dec($query->selectRaw('COALESCE(SUM(base_quantity), 0) as total')->value('total'));
    }

    /** One grouped query: [variant_id => branch balance]. */
    public function variantStocks(array $variantIds, ?int $branchId = null): array
    {
        if (! $variantIds) return [];

        $query = InventoryMovement::query()->whereIn('variant_id', $variantIds);
        $this->scopeNewBranchMovements($query, $branchId);

        return $query->selectRaw('variant_id, SUM(base_quantity) as total')
            ->groupBy('variant_id')->pluck('total', 'variant_id')
            ->map(fn ($value) => $this->dec($value))->all();
    }

    /** Branch balance not yet allocated to a variant. */
    public function unassignedFor(Product $product, ?int $branchId = null): string
    {
        $assignedQuery = InventoryMovement::query()
            ->where('product_id', $product->id)
            ->whereNotNull('variant_id');
        $this->scopeNewBranchMovements($assignedQuery, $branchId);
        $assigned = $assignedQuery->selectRaw('COALESCE(SUM(base_quantity), 0) as total')->value('total');

        return bcsub($this->availableFor($product, $branchId), $this->dec($assigned), 4);
    }

    /** Ensure a zero/current balance row exists for a product at an active shop branch. */
    public function ensureBranchBalance(Product $product, int $branchId): void
    {
        $this->assertBranchBelongsToProduct($branchId, $product);

        DB::table('product_stocks')->insertOrIgnore([
            'product_id' => $product->id,
            'branch_id' => $branchId,
            'shop_owner_id' => $product->shop_owner_id,
            'current_stock' => '0.0000',
            'reorder_level' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Apply a signed base-unit movement to the locked branch balance. Call inside a transaction. */
    public function applyMovement(Product $product, int $branchId, string $signedBase): void
    {
        $this->assertBranchBelongsToProduct($branchId, $product);

        $stock = DB::table('product_stocks')
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->where('shop_owner_id', $product->shop_owner_id)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            $this->ensureBranchBalance($product, $branchId);
            $stock = DB::table('product_stocks')
                ->where('product_id', $product->id)
                ->where('branch_id', $branchId)
                ->where('shop_owner_id', $product->shop_owner_id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        DB::table('product_stocks')->where('id', $stock->id)->update([
            'current_stock' => bcadd($this->dec($stock->current_stock), $signedBase, 4),
            'updated_at' => now(),
        ]);
    }

    /** Sets available_stock on each product using the same branch scope as the request. */
    public function hydrate(iterable $products, ?int $branchId = null): void
    {
        foreach ($products as $product) {
            $product->setAttribute('available_stock', $this->availableFor($product, $branchId));
            $product->setAttribute('inventory_branch_id', $branchId);
        }
    }

    public function assertVisibleAtBranch(Product $product, ?int $branchId, bool $strict = false): void
    {
        $enforce = $strict || auth()->user()?->staff !== null;
        if ($enforce && $branchId !== null && $product->branch_id !== null && (int) $product->branch_id !== $branchId) {
            abort(404, 'Product not found in this branch.');
        }
    }

    private function assertBranchBelongsToProduct(int $branchId, Product $product): void
    {
        if ($product->branch_id !== null && (int) $product->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'branch_id' => ['This product is assigned to a different branch.'],
            ]);
        }

        if (! DB::table('branches')
            ->where('id', $branchId)
            ->where('shop_owner_id', $product->shop_owner_id)
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'branch_id' => ['Choose an active branch belonging to the product shop.'],
            ]);
        }
    }

    private function scopeNewBranchMovements($query, ?int $branchId): void
    {
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        } else {
            // Historical unassigned movements are not a branch balance.
            $query->whereNotNull('branch_id');
        }
    }

    private function dec(mixed $value): string
    {
        if ($value === null || $value === '') return '0.0000';
        return is_string($value) ? $value : number_format((float) $value, 4, '.', '');
    }
}

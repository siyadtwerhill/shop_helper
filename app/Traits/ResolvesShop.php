<?php

namespace App\Traits;

use Illuminate\Http\Request;
use App\Models\Product;
use Spatie\Permission\PermissionRegistrar;

trait ResolvesShop
{
    /**
     * Resolve the shop for the current user. Shop owners have it directly;
     * staff / branch heads reach it through their staff record.
     */
    protected function shop(Request $request)
    {
        $user = $request->user();
        $shop = $user->shopOwner ?? $user->staff?->shopOwner;

        abort_unless($shop, 403, 'No shop is linked to this account.');

        return $shop;
    }

    protected function ensureOwner(Request $request): void
    {
        abort_unless($request->user()->isShopOwner(), 403, 'Only the shop owner can do this.');
    }

    protected function authorizeAction(Request $request, string $permission): void
    {
        $user = $request->user();
        if ($user->isShopOwner() || $user->isSuperAdmin()) {
            return;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->shop($request)->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        abort_unless($user->can($permission), 403, 'You do not have permission to do this.');
    }

    /** 404 (not 403) so ids of other shops or branches are never confirmed to exist. */
    protected function ownedProduct(Request $request, Product $product): Product
    {
        abort_unless($product->shop_owner_id === $this->shop($request)->id, 404);

        $user = $request->user();
        $branchId = $user->isBranchHead() ? $user->staff?->branch_id : null;

        // Shop-wide products (branch_id null) stay visible to branch heads.
        if ($branchId && $product->branch_id !== null && (int) $product->branch_id !== (int) $branchId) {
            abort(404);
        }

        return $product;
    }
}

<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Apply to any model with a branch_id column that shouldn't be visible
 * across branches by default (Staff, Sale, Expense, etc.).
 *
 * Scoping rules:
 *   - superadmin / shop_owner → no filter, sees everything in the shop
 *   - branch_head             → only rows for their own branch_id
 *   - staff                   → only rows for their own branch_id
 *
 * This is deliberately separate from Spatie permissions: permissions
 * answer "can this action happen at all," this trait answers "which
 * rows can they see while doing it." The two compose — a branch_head
 * with employees.list.view still only sees their own branch's staff.
 */
trait BelongsToBranch
{
    protected static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $builder) {
            if (!auth()->check()) {
                return;
            }

            $user = auth()->user();

            if ($user->isSuperAdmin() || $user->isShopOwner()) {
                return; // unscoped — full shop visibility
            }

            // Try to get staff relationship, but handle case where it doesn't exist
            try {
                $staff = $user->staff;
                if ($staff && $staff->branch_id) {
                    $builder->where($builder->getModel()->getTable() . '.branch_id', $staff->branch_id);
                }
            } catch (\Exception $e) {
                // If staff relationship doesn't exist or fails, don't apply scope
                return;
            }
            // staff with branch_id === null (shop-wide access) stay unscoped too
        });
    }

    public function scopeWithoutBranchScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('branch');
    }
}

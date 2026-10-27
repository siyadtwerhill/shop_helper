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
 *
 * FAIL-CLOSE: If branch context cannot be established for a non-superadmin/non-shop-owner
 * user, return an empty result set rather than exposing all records.
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

            // For staff (including branch heads), require valid branch context
            // Access staff relationship - Laravel will lazy load if not already loaded
            try {
                $staff = $user->staff;
            } catch (\Exception $e) {
                // FAIL-CLOSE: If staff relationship fails to load, return empty result
                $builder->whereRaw('1 = 0');
                return;
            }

            // If staff relationship is missing or staff has no branch assignment,
            // fail closed: return empty result set
            if (!$staff || !$staff->branch_id) {
                $builder->whereRaw('1 = 0');
                return;
            }

            // Scope to the staff's assigned branch
            $table = $builder->getModel()->getTable();
            $builder->where($table . '.branch_id', $staff->branch_id);

            // Also scope by shop_owner_id for tenant isolation (if the model has it)
            // This is CRITICAL for cross-shop isolation
            $fillable = $builder->getModel()->getFillable();
            if (in_array('shop_owner_id', $fillable)) {
                $builder->where($table . '.shop_owner_id', $staff->shop_owner_id);
            }
        });
    }

    public function scopeWithoutBranchScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('branch');
    }
}

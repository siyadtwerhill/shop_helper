<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ShopOwner;
use Illuminate\Support\Facades\DB;

class BranchModeService
{
    public function enabledFor(ShopOwner $shop): bool
    {
        $plan = $shop->plan;
        if (! $plan) return false;

        $allowsMultipleBranches = $plan->max_branches === null || (int) $plan->max_branches > 1;

        return $allowsMultipleBranches && $this->branchModuleEnabled($shop);
    }

    public function branchModuleEnabled(ShopOwner $shop): bool
    {
        $plan = $shop->plan;

        return $plan !== null && $plan->modules()
            ->where('modules.slug', 'branches')
            ->where('modules.is_active', true)
            ->exists();
    }

    public function allowsBranchStorage(ShopOwner $shop): bool
    {
        $shop->loadMissing('plan:id,max_branches');
        $plan = $shop->plan;

        return $plan !== null
            && ($plan->max_branches === null || (int) $plan->max_branches >= 1);
    }

    public function inventoryLogisticsEnabledFor(ShopOwner $shop): bool
    {
        $shop->loadMissing('plan.modules');
        $inventoryModuleEnabled = $shop->plan?->modules()
            ->where('modules.slug', 'inventory')
            ->where('modules.is_active', true)
            ->exists() ?? false;

        return $inventoryModuleEnabled
            && $this->branchModuleEnabled($shop)
            && $this->allowsBranchStorage($shop);
    }

    /**
     * Ensure branch-capable shops have a storage branch. During multi-branch
     * activation, unassigned employees are placed there. Product definitions
     * stay shared; the stock backfill places legacy balances in this branch.
     * Historical sales and movements are deliberately left unattributed.
     */
    public function ensureInitialBranch(ShopOwner $shop): ?Branch
    {
        if (! $this->allowsBranchStorage($shop)) {
            return null;
        }

        return DB::transaction(function () use ($shop): ?Branch {
            $lockedShop = ShopOwner::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            $lockedShop->load('plan:id,max_branches');

            $branch = Branch::where('shop_owner_id', $lockedShop->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $branch) {
                $branch = Branch::create([
                    'shop_owner_id' => $lockedShop->id,
                    'name' => 'Main Store',
                    'is_active' => true,
                ]);
            }

            // Existing catalog definitions remain shared. Only employee records
            // need an operational home branch when the shop activates branch mode.
            if ($this->enabledFor($lockedShop)) {
                DB::table('staff')
                    ->where('shop_owner_id', $lockedShop->id)
                    ->whereNull('branch_id')
                    ->whereNotIn('user_id', DB::table('model_has_roles as assignments')
                        ->join('roles', 'roles.id', '=', 'assignments.role_id')
                        ->where('assignments.model_type', \App\Models\User::class)
                        ->where('assignments.shop_owner_id', $lockedShop->id)
                        ->whereIn('roles.name', ['Branch Head', 'branch_head'])
                        ->select('assignments.model_id'))
                    ->update(['branch_id' => $branch->id, 'updated_at' => now()]);
            }

            return $branch->fresh();
        }, attempts: 3);
    }
}

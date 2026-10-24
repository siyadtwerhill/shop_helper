<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\ShopOwner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class BranchHeadRoleSeeder extends Seeder
{
    /**
     * Seed default permissions for the Branch Head role.
     * This seeder is idempotent and works for both existing and newly created shops.
     */
    public function run(): void
    {
        // Get all shop owners
        $shopOwners = ShopOwner::all();

        foreach ($shopOwners as $shopOwner) {
            // Set team context for this shop
            app(PermissionRegistrar::class)->setPermissionsTeamId($shopOwner->id);

            // Find or create the Branch Head role for this shop
            $branchHeadRole = Role::firstOrCreate(
                [
                    'name' => 'Branch Head',
                    'shop_owner_id' => $shopOwner->id,
                ],
                [
                    'name' => 'Branch Head',
                    'guard_name' => 'web',
                    'shop_owner_id' => $shopOwner->id,
                    'description' => 'Branch management with limited permissions',
                    'is_system' => true,
                ]
            );

            // Default Branch Head permissions (approved set)
            $defaultPermissions = [
                'products.view',
                'sales.pos',
                'inventory.stock.view',
                'employees.view',
                'branches.view',
            ];

            // Get permission IDs for the shop's team context
            $permissionIds = Permission::whereIn('name', $defaultPermissions)
                ->where('guard_name', 'web')
                ->pluck('id')
                ->toArray();

            // Sync permissions (this will add missing ones, keep existing ones)
            if (!empty($permissionIds)) {
                $branchHeadRole->syncPermissions($permissionIds);
            }
        }

        // Reset team context to null
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }
}

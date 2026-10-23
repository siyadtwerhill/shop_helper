<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Module;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        // Set team context to null for global seeding
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $modules = Module::all()->keyBy('slug');

        $featuresData = [
            'employees' => [
                ['name' => 'Employee Management', 'slug' => 'employees', 'sort_order' => 1],
            ],
            'branches' => [
                ['name' => 'Branch Management', 'slug' => 'branches', 'sort_order' => 1],
            ],
            'products' => [
                ['name' => 'Product Management', 'slug' => 'products', 'sort_order' => 1],
                ['name' => 'Product Variants', 'slug' => 'products.variants', 'sort_order' => 2],
                ['name' => 'Product Units', 'slug' => 'products.units', 'sort_order' => 3],
                ['name' => 'Product Bundles', 'slug' => 'products.bundles', 'sort_order' => 4],
                ['name' => 'Product Pricing', 'slug' => 'products.pricing', 'sort_order' => 5],
                ['name' => 'Inventory Stock', 'slug' => 'inventory.stock', 'sort_order' => 6],
            ],
            'sales' => [
                ['name' => 'Sales Management', 'slug' => 'sales', 'sort_order' => 1],
                ['name' => 'POS Operations', 'slug' => 'sales.pos', 'sort_order' => 2],
                ['name' => 'Sales Voids & Refunds', 'slug' => 'sales.refunds', 'sort_order' => 3],
            ],
            'customers' => [
                ['name' => 'Customer Management', 'slug' => 'customers', 'sort_order' => 1],
            ],
            'expenses' => [
                ['name' => 'Expense Management', 'slug' => 'expenses', 'sort_order' => 1],
            ],
        ];

        foreach ($featuresData as $moduleSlug => $features) {
            $module = $modules->get($moduleSlug);
            if (!$module) continue;

            foreach ($features as $featureData) {
                $feature = Feature::updateOrCreate(
                    ['slug' => $featureData['slug']],
                    array_merge($featureData, ['module_id' => $module->id])
                );

                // Create permissions for each action
                $actions = ['view', 'create', 'edit', 'delete'];
                foreach ($actions as $action) {
                    $permissionName = "{$featureData['slug']}.{$action}";
                    Permission::updateOrCreate(
                        ['name' => $permissionName],
                        [
                            'name' => $permissionName,
                            'feature_id' => $feature->id,
                            'action' => $action,
                            'guard_name' => 'web',
                        ]
                    );
                }
            }
        }
    }
}

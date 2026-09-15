<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['name' => 'Employees', 'slug' => 'employees', 'icon' => 'users', 'description' => 'Employee management', 'sort_order' => 1, 'is_active' => true],
            ['name' => 'Branches', 'slug' => 'branches', 'icon' => 'building', 'description' => 'Branch management', 'sort_order' => 2, 'is_active' => true],
            ['name' => 'Products', 'slug' => 'products', 'icon' => 'box', 'description' => 'Product management', 'sort_order' => 3, 'is_active' => true],
            ['name' => 'Sales', 'slug' => 'sales', 'icon' => 'shopping-cart', 'description' => 'Sales management', 'sort_order' => 4, 'is_active' => true],
            ['name' => 'Expenses', 'slug' => 'expenses', 'icon' => 'receipt', 'description' => 'Expense management', 'sort_order' => 5, 'is_active' => true],
        ];

        $createdModules = [];
        foreach ($modules as $module) {
            $createdModule = Module::updateOrCreate(['slug' => $module['slug']], $module);
            $createdModules[$module['slug']] = $createdModule;
        }

        // Create plans and assign modules
        $free = Plan::updateOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free',
                'slug' => 'free',
                'max_staff' => 3,
                'max_branches' => 1,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $pro = Plan::updateOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price_monthly' => 19000,
                'max_staff' => 20,
                'max_branches' => 10,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        // Assign modules to plans
        $free->modules()->sync([
            $createdModules['employees']->id,
            $createdModules['branches']->id,
            $createdModules['products']->id,
        ]);

        $pro->modules()->sync([
            $createdModules['employees']->id,
            $createdModules['branches']->id,
            $createdModules['products']->id,
            $createdModules['sales']->id,
            $createdModules['expenses']->id,
        ]);
    }
}

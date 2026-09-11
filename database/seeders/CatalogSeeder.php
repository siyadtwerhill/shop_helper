<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Module;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $sales = Module::create(['name' => 'Sales', 'slug' => 'sales', 'sort_order' => 1]);
        $sales->features()->create(['name' => 'Orders', 'slug' => 'sales.orders']);
        $sales->features()->create(['name' => 'Refunds & Voids', 'slug' => 'sales.refunds']);

        $products = Module::create(['name' => 'Products', 'slug' => 'products', 'sort_order' => 2]);
        $products->features()->create(['name' => 'Products', 'slug' => 'products.items']);
        $products->features()->create(['name' => 'Categories', 'slug' => 'products.categories']);
        $products->features()->create(['name' => 'Brands', 'slug' => 'products.brands']);

        $employees = Module::create(['name' => 'Employees', 'slug' => 'employees', 'sort_order' => 3]);
        $employees->features()->create(['name' => 'Employees', 'slug' => 'employees.list']);
        $employees->features()->create(['name' => 'Roles & Permissions', 'slug' => 'employees.roles']);

        $reports = Module::create(['name' => 'Advanced Reports', 'slug' => 'advanced_reports', 'sort_order' => 4]);
        $reports->features()->create(['name' => 'Reports', 'slug' => 'reports.view']);

        $free = Plan::create(['name' => 'Free', 'slug' => 'free', 'max_staff' => 3, 'max_branches' => 1]);
        $free->modules()->attach([$sales->id, $products->id, $employees->id]);

        $pro = Plan::create(['name' => 'Pro', 'slug' => 'pro', 'price_monthly' => 19000, 'max_staff' => 20, 'max_branches' => 10]);
        $pro->modules()->attach([$sales->id, $products->id, $employees->id, $reports->id]);
    }
}
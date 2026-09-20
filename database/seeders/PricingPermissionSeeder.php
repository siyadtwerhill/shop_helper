<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PricingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'override-fixed-price',
            'negotiate-price',
            'approve-below-margin-sale',
            'manage-price-rules',
        ] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}
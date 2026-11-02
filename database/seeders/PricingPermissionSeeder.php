<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Feature;
use Spatie\Permission\Models\Permission;

class PricingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $pricingFeatureId = Feature::where('slug', 'products.pricing')->value('id');

        foreach ([
            'override-fixed-price',
            'negotiate-price',
            'approve-below-margin-sale',
            'manage-price-rules',
        ] as $permission) {
            $attributes = ['guard_name' => 'web'];
            if ($permission === 'negotiate-price' && $pricingFeatureId) {
                $attributes['feature_id'] = $pricingFeatureId;
                // The permission action column is an enum of view/create/edit/delete.
                // Keep negotiation inside Product Pricing's Full Control set.
                $attributes['action'] = 'edit';
            }

            Permission::updateOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                $attributes,
            );
        }
    }
}

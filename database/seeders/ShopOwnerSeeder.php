<?php

namespace Database\Seeders;

use App\Models\ShopOwner;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ShopOwnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First, create the shop owner user if it doesn't exist
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'shopowner@shoppilot.com'],
            [
                'name' => 'Shop Owner',
                'username' => 'shop_owner',
                'email' => 'shopowner@shoppilot.com',
                'password' => Hash::make('12345678'),
                'role' => 'shop_owner',
            ]
        );

        // Get the free plan (ID 1 after seeding)
        $freePlan = \App\Models\Plan::where('slug', 'free')->first();

        $shopOwner = ShopOwner::updateOrCreate(
            ['user_id' => $user->id],
            [
                'user_id' => $user->id,
                'shop_name' => 'My_Shop',
                'location' => 'Yangon',
                'plan_id' => $freePlan ? $freePlan->id : null,
                'staff_count' => 0,
            ]
        );

        // Clear existing roles for this shop owner to avoid duplicates
        Role::where('shop_owner_id', $shopOwner->id)->delete();

        // Create default roles for this shop owner
        $defaultRoles = [
            [
                'name' => 'Manager',
                'description' => 'Full access to shop operations',
                'is_system' => false,
            ],
            [
                'name' => 'Cashier',
                'description' => 'Sales and POS access',
                'is_system' => false,
            ],
            [
                'name' => 'Branch Head',
                'description' => 'Branch management with limited permissions',
                'is_system' => true, // System role - cannot be deleted
            ],
        ];

        foreach ($defaultRoles as $roleData) {
            Role::create([
                'name' => $roleData['name'],
                'description' => $roleData['description'],
                'guard_name' => 'web',
                'shop_owner_id' => $shopOwner->id,
                'is_system' => $roleData['is_system'] ?? false,
            ]);
        }
        
        echo "Created shop owner with ID: {$shopOwner->id}" . PHP_EOL;
        echo "Created roles for shop owner: " . Role::where('shop_owner_id', $shopOwner->id)->count() . PHP_EOL;
    }
}

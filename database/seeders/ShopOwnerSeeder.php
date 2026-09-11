<?php

namespace Database\Seeders;

use App\Models\ShopOwner;
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
        // Get the free plan (ID 1 after seeding)
        $freePlan = \App\Models\Plan::where('slug', 'free')->first();

        ShopOwner::updateOrCreate(
            ['user_id' => 1],
            [
                'user_id' => 1,
                'shop_name' => 'My_Shop',
                'location' => 'Yangon',
                'plan_id' => $freePlan ? $freePlan->id : null,
                'staff_count' => 0,
            ]
        );
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SuperAdminSeeder::class,
            ModuleSeeder::class,
            FeatureSeeder::class,
            ShopOwnerSeeder::class,
            PricingPermissionSeeder::class,
            BranchHeadRoleSeeder::class,
        ]);

        // Uncomment the shop seeders you want to run
        // $this->call([
        //     SupermarketSeeder::class,
        //     ClothingSeeder::class,
        //     PharmacySeeder::class,
        //     ElectronicsSeeder::class,
        //     CafeSeeder::class,
        // ]);
    }
}

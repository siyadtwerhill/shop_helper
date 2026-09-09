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
        ShopOwner::updateOrCreate(
            ['email' => 'shopowner@shophelper.com'],
            [
                'username' => 'shopowner',
                'shop_name' => 'My Shop',
                'email' => 'shopowner@shophelper.com',
                'password' => Hash::make('shopowner123'),
            ]
        );
    }
}

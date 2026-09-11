<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'superadmin@shoppilot.com'],
            [
                'name' => 'Super Admin',
                'username' => 'super_admin',
                'password' => Hash::make('12345678'),
                'role' => 'superadmin',
            ]
        );
    }
}

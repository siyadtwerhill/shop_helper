<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin@shophelper.com'],
            [
                'name' => 'Super Admin',
                'username' => 'superadmin',
                'email' => 'superadmin@shophelper.com',
                'password' => Hash::make('superadmin123'),
                'role' => 'superadmin',
            ]
        );
    }
}

<?php

namespace Database\Factories;

use App\Models\ShopOwner;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'staff']),
            'shop_owner_id' => ShopOwner::factory(),
            'branch_id' => null,
            'phone' => $this->faker->optional()->phoneNumber(),
            'status' => 'active',
        ];
    }
}

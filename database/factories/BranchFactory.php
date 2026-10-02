<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\ShopOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'shop_owner_id' => ShopOwner::factory(),
            'name' => $this->faker->company(),
            'address' => $this->faker->address(),
            'is_active' => true,
            'head_staff_id' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\ShopOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'shop_owner_id' => ShopOwner::factory(),
            'total_amount' => $this->faker->randomFloat(2, 1000, 500000),
            'total_discount' => $this->faker->randomFloat(2, 0, 50000),
            'payment_method' => $this->faker->randomElement(['cash', 'kbz_pay', 'wave_pay', 'bank_transfer']),
            'status' => $this->faker->randomElement(['pending', 'completed', 'cancelled']),
        ];
    }
}

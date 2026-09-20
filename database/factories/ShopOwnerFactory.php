<?php

namespace Database\Factories;

use App\Models\ShopOwner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShopOwnerFactory extends Factory
{
    protected $model = ShopOwner::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'shop_name' => $this->faker->company(),
            'location' => $this->faker->city(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'business_type' => $this->faker->randomElement(['retail', 'wholesale', 'restaurant', 'cafe', 'other']),
            'tax_id' => $this->faker->optional()->numerify('##########'),
            'description' => $this->faker->optional()->sentence(),
            'website' => $this->faker->optional()->url(),
            'plan_id' => null,
            'staff_count' => $this->faker->numberBetween(1, 50),
        ];
    }
}

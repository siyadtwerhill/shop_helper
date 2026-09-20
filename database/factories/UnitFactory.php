<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'shop_owner_id' => null,
            'name' => $this->faker->unique()->word(),
            'symbol' => strtoupper($this->faker->lexify('??')),
            'type' => $this->faker->randomElement(['weight', 'volume', 'length', 'quantity', 'packaging']),
            'is_system' => false,
        ];
    }

    public function system(): static
    {
        return $this->state(fn () => ['is_system' => true, 'shop_owner_id' => null]);
    }

    public function forShop(int $shopOwnerId): static
    {
        return $this->state(fn () => ['shop_owner_id' => $shopOwnerId, 'is_system' => false]);
    }
}

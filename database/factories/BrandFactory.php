<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\ShopOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        return [
            'shop_owner_id' => ShopOwner::factory(),
            'name' => $this->faker->company(),
            'slug' => $this->faker->slug(),
        ];
    }

    public function forShop(ShopOwner $shopOwner): static
    {
        return $this->state(fn () => ['shop_owner_id' => $shopOwner->id]);
    }
}

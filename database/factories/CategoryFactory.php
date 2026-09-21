<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\ShopOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'shop_owner_id' => ShopOwner::factory(),
            'name' => $this->faker->word(),
            'slug' => $this->faker->slug(),
            'parent_id' => null,
        ];
    }

    public function forShop(ShopOwner $shopOwner): static
    {
        return $this->state(fn () => ['shop_owner_id' => $shopOwner->id]);
    }
}

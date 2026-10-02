<?php

namespace Database\Factories;

use App\Models\Blueprint;
use App\Models\Product;
use App\Models\ShopOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'shop_owner_id' => ShopOwner::factory(),
            'branch_id' => null,
            'category_id' => null,
            'brand_id' => null,
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'internal_notes' => $this->faker->optional()->sentence(),
            'image_path' => null,
            'sku' => null,
            'qr_path' => null,
            'status' => 'active',
            'product_type' => 'neutral',
            'current_stock' => '0.0000',
            'base_unit_id' => null,
            'pricing_mode' => 'fixed',
            'cost_price' => $this->faker->optional()->randomFloat(2, 100, 10000),
            'min_margin_percent' => $this->faker->optional()->randomFloat(2, 5, 50),
            'min_price' => null,
            'blueprint_id' => null,
            'blueprint_version' => 1,
            'stock_mode' => 'own',
            'attributes' => null,
        ];
    }

    public function forBranch(int $branchId): self
    {
        return $this->state(fn (array $attributes) => [
            'branch_id' => $branchId,
        ]);
    }

    public function withBlueprint(Blueprint $blueprint): self
    {
        return $this->state(fn (array $attributes) => [
            'blueprint_id' => $blueprint->id,
            'blueprint_version' => $blueprint->version,
            'stock_mode' => $blueprint->capabilities['variants'] ? 'from_variants' : 'own',
        ]);
    }
}

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
            'base_unit_id' => null,
            'pricing_mode' => 'fixed',
            'cost_price' => null,
            'min_margin_percent' => null,
            'min_price' => null,
            'blueprint_id' => null,
            'blueprint_version' => 1,
            'stock_mode' => 'own',
            'custom_fields' => null,
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

    public function withFloor(string $cost, string $margin): self
    {
        return $this->state(['cost_price' => $cost, 'min_margin_percent' => $margin]);
    }
}

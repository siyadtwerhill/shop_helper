<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductPriceRuleFactory extends Factory
{
    protected $model = ProductPriceRule::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'unit_id' => ProductUnit::factory(),
            'min_quantity' => '1.0000',
            'max_quantity' => '9.0000',
            'price' => $this->faker->randomFloat(2, 100, 5000),
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn () => ['product_id' => $product->id]);
    }
}
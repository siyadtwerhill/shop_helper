<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => 'VAR-' . $this->faker->unique()->numerify('#####'),
            'attributes' => ['color' => $this->faker->safeColorName(), 'size' => $this->faker->randomElement(['S', 'M', 'L'])],
            'selling_price' => $this->faker->randomFloat(2, 100, 5000),
            'purchase_price' => $this->faker->randomFloat(2, 50, 4000),
            'status' => 'active',
        ];
    }
}

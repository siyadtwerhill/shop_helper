<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductBundle;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductBundleFactory extends Factory
{
    protected $model = ProductBundle::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'bundle_price' => $this->faker->randomFloat(2, 1000, 10000),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductActivityLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductActivityLogFactory extends Factory
{
    protected $model = ProductActivityLog::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => null,
            'action' => $this->faker->randomElement(['price_changed', 'cost_changed', 'stock_adjusted', 'barcode_added', 'variant_added', 'bundle_created']),
            'field' => $this->faker->randomElement(['selling_price', 'purchase_price', 'current_stock', 'barcode']),
            'old_value' => $this->faker->randomNumber(4),
            'new_value' => $this->faker->randomNumber(4),
            'description' => $this->faker->sentence(),
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn () => ['product_id' => $product->id]);
    }
}

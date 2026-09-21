<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductUnitFactory extends Factory
{
    protected $model = ProductUnit::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'unit_id' => Unit::factory(),
            'conversion_factor' => '1.0000',
            'selling_price' => $this->faker->randomFloat(2, 100, 5000),
            'purchase_price' => $this->faker->randomFloat(2, 50, 4000),
            'is_base' => false,
            'is_sellable' => true,
            'is_purchasable' => true,
        ];
    }

    public function base(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_base' => true,
            'conversion_factor' => '1.0000',
        ])->afterCreating(function (ProductUnit $unit) {
            $unit->load('product');
            if ($unit->product) {
                $unit->product->update(['base_unit_id' => $unit->id]);
            }
        });
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn () => ['product_id' => $product->id]);
    }
}

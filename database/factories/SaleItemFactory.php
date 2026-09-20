<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'unit_id' => ProductUnit::factory(),
            'quantity' => $this->faker->randomFloat(4, 0.1, 100),
            'base_quantity' => $this->faker->randomFloat(4, 0.1, 100),
            'unit_price' => $this->faker->randomFloat(2, 100, 50000),
            'original_unit_price' => $this->faker->randomFloat(2, 100, 50000),
            'discount_amount' => $this->faker->randomFloat(2, 0, 5000),
        ];
    }

    public function withSaleId(int $saleId): static
    {
        return $this->state(fn () => ['sale_id' => $saleId]);
    }
}

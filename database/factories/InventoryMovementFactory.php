<?php

namespace Database\Factories;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'variant_id' => null,
            'unit_id' => ProductUnit::factory(),
            'quantity' => $this->faker->randomFloat(4, 1, 100),
            'base_quantity' => $this->faker->randomFloat(4, 1, 100),
            'movement_type' => $this->faker->randomElement(['purchase', 'sale', 'sale_return', 'purchase_return', 'adjustment', 'damage', 'opening_stock']),
            'reference_type' => null,
            'reference_id' => null,
            'note' => $this->faker->optional()->sentence(),
            'created_by' => null,
        ];
    }
}

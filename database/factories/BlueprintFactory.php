<?php

namespace Database\Factories;

use App\Models\Blueprint;
use App\Models\ShopOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlueprintFactory extends Factory
{
    protected $model = Blueprint::class;

    public function definition(): array
    {
        return [
            'shop_owner_id' => ShopOwner::factory(),
            'name' => fake()->words(3, true),
            'preset_key' => fake()->randomElement(['generic', 'fashion', 'grocery', 'pharmacy', 'electronics', 'cafe']),
            'is_default' => false,
            'status' => 'active',
            'capabilities' => [
                'variants' => fake()->boolean(),
                'bundles' => fake()->boolean(),
                'batch_expiry' => fake()->boolean(),
                'serial_numbers' => fake()->boolean(),
                'decimal_quantities' => fake()->boolean(),
                'multiple_units' => true,
            ],
            'pricing_policy' => [
                'allowed_modes' => ['fixed', 'negotiable', 'wholesale'],
                'default_mode' => 'fixed',
                'min_margin_percent' => fake()->numberBetween(10, 30),
                'cost_required' => true,
                'block_below_margin' => false,
                'price_per_variant' => fake()->boolean(),
            ],
            'unit_policy' => [
                'default_base_unit_id' => null, // Will be set in afterMaking callback
                'allowed_unit_ids' => [],
                'price_per_unit' => true,
                'barcode_per_unit' => false,
                'buy_sell_different_units' => true,
            ],
            'layout' => [
                'sections' => [
                    'details' => ['label' => 'Details', 'sort_order' => 1],
                    'pricing' => ['label' => 'Pricing', 'sort_order' => 2],
                    'inventory' => ['label' => 'Inventory', 'sort_order' => 3],
                ],
            ],
            'version' => 1,
        ];
    }

    public function generic(): self
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Generic',
            'preset_key' => 'generic',
            'is_default' => true,
            'capabilities' => [
                'variants' => false,
                'bundles' => false,
                'batch_expiry' => false,
                'serial_numbers' => false,
                'decimal_quantities' => false,
                'multiple_units' => true,
            ],
        ]);
    }

    public function fashion(): self
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Fashion Retail',
            'preset_key' => 'fashion',
            'capabilities' => [
                'variants' => true,
                'bundles' => true,
                'batch_expiry' => false,
                'serial_numbers' => false,
                'decimal_quantities' => false,
                'multiple_units' => true,
            ],
            'pricing_policy' => [
                'allowed_modes' => ['fixed', 'negotiable', 'wholesale'],
                'default_mode' => 'fixed',
                'min_margin_percent' => 15,
                'cost_required' => true,
                'block_below_margin' => false,
                'price_per_variant' => true,
            ],
        ]);
    }

    public function draft(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    public function archived(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
        ]);
    }
}

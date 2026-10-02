<?php

namespace Database\Factories;

use App\Models\BlueprintField;
use App\Models\Blueprint;
use App\Models\FieldDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlueprintFieldFactory extends Factory
{
    protected $model = BlueprintField::class;

    public function definition(): array
    {
        return [
            'blueprint_id' => Blueprint::factory(),
            'field_definition_id' => FieldDefinition::factory(),
            'section' => fake()->randomElement(['details', 'pricing', 'inventory', 'variant_axes']),
            'sort_order' => fake()->numberBetween(0, 100),
            'required' => fake()->boolean(),
            'show_in_list' => fake()->boolean(),
            'show_in_pos' => fake()->boolean(),
            'show_on_label' => fake()->boolean(),
            'is_variant_axis' => fake()->boolean(),
            'is_filterable' => fake()->boolean(),
            'is_searchable' => fake()->boolean(),
            'hidden' => false,
        ];
    }

    public function details(): self
    {
        return $this->state(fn (array $attributes) => [
            'section' => 'details',
        ]);
    }

    public function variantAxis(): self
    {
        return $this->state(fn (array $attributes) => [
            'section' => 'variant_axes',
            'is_variant_axis' => true,
            'required' => true,
        ]);
    }

    public function required(): self
    {
        return $this->state(fn (array $attributes) => [
            'required' => true,
        ]);
    }

    public function showInList(): self
    {
        return $this->state(fn (array $attributes) => [
            'show_in_list' => true,
        ]);
    }

    public function showInPos(): self
    {
        return $this->state(fn (array $attributes) => [
            'show_in_pos' => true,
        ]);
    }

    public function showOnLabel(): self
    {
        return $this->state(fn (array $attributes) => [
            'show_on_label' => true,
        ]);
    }

    public function filterable(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_filterable' => true,
        ]);
    }

    public function searchable(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_searchable' => true,
        ]);
    }
}

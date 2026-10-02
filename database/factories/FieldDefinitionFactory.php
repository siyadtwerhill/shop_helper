<?php

namespace Database\Factories;

use App\Models\FieldDefinition;
use App\Models\ShopOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

class FieldDefinitionFactory extends Factory
{
    protected $model = FieldDefinition::class;

    public function definition(): array
    {
        return [
            'shop_owner_id' => ShopOwner::factory(),
            'key' => fake()->unique()->slug(),
            'label' => fake()->words(2, true),
            'type' => fake()->randomElement(['text', 'textarea', 'number', 'decimal', 'select', 'multi_select', 'checkbox', 'date', 'boolean', 'json']),
            'options' => fake()->boolean() ? fake()->words(3) : null,
            'validation' => fake()->boolean() ? ['nullable', 'string', 'max:255'] : null,
        ];
    }

    public function text(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'text',
            'validation' => ['nullable', 'string', 'max:255'],
        ]);
    }

    public function select(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'select',
            'options' => fake()->words(3),
            'validation' => ['nullable', 'string'],
        ]);
    }

    public function number(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'number',
            'validation' => ['nullable', 'integer'],
        ]);
    }

    public function required(): self
    {
        return $this->state(fn (array $attributes) => [
            'validation' => ['required', 'string'],
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductBarcode;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductBarcodeFactory extends Factory
{
    protected $model = ProductBarcode::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'barcode' => $this->faker->numerify('#############'),
            'type' => $this->faker->randomElement(['auto_generated', 'manufacturer', 'scanned']),
            'is_primary' => false,
        ];
    }

    public function forProduct(\App\Models\Product $product): static
    {
        return $this->state(fn () => ['product_id' => $product->id, 'shop_owner_id' => $product->shop_owner_id]);
    }
}

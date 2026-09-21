<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductBundleItemFactory extends Factory
{
    protected $model = ProductBundleItem::class;

    public function definition(): array
    {
        return [
            'product_bundle_id' => ProductBundle::factory(),
            'component_product_id' => Product::factory(),
            'unit_id' => ProductUnit::factory(),
            'quantity' => '1.0000',
        ];
    }
}

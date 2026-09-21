<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBundleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_bundle_with_components(): void
    {
        $user = User::factory()->create();
        $bundleProduct = Product::factory()->create();
        $coffee = Product::factory()->create();
        $coffeeUnit = ProductUnit::factory()->for($coffee)->base()->create();

        $response = $this->actingAs($user)->postJson("/api/products/{$bundleProduct->id}/bundle", [
            'bundle_price' => 7500,
            'items' => [
                ['component_product_id' => $coffee->id, 'unit_id' => $coffeeUnit->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('product_bundles', ['product_id' => $bundleProduct->id, 'bundle_price' => 7500]);
        $this->assertDatabaseHas('product_bundle_items', ['component_product_id' => $coffee->id]);
    }

    public function test_a_product_cannot_be_turned_into_a_bundle_twice(): void
    {
        $user = User::factory()->create();
        $bundleProduct = Product::factory()->create();
        $coffee = Product::factory()->create();
        $coffeeUnit = ProductUnit::factory()->for($coffee)->base()->create();

        $payload = [
            'bundle_price' => 7500,
            'items' => [['component_product_id' => $coffee->id, 'unit_id' => $coffeeUnit->id, 'quantity' => 1]],
        ];

        $this->actingAs($user)->postJson("/api/products/{$bundleProduct->id}/bundle", $payload)->assertCreated();
        $this->actingAs($user)->postJson("/api/products/{$bundleProduct->id}/bundle", $payload)->assertStatus(409);
    }

    public function test_a_bundle_cannot_contain_itself_as_a_component(): void
    {
        $user = User::factory()->create();
        $bundleProduct = Product::factory()->create();
        $unit = ProductUnit::factory()->for($bundleProduct)->base()->create();

        $this->actingAs($user)->postJson("/api/products/{$bundleProduct->id}/bundle", [
            'bundle_price' => 7500,
            'items' => [['component_product_id' => $bundleProduct->id, 'unit_id' => $unit->id, 'quantity' => 1]],
        ])->assertStatus(422);
    }
}

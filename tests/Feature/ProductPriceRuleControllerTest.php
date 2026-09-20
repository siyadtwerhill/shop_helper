<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductPriceRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::firstOrCreate(['name' => 'manage-price-rules', 'guard_name' => 'web']);
    }

    public function test_creating_a_price_rule_requires_manage_price_rules_permission(): void
    {
        $user = User::factory()->create(); // no permission granted
        $product = Product::factory()->create();
        $unit = ProductUnit::factory()->forProduct($product)->base()->create();

        $this->actingAs($user)->postJson("/api/products/{$product->id}/price-rules", [
            'unit_id' => $unit->id,
            'min_quantity' => 10,
            'max_quantity' => 49,
            'price' => 4500,
        ])->assertForbidden();
    }

    public function test_user_with_permission_can_create_a_tier(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage-price-rules');
        $product = Product::factory()->create();
        $unit = ProductUnit::factory()->forProduct($product)->base()->create();

        $response = $this->actingAs($user)->postJson("/api/products/{$product->id}/price-rules", [
            'unit_id' => $unit->id,
            'min_quantity' => 10,
            'max_quantity' => 49,
            'price' => 4500,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('product_price_rules', ['product_id' => $product->id, 'price' => 4500]);
    }
}
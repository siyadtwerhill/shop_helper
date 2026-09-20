<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Models\User;
use App\Models\ShopOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_unit_attached_to_a_product_automatically_becomes_the_base_unit(): void
    {
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop = ShopOwner::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create(['shop_owner_id' => $shop->id]);
        $kg = Unit::factory()->create(['name' => 'Kilogram']);

        $response = $this->actingAs($user)->postJson("/api/products/{$product->id}/units", [
            'unit_id' => $kg->id,
            'conversion_factor' => 1,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('product_units', [
            'product_id' => $product->id,
            'unit_id' => $kg->id,
            'is_base' => 1,
        ]);
        $this->assertNotNull($product->refresh()->base_unit_id);
    }

    public function test_setting_a_second_unit_as_base_clears_the_previous_base_unit(): void
    {
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop = ShopOwner::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create(['shop_owner_id' => $shop->id]);
        $kg = ProductUnit::factory()->for($product)->base()->create();
        $bag = Unit::factory()->create(['name' => 'Bag']);

        $response = $this->actingAs($user)->postJson("/api/products/{$product->id}/units", [
            'unit_id' => $bag->id,
            'conversion_factor' => 20,
            'is_base' => true,
        ]);

        $response->assertCreated();
        $this->assertFalse($kg->refresh()->is_base);
        $this->assertSame(20.0, (float) $response->json('conversion_factor'));
    }

    public function test_the_same_unit_cannot_be_attached_twice_to_one_product(): void
    {
        $product = Product::factory()->create();
        $kg = Unit::factory()->create();
        ProductUnit::factory()->for($product)->for($kg, 'unit')->create();

        $this->expectException(\Illuminate\Database\QueryException::class);
        ProductUnit::factory()->for($product)->for($kg, 'unit')->create();
    }

    public function test_500g_can_be_priced_independently_of_half_the_1kg_price(): void
    {
        $product = Product::factory()->create();
        $kg = ProductUnit::factory()->for($product)->base()->create(['selling_price' => '4500.00']);
        $grams = ProductUnit::factory()->for($product)->create([
            'conversion_factor' => '0.5000',
            'selling_price' => '2500.00',
        ]);

        $this->assertSame('2500.00', $grams->selling_price);
        $this->assertNotEquals(bcdiv($kg->selling_price, '2', 2), $grams->selling_price);
    }

    public function test_base_unit_cannot_be_deleted_while_it_is_the_only_or_current_base(): void
    {
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop = ShopOwner::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create(['shop_owner_id' => $shop->id]);
        $base = ProductUnit::factory()->for($product)->base()->create();

        $this->actingAs($user)
            ->deleteJson("/api/products/{$product->id}/units/{$base->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('product_units', ['id' => $base->id]);
    }

    public function test_conversion_factor_supports_four_decimal_places(): void
    {
        $productUnit = ProductUnit::factory()->create(['conversion_factor' => '0.0010']);

        $this->assertSame('0.0010', $productUnit->refresh()->conversion_factor);
    }
}
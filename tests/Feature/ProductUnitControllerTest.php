<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ShopOwner;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductUnitControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_index_returns_product_units(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();
        ProductUnit::factory()->for($product)->count(3)->create();

        $response = $this->actingAs($user)->getJson("/api/products/{$product->id}/units");

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_store_creates_product_unit(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();
        $unit = Unit::factory()->system()->create();

        $response = $this->actingAs($user)->postJson("/api/products/{$product->id}/units", [
            'unit_id' => $unit->id,
            'conversion_factor' => '1.0000',
            'selling_price' => '100.00',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('product_units', [
            'product_id' => $product->id,
            'unit_id' => $unit->id,
        ]);
    }

    public function test_store_sets_base_unit_when_requested(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();
        $unit = Unit::factory()->system()->create();

        $response = $this->actingAs($user)->postJson("/api/products/{$product->id}/units", [
            'unit_id' => $unit->id,
            'conversion_factor' => '1.0000',
            'is_base' => true,
        ]);

        $response->assertCreated();
        $this->assertEquals($unit->id, $product->refresh()->base_unit_id);
    }

    public function test_store_sets_base_unit_automatically_on_first_unit(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();
        $unit = Unit::factory()->system()->create();

        $response = $this->actingAs($user)->postJson("/api/products/{$product->id}/units", [
            'unit_id' => $unit->id,
            'conversion_factor' => '1.0000',
        ]);

        $response->assertCreated();
        $this->assertEquals($unit->id, $product->refresh()->base_unit_id);
    }

    public function test_update_modifies_product_unit(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();
        $productUnit = ProductUnit::factory()->for($product)->create(['selling_price' => '100.00']);

        $response = $this->actingAs($user)->putJson("/api/products/{$product->id}/units/{$productUnit->id}", [
            'selling_price' => '150.00',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('product_units', [
            'id' => $productUnit->id,
            'selling_price' => '150.00',
        ]);
    }

    public function test_update_changes_base_unit(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();
        $oldBase = ProductUnit::factory()->for($product)->base()->create();
        $newUnit = ProductUnit::factory()->for($product)->create();

        $response = $this->actingAs($user)->putJson("/api/products/{$product->id}/units/{$newUnit->id}", [
            'is_base' => true,
        ]);

        $response->assertOk();
        $this->assertEquals($newUnit->id, $product->refresh()->base_unit_id);
    }

    public function test_update_fails_for_wrong_product(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $productA = Product::factory()->for($shop)->create();
        $productB = Product::factory()->for($shop)->create();
        $unit = Unit::factory()->system()->create();
        $productUnit = ProductUnit::create([
            'product_id' => $productA->id,
            'unit_id' => $unit->id,
            'conversion_factor' => '1.0000',
        ]);

        $response = $this->actingAs($user)->putJson("/api/products/{$productB->id}/units/{$productUnit->id}", [
            'selling_price' => '150.00',
        ]);

        $response->assertNotFound();
    }

    public function test_delete_deletes_product_unit(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();
        $productUnit = ProductUnit::factory()->for($product)->create();

        $response = $this->actingAs($user)->deleteJson("/api/products/{$product->id}/units/{$productUnit->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('product_units', ['id' => $productUnit->id]);
    }

    public function test_delete_fails_for_base_unit(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();
        $productUnit = ProductUnit::factory()->for($product)->base()->create();

        $response = $this->actingAs($user)->deleteJson("/api/products/{$product->id}/units/{$productUnit->id}");

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Cannot delete the base unit. Assign a new base unit first.');
    }

    public function test_delete_fails_for_wrong_product(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $productA = Product::factory()->for($shop)->create();
        $productB = Product::factory()->for($shop)->create();
        $unit = Unit::factory()->system()->create();
        $productUnit = ProductUnit::create([
            'product_id' => $productA->id,
            'unit_id' => $unit->id,
            'conversion_factor' => '1.0000',
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/products/{$productB->id}/units/{$productUnit->id}");

        $response->assertNotFound();
    }
}

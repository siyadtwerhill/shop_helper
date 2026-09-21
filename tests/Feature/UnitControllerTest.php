<?php

namespace Tests\Feature;

use App\Models\ShopOwner;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_index_returns_system_and_custom_units(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        Unit::factory()->system()->count(3)->create();
        Unit::factory()->forShop($shop->id)->count(2)->create();

        $response = $this->actingAs($user)->getJson('/api/units');

        $response->assertOk();
        $this->assertCount(5, $response->json());
    }

    public function test_index_orders_system_units_first(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        Unit::factory()->forShop($shop->id)->create(['name' => 'Custom']);
        Unit::factory()->system()->create(['name' => 'KG']);

        $response = $this->actingAs($user)->getJson('/api/units');

        $response->assertOk();
        $units = $response->json();
        $this->assertTrue($units[0]['is_system']);
        $this->assertFalse($units[1]['is_system']);
    }

    public function test_store_creates_custom_unit(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();

        $response = $this->actingAs($user)->postJson('/api/units', [
            'name' => 'Bag',
            'symbol' => 'BAG',
            'type' => 'packaging',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('units', [
            'name' => 'Bag',
            'symbol' => 'BAG',
            'shop_owner_id' => $shop->id,
            'is_system' => false,
        ]);
    }

    public function test_update_modifies_custom_unit(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $unit = Unit::factory()->forShop($shop->id)->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->putJson("/api/units/{$unit->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'name' => 'New Name',
        ]);
    }

    public function test_update_fails_for_system_unit(): void
    {
        $user = User::factory()->create();
        $unit = Unit::factory()->system()->create();

        $response = $this->actingAs($user)->putJson("/api/units/{$unit->id}", [
            'name' => 'New Name',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('message', 'System units cannot be edited.');
    }

    public function test_update_fails_for_other_shop_unit(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $otherShop = ShopOwner::factory()->create();
        $unit = Unit::factory()->forShop($otherShop->id)->create();

        $response = $this->actingAs($user)->putJson("/api/units/{$unit->id}", [
            'name' => 'New Name',
        ]);

        $response->assertForbidden();
    }

    public function test_delete_deletes_custom_unit(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $unit = Unit::factory()->forShop($shop->id)->create();

        $response = $this->actingAs($user)->deleteJson("/api/units/{$unit->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
    }

    public function test_delete_fails_for_system_unit(): void
    {
        $user = User::factory()->create();
        $unit = Unit::factory()->system()->create();

        $response = $this->actingAs($user)->deleteJson("/api/units/{$unit->id}");

        $response->assertForbidden();
        $response->assertJsonPath('message', 'System units cannot be deleted.');
    }

    public function test_delete_fails_when_unit_in_use(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $unit = Unit::factory()->forShop($shop->id)->create();
        $product = \App\Models\Product::factory()->for($shop)->create();
        \App\Models\ProductUnit::factory()->for($product)->create(['unit_id' => $unit->id]);

        $response = $this->actingAs($user)->deleteJson("/api/units/{$unit->id}");

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Unit is still in use on a product.');
    }
}

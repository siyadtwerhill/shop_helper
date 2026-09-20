<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use App\Models\ShopOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_includes_system_units_and_own_tenant_units_but_not_other_tenants(): void
    {
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop = ShopOwner::factory()->create(['user_id' => $user->id]);
        Unit::factory()->system()->create(['name' => 'Kilogram']);
        Unit::factory()->forShop($shop->id)->create(['name' => 'My Bag']);
        Unit::factory()->forShop(999)->create(['name' => 'Other Shop Unit']);

        $response = $this->actingAs($user)->getJson('/api/units');

        $response->assertOk();
        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('Kilogram'));
        $this->assertTrue($names->contains('My Bag'));
        $this->assertFalse($names->contains('Other Shop Unit'));
    }

    public function test_tenant_can_create_a_custom_packaging_unit(): void
    {
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop = ShopOwner::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/units', [
            'name' => 'Bag',
            'symbol' => 'အိတ်',
            'type' => 'packaging',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('units', [
            'name' => 'Bag',
            'shop_owner_id' => $shop->id,
            'is_system' => false,
        ]);
    }

    public function test_system_units_cannot_be_edited_or_deleted(): void
    {
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop = ShopOwner::factory()->create(['user_id' => $user->id]);
        $unit = Unit::factory()->system()->create();

        $this->actingAs($user)->putJson("/api/units/{$unit->id}", ['name' => 'Changed'])
            ->assertForbidden();

        $this->actingAs($user)->deleteJson("/api/units/{$unit->id}")
            ->assertForbidden();
    }

    public function test_duplicate_unit_name_within_the_same_tenant_is_rejected_at_db_level(): void
    {
        $shop = ShopOwner::factory()->create();
        Unit::factory()->forShop($shop->id)->create(['name' => 'Bag']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Unit::factory()->forShop($shop->id)->create(['name' => 'Bag']);
    }
}
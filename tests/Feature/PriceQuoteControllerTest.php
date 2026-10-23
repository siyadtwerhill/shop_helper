<?php

namespace Tests\Feature;

use App\Enums\PricingMode;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ShopOwner;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PriceQuoteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        Permission::firstOrCreate(['name' => 'products.pricing.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'negotiate-price', 'guard_name' => 'web']);
    }

    public function test_quote_returns_422_with_a_message_when_margin_is_violated(): void
    {
        $shopOwner = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'staff']);
        $staff = Staff::factory()->for($shopOwner)->create(['user_id' => $user->id]);
        
        app(PermissionRegistrar::class)->setPermissionsTeamId($shopOwner->id);
        $user->givePermissionTo('products.pricing.view');
        $user->givePermissionTo('negotiate-price');

        $product = Product::factory()->for($shopOwner)->create([
            'pricing_mode' => PricingMode::Negotiable->value,
            'cost_price' => '4000.00',
            'min_margin_percent' => '10.00',
        ]);
        $unit = ProductUnit::factory()->forProduct($product)->base()->create();

        $response = $this->actingAs($user)->postJson('/api/pos/price-quote', [
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'quantity' => 1,
            'requested_price' => 4200,
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message']);
    }

    public function test_quote_returns_the_resolved_price_for_a_valid_wholesale_request(): void
    {
        $shopOwner = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'staff']);
        $staff = Staff::factory()->for($shopOwner)->create(['user_id' => $user->id]);
        
        app(PermissionRegistrar::class)->setPermissionsTeamId($shopOwner->id);
        $user->givePermissionTo('products.pricing.view');
        
        $product = Product::factory()->for($shopOwner)->create(['pricing_mode' => PricingMode::Wholesale->value]);
        $unit = ProductUnit::factory()->forProduct($product)->base()->create(['selling_price' => '5000.00']);

        $response = $this->actingAs($user)->postJson('/api/pos/price-quote', [
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'quantity' => 3,
        ]);

        $response->assertOk();
        $response->assertJsonPath('price', '5000.00');
        $response->assertJsonPath('requires_approval', false);
    }
}
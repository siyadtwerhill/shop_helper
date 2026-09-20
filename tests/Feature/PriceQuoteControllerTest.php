<?php

namespace Tests\Feature;

use App\Enums\PricingMode;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PriceQuoteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::firstOrCreate(['name' => 'negotiate-price', 'guard_name' => 'web']);
    }

    public function test_quote_returns_422_with_a_message_when_margin_is_violated(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('negotiate-price');

        $product = Product::factory()->create([
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
        $user = User::factory()->create();
        $product = Product::factory()->create(['pricing_mode' => PricingMode::Wholesale->value]);
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
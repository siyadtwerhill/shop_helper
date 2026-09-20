<?php

namespace Tests\Unit\Services;

use App\Enums\PricingMode;
use App\Exceptions\MinimumMarginViolationException;
use App\Exceptions\PriceBelowMinimumException;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\ProductUnit;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PricingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PricingService();

        foreach (['override-fixed-price', 'negotiate-price', 'approve-below-margin-sale'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }

    public function test_fixed_mode_returns_list_price_when_no_price_requested(): void
    {
        $product = Product::factory()->create(['pricing_mode' => PricingMode::Fixed->value]);
        $unit = ProductUnit::factory()->forProduct($product)->base()->create(['selling_price' => '4500.00']);
        $user = User::factory()->create();

        $result = $this->service->resolvePrice($product, $unit, 1, null, $user);

        $this->assertSame('4500.00', $result['price']);
        $this->assertFalse($result['requires_approval']);
    }

    public function test_fixed_mode_rejects_a_different_price_without_override_permission(): void
    {
        $product = Product::factory()->create(['pricing_mode' => PricingMode::Fixed->value]);
        $unit = ProductUnit::factory()->forProduct($product)->base()->create(['selling_price' => '4500.00']);
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service->resolvePrice($product, $unit, 1, '4000.00', $user);
    }

    public function test_fixed_mode_allows_override_with_permission(): void
    {
        $product = Product::factory()->create(['pricing_mode' => PricingMode::Fixed->value]);
        $unit = ProductUnit::factory()->forProduct($product)->base()->create(['selling_price' => '4500.00']);
        $user = User::factory()->create();
        $user->givePermissionTo('override-fixed-price');

        $result = $this->service->resolvePrice($product, $unit, 1, '4000.00', $user);

        $this->assertSame('4000.00', $result['price']);
    }

    public function test_negotiable_mode_requires_negotiate_permission(): void
    {
        $product = Product::factory()->create(['pricing_mode' => PricingMode::Negotiable->value]);
        $unit = ProductUnit::factory()->forProduct($product)->base()->create();
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service->resolvePrice($product, $unit, 1, '3000.00', $user);
    }

    public function test_price_range_mode_rejects_price_below_configured_minimum(): void
    {
        $product = Product::factory()->create([
            'pricing_mode' => PricingMode::PriceRange->value,
            'min_price' => '4000.00',
        ]);
        $unit = ProductUnit::factory()->forProduct($product)->base()->create(['selling_price' => '4500.00']);
        $user = User::factory()->create();

        $this->expectException(PriceBelowMinimumException::class);
        $this->service->resolvePrice($product, $unit, 1, '3500.00', $user);
    }

    public function test_wholesale_mode_picks_the_matching_quantity_tier(): void
    {
        $product = Product::factory()->create(['pricing_mode' => PricingMode::Wholesale->value]);
        $unit = ProductUnit::factory()->forProduct($product)->base()->create(['selling_price' => '5000.00']);
        ProductPriceRule::factory()->forProduct($product)->create(['unit_id' => $unit->id, 'min_quantity' => 1, 'max_quantity' => 9, 'price' => '5000.00']);
        ProductPriceRule::factory()->forProduct($product)->create(['unit_id' => $unit->id, 'min_quantity' => 10, 'max_quantity' => 49, 'price' => '4500.00']);
        ProductPriceRule::factory()->forProduct($product)->create(['unit_id' => $unit->id, 'min_quantity' => 50, 'max_quantity' => null, 'price' => '4000.00']);
        $user = User::factory()->create();

        $this->assertSame('5000.00', $this->service->resolvePrice($product, $unit, 5, null, $user)['price']);
        $this->assertSame('4500.00', $this->service->resolvePrice($product, $unit, 20, null, $user)['price']);
        $this->assertSame('4000.00', $this->service->resolvePrice($product, $unit, 100, null, $user)['price']);
    }

    public function test_wholesale_mode_falls_back_to_list_price_when_no_tier_matches(): void
    {
        $product = Product::factory()->create(['pricing_mode' => PricingMode::Wholesale->value]);
        $unit = ProductUnit::factory()->forProduct($product)->base()->create(['selling_price' => '5000.00']);
        $user = User::factory()->create();

        $this->assertSame('5000.00', $this->service->resolvePrice($product, $unit, 3, null, $user)['price']);
    }

    public function test_margin_protection_blocks_a_below_floor_price_without_approval_permission(): void
    {
        $product = Product::factory()->create([
            'pricing_mode' => PricingMode::Negotiable->value,
            'cost_price' => '4000.00',
            'min_margin_percent' => '10.00', // floor = 4400.00
        ]);
        $unit = ProductUnit::factory()->forProduct($product)->base()->create();
        $user = User::factory()->create();
        $user->givePermissionTo('negotiate-price');

        $this->expectException(MinimumMarginViolationException::class);
        $this->service->resolvePrice($product, $unit, 1, '4200.00', $user);
    }

    public function test_margin_protection_allows_below_floor_price_with_approval_permission_and_flags_it(): void
    {
        $product = Product::factory()->create([
            'pricing_mode' => PricingMode::Negotiable->value,
            'cost_price' => '4000.00',
            'min_margin_percent' => '10.00',
        ]);
        $unit = ProductUnit::factory()->forProduct($product)->base()->create();
        $user = User::factory()->create();
        $user->givePermissionTo(['negotiate-price', 'approve-below-margin-sale']);

        $result = $this->service->resolvePrice($product, $unit, 1, '4200.00', $user);

        $this->assertSame('4200.00', $result['price']);
        $this->assertTrue($result['requires_approval']);
    }

    public function test_margin_protection_is_a_no_op_when_cost_price_or_margin_is_unset(): void
    {
        $product = Product::factory()->create([
            'pricing_mode' => PricingMode::Negotiable->value,
            'cost_price' => null,
        ]);
        $unit = ProductUnit::factory()->forProduct($product)->base()->create();
        $user = User::factory()->create();
        $user->givePermissionTo('negotiate-price');

        $result = $this->service->resolvePrice($product, $unit, 1, '1.00', $user);

        $this->assertFalse($result['requires_approval']);
    }
}
<?php

use App\Enums\StockMode;
use App\Models\Blueprint as ProductBlueprint;
use App\Models\FieldDefinition;
use App\Models\BlueprintField;
use App\Models\ShopOwner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint as SchemaBlueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create a Generic blueprint for each shop
        $shops = ShopOwner::all();

        foreach ($shops as $shop) {
            $blueprint = ProductBlueprint::create([
                'shop_owner_id' => $shop->id,
                'name' => 'Generic',
                'preset_key' => 'generic',
                'is_default' => true,
                'status' => 'active',
                'capabilities' => json_encode([
                    'variants' => false,
                    'bundles' => false,
                    'batch_expiry' => false,
                    'serial_numbers' => false,
                    'decimal_quantities' => false,
                    'multiple_units' => true,
                ]),
                'pricing_policy' => json_encode([
                    'allowed_modes' => ['fixed', 'negotiable', 'wholesale'],
                    'default_mode' => 'fixed',
                    'min_margin_percent' => 15,
                    'cost_required' => true,
                    'block_below_margin' => false,
                    'price_per_variant' => false,
                ]),
                'unit_policy' => json_encode([
                    'default_base_unit' => 'piece',
                    'allowed_units' => ['piece', 'box', 'dozen'],
                    'price_per_unit' => true,
                    'barcode_per_unit' => false,
                    'buy_sell_different_units' => true,
                ]),
                'layout' => json_encode([
                    'sections' => [
                        'details' => ['label' => 'Details', 'sort_order' => 1],
                        'pricing' => ['label' => 'Pricing', 'sort_order' => 2],
                        'inventory' => ['label' => 'Inventory', 'sort_order' => 3],
                    ],
                ]),
                'version' => 1,
            ]);

            // Map existing products to this blueprint
            $shop->products()->update([
                'blueprint_id' => $blueprint->id,
                'blueprint_version' => 1,
            ]);

            // Map product_type to stock_mode
            $shop->products()->where('product_type', 'neutral')->update(['stock_mode' => StockMode::Own->value]);
            $shop->products()->where('product_type', 'variant')->update(['stock_mode' => StockMode::FromVariants->value]);
            $shop->products()->where('product_type', 'bundle')->update(['stock_mode' => StockMode::FromComponents->value]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a data migration - we won't reverse it
        // In production, you'd want to keep the blueprints
    }
};

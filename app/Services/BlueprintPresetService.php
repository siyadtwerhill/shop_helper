<?php

namespace App\Services;

use App\Models\Blueprint;
use App\Models\FieldDefinition;
use App\Models\BlueprintField;
use App\Models\ShopOwner;

class BlueprintPresetService
{
    /**
     * Create a Generic blueprint for a shop
     */
    public function createGenericBlueprint(ShopOwner $shop): Blueprint
    {
        $blueprint = Blueprint::create([
            'shop_owner_id' => $shop->id,
            'name' => 'Generic',
            'preset_key' => 'generic',
            'is_default' => true,
            'status' => 'active',
            'capabilities' => [
                'variants' => false,
                'bundles' => false,
                'batch_expiry' => false,
                'serial_numbers' => false,
                'decimal_quantities' => true,
                'multiple_units' => true,
            ],
            'pricing_policy' => [
                'allowed_modes' => ['fixed', 'negotiable', 'price_range', 'wholesale'],
                'default_mode' => 'fixed',
                'min_margin_percent' => null,
                'cost_required' => false,
                'block_below_margin' => false,
                'price_per_variant' => false,
                'pos_price_override' => 'anyone',
            ],
            'unit_policy' => [
                'default_base_unit_id' => null,
                'allowed_unit_ids' => [],
                'price_per_unit' => true,
                'barcode_per_unit' => false,
                'buy_sell_different_units' => true,
            ],
            'layout' => [
                'sections' => [
                    'details' => ['label' => 'Details', 'sort_order' => 1],
                    'pricing' => ['label' => 'Pricing', 'sort_order' => 2],
                    'inventory' => ['label' => 'Inventory', 'sort_order' => 3],
                ],
            ],
            'version' => 1,
        ]);

        return $blueprint;
    }

    /**
     * Create a Fashion blueprint for a shop
     */
    public function createFashionBlueprint(ShopOwner $shop): Blueprint
    {
        $blueprint = Blueprint::create([
            'shop_owner_id' => $shop->id,
            'name' => 'Fashion Retail',
            'preset_key' => 'fashion',
            'is_default' => false,
            'status' => 'active',
            'capabilities' => [
                'variants' => true,
                'bundles' => true,
                'batch_expiry' => false,
                'serial_numbers' => false,
                'decimal_quantities' => false,
                'multiple_units' => true,
            ],
            'pricing_policy' => [
                'allowed_modes' => ['fixed', 'negotiable', 'price_range', 'wholesale'],
                'default_mode' => 'fixed',
                'min_margin_percent' => 15,
                'cost_required' => true,
                'block_below_margin' => false,
                'price_per_variant' => true,
                'pos_price_override' => false,
            ],
            'unit_policy' => [
                'default_base_unit_id' => null,
                'allowed_unit_ids' => [],
                'price_per_unit' => true,
                'barcode_per_unit' => false,
                'buy_sell_different_units' => true,
            ],
            'layout' => [
                'sections' => [
                    'details' => ['label' => 'Details', 'sort_order' => 1],
                    'variant_axes' => ['label' => 'Variant Axes', 'sort_order' => 2],
                    'pricing' => ['label' => 'Pricing', 'sort_order' => 3],
                    'inventory' => ['label' => 'Inventory', 'sort_order' => 4],
                ],
            ],
            'version' => 1,
        ]);

        // Add field definitions for fashion
        $materialField = FieldDefinition::create([
            'shop_owner_id' => $shop->id,
            'key' => 'material',
            'label' => 'Material',
            'type' => 'text',
            'options' => null,
            'validation' => ['nullable', 'string', 'max:255'],
        ]);

        $seasonField = FieldDefinition::create([
            'shop_owner_id' => $shop->id,
            'key' => 'season',
            'label' => 'Season',
            'type' => 'select',
            'options' => ['Spring', 'Summer', 'Fall', 'Winter'],
            'validation' => ['nullable', 'string'],
        ]);

        $colorField = FieldDefinition::create([
            'shop_owner_id' => $shop->id,
            'key' => 'color',
            'label' => 'Color',
            'type' => 'select',
            'options' => ['Black', 'White', 'Navy', 'Red', 'Blue', 'Green'],
            'validation' => ['nullable', 'string'],
        ]);

        $sizeField = FieldDefinition::create([
            'shop_owner_id' => $shop->id,
            'key' => 'size',
            'label' => 'Size',
            'type' => 'select',
            'options' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
            'validation' => ['nullable', 'string'],
        ]);

        // Add fields to blueprint
        BlueprintField::create([
            'blueprint_id' => $blueprint->id,
            'field_definition_id' => $materialField->id,
            'section' => 'details',
            'sort_order' => 1,
            'required' => false,
            'show_in_list' => true,
            'show_in_pos' => false,
            'show_on_label' => false,
            'is_variant_axis' => false,
            'is_filterable' => true,
            'is_searchable' => true,
            'hidden' => false,
        ]);

        BlueprintField::create([
            'blueprint_id' => $blueprint->id,
            'field_definition_id' => $seasonField->id,
            'section' => 'details',
            'sort_order' => 2,
            'required' => false,
            'show_in_list' => true,
            'show_in_pos' => false,
            'show_on_label' => false,
            'is_variant_axis' => false,
            'is_filterable' => true,
            'is_searchable' => true,
            'hidden' => false,
        ]);

        BlueprintField::create([
            'blueprint_id' => $blueprint->id,
            'field_definition_id' => $colorField->id,
            'section' => 'variant_axes',
            'sort_order' => 1,
            'required' => true,
            'show_in_list' => true,
            'show_in_pos' => true,
            'show_on_label' => true,
            'is_variant_axis' => true,
            'is_filterable' => true,
            'is_searchable' => true,
            'hidden' => false,
        ]);

        BlueprintField::create([
            'blueprint_id' => $blueprint->id,
            'field_definition_id' => $sizeField->id,
            'section' => 'variant_axes',
            'sort_order' => 2,
            'required' => true,
            'show_in_list' => true,
            'show_in_pos' => true,
            'show_on_label' => true,
            'is_variant_axis' => true,
            'is_filterable' => true,
            'is_searchable' => true,
            'hidden' => false,
        ]);

        return $blueprint;
    }

    /**
     * Ensure a blueprint that has variants=true has at least Color and Size axes.
     * Safe to call repeatedly — firstOrCreate is idempotent.
     */
    public function ensureVariantAxes(Blueprint $bp): void
    {
        $order = BlueprintField::where('blueprint_id', $bp->id)->where('is_variant_axis', true)->count();

        foreach (['color' => 'Color', 'size' => 'Size'] as $key => $label) {
            $def = FieldDefinition::firstOrCreate(
                ['shop_owner_id' => $bp->shop_owner_id, 'key' => $key],
                ['label' => $label, 'type' => 'text', 'options' => null, 'validation' => null]
            );
            BlueprintField::firstOrCreate(
                ['blueprint_id' => $bp->id, 'field_definition_id' => $def->id],
                [
                    'section'        => 'variant_axes',
                    'sort_order'     => ++$order,
                    'required'       => false,
                    'is_variant_axis'=> true,
                    'show_in_list'   => false,
                    'show_in_pos'    => true,
                    'show_on_label'  => true,
                    'is_filterable'  => false,
                    'is_searchable'  => false,
                    'hidden'         => false,
                ]
            );
        }
    }

    /**
     * Get available presets
     */
    public function getAvailablePresets(): array
    {
        return [
            [
                'key' => 'generic',
                'name' => 'Generic',
                'description' => 'Simple products without variants or bundles',
                'icon' => 'Package',
            ],
            [
                'key' => 'fashion',
                'name' => 'Fashion',
                'description' => 'Clothing and accessories with size/color variants',
                'icon' => 'Shirt',
            ],
            [
                'key' => 'grocery',
                'name' => 'Grocery',
                'description' => 'Food items with expiry dates and batch tracking',
                'icon' => 'ShoppingCart',
            ],
            [
                'key' => 'pharmacy',
                'name' => 'Pharmacy',
                'description' => 'Medicines with serial numbers and expiry tracking',
                'icon' => 'Pill',
            ],
            [
                'key' => 'electronics',
                'name' => 'Electronics',
                'description' => 'Tech products with serial numbers and warranties',
                'icon' => 'Cpu',
            ],
            [
                'key' => 'cafe',
                'name' => 'Cafe',
                'description' => 'Food and beverages with decimal quantities',
                'icon' => 'Coffee',
            ],
        ];
    }
}

<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Blueprint;
use App\Models\BlueprintField;
use App\Models\Category;
use App\Models\Customer;
use App\Models\FieldDefinition;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\ShopOwner;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CafeSeeder extends Seeder
{
    public function run(): void
    {
        // Create user for this shop
        $user = User::firstOrCreate(
            ['email' => 'moekyaw@cafe.com'],
            [
                'name' => 'Moe Kyaw Owner',
                'username' => 'moekyaw',
                'email' => 'moekyaw@cafe.com',
                'password' => Hash::make('password123'),
                'role' => 'shop_owner',
            ]
        );

        // Get free plan
        $freePlan = \App\Models\Plan::where('slug', 'free')->first();

        // Create shop owner
        $shopOwner = ShopOwner::firstOrCreate(
            ['user_id' => $user->id],
            [
                'user_id' => $user->id,
                'shop_name' => 'Moe Kyaw Coffee and Bakery',
                'location' => 'Yangon',
                'address' => '890 University Avenue Road, Yangon',
                'phone' => '09-888888888',
                'business_type' => 'Cafe and Bakery',
                'plan_id' => $freePlan ? $freePlan->id : null,
                'staff_count' => 0,
                'description' => 'Cozy cafe with premium coffee and fresh baked goods',
            ]
        );

        echo "Created Shop Owner: {$shopOwner->shop_name} (ID: {$shopOwner->id})" . PHP_EOL;

        // Create branches
        $branches = [
            ['name' => 'Sanchaung Cafe', 'address' => '890 University Avenue Road, Yangon'],
            ['name' => 'Bahan Cafe', 'address' => '234 Pyay Road, Yangon'],
            ['name' => 'Airport Kiosk', 'address' => 'Mingaladon Airport, Yangon'],
        ];

        foreach ($branches as $branchData) {
            Branch::firstOrCreate(
                ['shop_owner_id' => $shopOwner->id, 'name' => $branchData['name']],
                [
                    'shop_owner_id' => $shopOwner->id,
                    'name' => $branchData['name'],
                    'address' => $branchData['address'],
                    'is_active' => true,
                ]
            );
        }

        echo "Created branches" . PHP_EOL;

        // Create units
        $units = [
            ['name' => 'piece', 'symbol' => 'pcs', 'type' => 'quantity'],
            ['name' => 'kilogram', 'symbol' => 'kg', 'type' => 'weight'],
            ['name' => 'gram', 'symbol' => 'g', 'type' => 'weight'],
            ['name' => 'cup', 'symbol' => 'cup', 'type' => 'quantity'],
            ['name' => 'slice', 'symbol' => 'slc', 'type' => 'quantity'],
            ['name' => 'box', 'symbol' => 'bx', 'type' => 'quantity'],
        ];

        foreach ($units as $unitData) {
            Unit::firstOrCreate(
                ['shop_owner_id' => $shopOwner->id, 'name' => $unitData['name']],
                [
                    'shop_owner_id' => $shopOwner->id,
                    'name' => $unitData['name'],
                    'symbol' => $unitData['symbol'],
                    'type' => $unitData['type'],
                    'is_system' => false,
                ]
            );
        }

        echo "Created units" . PHP_EOL;

        // Create categories
        $categories = [
            'Coffee', 'Tea', 'Cold Drinks', 'Pastries', 'Cakes',
            'Sandwiches', 'Combos', 'Coffee Beans', 'Tea Leaves', 'Merchandise',
            'ကော်ဖီ', 'ကိတ်'
        ];

        foreach ($categories as $categoryName) {
            Category::firstOrCreate(
                ['shop_owner_id' => $shopOwner->id, 'name' => $categoryName],
                [
                    'shop_owner_id' => $shopOwner->id,
                    'name' => $categoryName,
                    'slug' => strtolower(str_replace(' ', '-', $categoryName)),
                ]
            );
        }

        echo "Created categories" . PHP_EOL;

        // Create brands (most products have no brand, but we need a few)
        $brands = [
            'Moe Kyaw Roastery', 'Local Bakery', 'Premium Imports',
            'Myanmar Coffee Co', 'Artisan Blends', 'မြန်မာကော်ဖီ'
        ];

        foreach ($brands as $brandName) {
            Brand::firstOrCreate(
                ['shop_owner_id' => $shopOwner->id, 'name' => $brandName],
                [
                    'shop_owner_id' => $shopOwner->id,
                    'name' => $brandName,
                    'slug' => strtolower(str_replace(' ', '-', $brandName)),
                    'status' => 'active',
                ]
            );
        }

        echo "Created brands" . PHP_EOL;

        // Blueprint A: Cafe Drinks
        $blueprintA = Blueprint::firstOrCreate(
            ['shop_owner_id' => $shopOwner->id, 'name' => 'Cafe Drinks'],
            [
                'shop_owner_id' => $shopOwner->id,
                'name' => 'Cafe Drinks',
                'description' => 'Blueprint for cafe drinks with size and temperature variants',
                'preset_key' => 'cafe',
                'is_default' => true,
                'status' => 'active',
                'capabilities' => [
                    'variants' => true,
                    'bundles' => false,
                    'batch_expiry' => false,
                    'serial_numbers' => false,
                    'decimal_quantities' => false,
                ],
                'pricing_policy' => [
                    'allowed_modes' => ['fixed'],
                    'default_mode' => 'fixed',
                    'min_margin_percent' => null,
                    'cost_required' => false,
                    'block_below_margin' => false,
                    'price_per_variant' => true,
                    'pos_price_override' => false,
                ],
                'unit_policy' => [
                    'default_base_unit' => 'cup',
                    'allowed_units' => ['cup'],
                    'price_per_unit' => true,
                    'barcode_per_unit' => false,
                    'buy_sell_different_units' => false,
                ],
                'system_fields' => [
                    'brand_id' => ['hidden' => true, 'required' => false],
                    'cost_price' => ['hidden' => true, 'required' => false],
                    'min_stock' => ['hidden' => true, 'required' => false],
                    'description' => ['hidden' => false, 'required' => true],
                ],
                'version' => 1,
            ]
        );

        echo "Created blueprint A: Cafe Drinks" . PHP_EOL;

        // Create field definitions for Blueprint A
        $fieldDefinitionsA = [
            [
                'key' => 'allergens',
                'label' => 'Allergens',
                'type' => 'multi_select',
                'options' => [
                    ['value' => 'milk', 'label' => 'Milk', 'active' => true],
                    ['value' => 'soy', 'label' => 'Soy', 'active' => true],
                    ['value' => 'nuts', 'label' => 'Nuts', 'active' => true],
                    ['value' => 'gluten', 'label' => 'Gluten', 'active' => true],
                    ['value' => 'egg', 'label' => 'Egg', 'active' => true],
                ],
            ],
            [
                'key' => 'prep_time_minutes',
                'label' => 'Prep Time (Minutes)',
                'type' => 'number',
                'options' => [],
            ],
            [
                'key' => 'calories',
                'label' => 'Calories',
                'type' => 'number',
                'options' => [],
            ],
        ];

        foreach ($fieldDefinitionsA as $fieldDef) {
            $fieldDefinition = FieldDefinition::where('shop_owner_id', $shopOwner->id)
                ->where('key', $fieldDef['key'])
                ->first();

            if (!$fieldDefinition) {
                $fieldDefinition = FieldDefinition::create([
                    'shop_owner_id' => $shopOwner->id,
                    'key' => $fieldDef['key'],
                    'label' => $fieldDef['label'],
                    'type' => $fieldDef['type'],
                    'options' => $fieldDef['options'],
                ]);
            }

            BlueprintField::firstOrCreate(
                ['blueprint_id' => $blueprintA->id, 'field_definition_id' => $fieldDefinition->id],
                [
                    'blueprint_id' => $blueprintA->id,
                    'field_definition_id' => $fieldDefinition->id,
                    'section' => 'details',
                    'sort_order' => 1,
                    'required' => false,
                    'show_in_list' => false,
                    'show_in_pos' => false,
                    'show_on_label' => false,
                    'is_variant_axis' => false,
                    'is_filterable' => false,
                    'is_searchable' => false,
                    'hidden' => false,
                ]
            );
        }

        // Create variant axes for Blueprint A
        $variantAxesA = [
            [
                'key' => 'size',
                'label' => 'Size',
                'type' => 'select',
                'options' => [
                    ['value' => 'small', 'label' => 'Small', 'active' => true],
                    ['value' => 'medium', 'label' => 'Medium', 'active' => true],
                    ['value' => 'large', 'label' => 'Large', 'active' => true],
                ],
            ],
            [
                'key' => 'temperature',
                'label' => 'Temperature',
                'type' => 'select',
                'options' => [
                    ['value' => 'hot', 'label' => 'Hot', 'active' => true],
                    ['value' => 'iced', 'label' => 'Iced', 'active' => true],
                ],
            ],
        ];

        foreach ($variantAxesA as $axisDef) {
            $fieldDefinition = FieldDefinition::where('shop_owner_id', $shopOwner->id)
                ->where('key', $axisDef['key'])
                ->first();

            if (!$fieldDefinition) {
                $fieldDefinition = FieldDefinition::create([
                    'shop_owner_id' => $shopOwner->id,
                    'key' => $axisDef['key'],
                    'label' => $axisDef['label'],
                    'type' => $axisDef['type'],
                    'options' => $axisDef['options'],
                ]);
            }

            BlueprintField::firstOrCreate(
                ['blueprint_id' => $blueprintA->id, 'field_definition_id' => $fieldDefinition->id],
                [
                    'blueprint_id' => $blueprintA->id,
                    'field_definition_id' => $fieldDefinition->id,
                    'section' => 'variant_axes',
                    'sort_order' => 1,
                    'required' => $axisDef['key'] === 'size',
                    'show_in_list' => false,
                    'show_in_pos' => false,
                    'show_on_label' => false,
                    'is_variant_axis' => true,
                    'is_filterable' => false,
                    'is_searchable' => false,
                    'hidden' => false,
                ]
            );
        }

        // Blueprint B: Cafe Food and Combos
        $blueprintB = Blueprint::firstOrCreate(
            ['shop_owner_id' => $shopOwner->id, 'name' => 'Cafe Food and Combos'],
            [
                'shop_owner_id' => $shopOwner->id,
                'name' => 'Cafe Food and Combos',
                'description' => 'Blueprint for food items and combo bundles',
                'preset_key' => 'cafe',
                'is_default' => false,
                'status' => 'active',
                'capabilities' => [
                    'variants' => false,
                    'bundles' => true,
                    'batch_expiry' => false,
                    'serial_numbers' => false,
                    'decimal_quantities' => false,
                ],
                'pricing_policy' => [
                    'allowed_modes' => ['fixed'],
                    'default_mode' => 'fixed',
                    'min_margin_percent' => null,
                    'cost_required' => true,
                    'block_below_margin' => false,
                    'price_per_variant' => false,
                    'pos_price_override' => false,
                ],
                'unit_policy' => [
                    'default_base_unit' => 'piece',
                    'allowed_units' => ['piece', 'slice', 'box'],
                    'price_per_unit' => true,
                    'barcode_per_unit' => false,
                    'buy_sell_different_units' => false,
                ],
                'system_fields' => [
                    'brand_id' => ['hidden' => true, 'required' => false],
                    'description' => ['hidden' => false, 'required' => false],
                ],
                'version' => 1,
            ]
        );

        echo "Created blueprint B: Cafe Food and Combos" . PHP_EOL;

        // Create field definitions for Blueprint B
        $fieldDefinitionsB = [
            [
                'key' => 'allergens',
                'label' => 'Allergens',
                'type' => 'multi_select',
                'options' => [
                    ['value' => 'milk', 'label' => 'Milk', 'active' => true],
                    ['value' => 'soy', 'label' => 'Soy', 'active' => true],
                    ['value' => 'nuts', 'label' => 'Nuts', 'active' => true],
                    ['value' => 'gluten', 'label' => 'Gluten', 'active' => true],
                    ['value' => 'egg', 'label' => 'Egg', 'active' => true],
                ],
            ],
            [
                'key' => 'prep_time_minutes',
                'label' => 'Prep Time (Minutes)',
                'type' => 'number',
                'options' => [],
            ],
            [
                'key' => 'is_vegetarian',
                'label' => 'Vegetarian',
                'type' => 'boolean',
                'options' => [],
            ],
        ];

        foreach ($fieldDefinitionsB as $fieldDef) {
            $fieldDefinition = FieldDefinition::where('shop_owner_id', $shopOwner->id)
                ->where('key', $fieldDef['key'])
                ->first();

            if (!$fieldDefinition) {
                $fieldDefinition = FieldDefinition::create([
                    'shop_owner_id' => $shopOwner->id,
                    'key' => $fieldDef['key'],
                    'label' => $fieldDef['label'],
                    'type' => $fieldDef['type'],
                    'options' => $fieldDef['options'],
                ]);
            }

            BlueprintField::firstOrCreate(
                ['blueprint_id' => $blueprintB->id, 'field_definition_id' => $fieldDefinition->id],
                [
                    'blueprint_id' => $blueprintB->id,
                    'field_definition_id' => $fieldDefinition->id,
                    'section' => 'details',
                    'sort_order' => 1,
                    'required' => false,
                    'show_in_list' => false,
                    'show_in_pos' => false,
                    'show_on_label' => false,
                    'is_variant_axis' => false,
                    'is_filterable' => false,
                    'is_searchable' => false,
                    'hidden' => false,
                ]
            );
        }

        // Blueprint C: Beans and Retail
        $blueprintC = Blueprint::firstOrCreate(
            ['shop_owner_id' => $shopOwner->id, 'name' => 'Beans and Retail'],
            [
                'shop_owner_id' => $shopOwner->id,
                'name' => 'Beans and Retail',
                'description' => 'Blueprint for coffee beans, tea leaves, and retail items',
                'preset_key' => 'generic',
                'is_default' => false,
                'status' => 'active',
                'capabilities' => [
                    'variants' => false,
                    'bundles' => false,
                    'batch_expiry' => false,
                    'serial_numbers' => false,
                    'decimal_quantities' => true,
                ],
                'pricing_policy' => [
                    'allowed_modes' => ['fixed', 'wholesale'],
                    'default_mode' => 'fixed',
                    'min_margin_percent' => null,
                    'cost_required' => false,
                    'block_below_margin' => false,
                    'price_per_variant' => false,
                    'pos_price_override' => false,
                ],
                'unit_policy' => [
                    'default_base_unit' => 'kilogram',
                    'allowed_units' => ['kilogram', 'gram', 'piece', 'box'],
                    'price_per_unit' => true,
                    'barcode_per_unit' => false,
                    'buy_sell_different_units' => false,
                ],
                'system_fields' => [],
                'version' => 1,
            ]
        );

        echo "Created blueprint C: Beans and Retail" . PHP_EOL;

        // Create field definitions for Blueprint C
        $fieldDefinitionsC = [
            [
                'key' => 'roast_level',
                'label' => 'Roast Level',
                'type' => 'select',
                'options' => [
                    ['value' => 'light', 'label' => 'Light', 'active' => true],
                    ['value' => 'medium', 'label' => 'Medium', 'active' => true],
                    ['value' => 'dark', 'label' => 'Dark', 'active' => true],
                ],
            ],
            [
                'key' => 'origin',
                'label' => 'Origin',
                'type' => 'text',
                'options' => [],
            ],
        ];

        foreach ($fieldDefinitionsC as $fieldDef) {
            $fieldDefinition = FieldDefinition::where('shop_owner_id', $shopOwner->id)
                ->where('key', $fieldDef['key'])
                ->first();

            if (!$fieldDefinition) {
                $fieldDefinition = FieldDefinition::create([
                    'shop_owner_id' => $shopOwner->id,
                    'key' => $fieldDef['key'],
                    'label' => $fieldDef['label'],
                    'type' => $fieldDef['type'],
                    'options' => $fieldDef['options'],
                ]);
            }

            BlueprintField::firstOrCreate(
                ['blueprint_id' => $blueprintC->id, 'field_definition_id' => $fieldDefinition->id],
                [
                    'blueprint_id' => $blueprintC->id,
                    'field_definition_id' => $fieldDefinition->id,
                    'section' => 'details',
                    'sort_order' => 1,
                    'required' => false,
                    'show_in_list' => false,
                    'show_in_pos' => false,
                    'show_on_label' => false,
                    'is_variant_axis' => false,
                    'is_filterable' => false,
                    'is_searchable' => false,
                    'hidden' => false,
                ]
            );
        }

        echo "Created blueprint fields" . PHP_EOL;

        // Get references
        $cupUnit = Unit::where('shop_owner_id', $shopOwner->id)->where('name', 'cup')->first();
        $pcsUnit = Unit::where('shop_owner_id', $shopOwner->id)->where('name', 'piece')->first();
        $sliceUnit = Unit::where('shop_owner_id', $shopOwner->id)->where('name', 'slice')->first();
        $kgUnit = Unit::where('shop_owner_id', $shopOwner->id)->where('name', 'kilogram')->first();
        $gUnit = Unit::where('shop_owner_id', $shopOwner->id)->where('name', 'gram')->first();
        $boxUnit = Unit::where('shop_owner_id', $shopOwner->id)->where('name', 'box')->first();

        $coffeeCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Coffee')->first();
        $teaCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Tea')->first();
        $pastriesCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Pastries')->first();
        $cakesCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Cakes')->first();
        $combosCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Combos')->first();
        $beansCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Coffee Beans')->first();
        $merchCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Merchandise')->first();

        $brand1 = Brand::where('shop_owner_id', $shopOwner->id)->where('name', 'Moe Kyaw Roastery')->first();
        $brand2 = Brand::where('shop_owner_id', $shopOwner->id)->where('name', 'Local Bakery')->first();

        // Create drinks (Blueprint A) - variant products with no stock
        $drinks = [
            [
                'name' => 'Espresso',
                'description' => 'Rich and bold espresso shot',
                'custom_fields' => [
                    'allergens' => [],
                    'prep_time_minutes' => 3,
                    'calories' => 5,
                ],
            ],
            [
                'name' => 'Cappuccino',
                'description' => 'Classic cappuccino with steamed milk foam',
                'custom_fields' => [
                    'allergens' => ['milk'],
                    'prep_time_minutes' => 5,
                    'calories' => 120,
                ],
            ],
            [
                'name' => 'Latte',
                'description' => 'Smooth latte with espresso and steamed milk',
                'custom_fields' => [
                    'allergens' => ['milk'],
                    'prep_time_minutes' => 5,
                    'calories' => 150,
                ],
            ],
        ];

        foreach ($drinks as $drink) {
            $product = Product::firstOrCreate(
                [
                    'shop_owner_id' => $shopOwner->id,
                    'name' => $drink['name'],
                ],
                [
                    'shop_owner_id' => $shopOwner->id,
                    'category_id' => $coffeeCategory->id,
                    'name' => $drink['name'],
                    'description' => $drink['description'],
                    'base_unit_id' => null,
                    'pricing_mode' => 'fixed',
                    'blueprint_id' => $blueprintA->id,
                    'blueprint_version' => 1,
                    'product_type' => 'variant',
                    'custom_fields' => $drink['custom_fields'],
                    'status' => 'active',
                    'track_stock' => false, // No stock tracking for drinks
                ]
            );

            $baseProductUnit = ProductUnit::updateOrCreate(
                ['product_id' => $product->id, 'unit_id' => $cupUnit->id],
                [
                    'product_id' => $product->id,
                    'unit_id' => $cupUnit->id,
                    'conversion_factor' => 1,
                    'selling_price' => 2000,
                    'purchase_price' => null,
                ]
            );

            $product->update(['base_unit_id' => $baseProductUnit->id]);

            // Add barcode for Espresso (about 10% of products)
            if ($drink['name'] === 'Espresso') {
                $ean13 = $this->generateEAN13();
                \App\Models\ProductBarcode::create([
                    'product_id' => $product->id,
                    'barcode' => $ean13,
                    'is_primary' => true,
                ]);
            }

            // Create variants (size x temperature)
            $sizes = ['small', 'medium', 'large'];
            $temperatures = ['hot', 'iced'];

            foreach ($sizes as $size) {
                foreach ($temperatures as $temperature) {
                    $priceMultiplier = match($size) {
                        'small' => 1.0,
                        'medium' => 1.3,
                        'large' => 1.6,
                    };

                    ProductVariant::firstOrCreate(
                        [
                            'product_id' => $product->id,
                            'attributes' => json_encode(['size' => $size, 'temperature' => $temperature]),
                        ],
                        [
                            'product_id' => $product->id,
                            'sku' => strtoupper(substr($drink['name'], 0, 3)) . '-' . strtoupper($size) . '-' . strtoupper($temperature),
                            'attributes' => json_encode(['size' => $size, 'temperature' => $temperature]),
                            'selling_price' => 2000 * $priceMultiplier,
                            'purchase_price' => null,
                            'status' => 'active',
                        ]
                    );
                }
            }
        }

        echo "Created drinks with variants" . PHP_EOL;

        // Create food items (Blueprint B) with stock
        $foodItems = [
            [
                'name' => 'Croissant',
                'category' => $pastriesCategory,
                'unit' => $pcsUnit,
                'cost_price' => 500,
                'selling_price' => 1200,
                'min_stock' => 20,
                'opening_stock' => 0, // Zero stock
                'custom_fields' => [
                    'allergens' => ['gluten', 'milk'],
                    'prep_time_minutes' => 0,
                    'is_vegetarian' => true,
                ],
            ],
            [
                'name' => 'Chocolate Cake Slice',
                'category' => $cakesCategory,
                'unit' => $sliceUnit,
                'cost_price' => 800,
                'selling_price' => 2000,
                'min_stock' => 15,
                'opening_stock' => 10, // Below min_stock
                'custom_fields' => [
                    'allergens' => ['gluten', 'milk', 'egg'],
                    'prep_time_minutes' => 0,
                    'is_vegetarian' => true,
                ],
            ],
            [
                'name' => 'Sandwich',
                'category' => Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Sandwiches')->first(),
                'unit' => $pcsUnit,
                'cost_price' => 1500,
                'selling_price' => 3500,
                'min_stock' => 10,
                'opening_stock' => 8, // Below min_stock
                'custom_fields' => [
                    'allergens' => ['gluten', 'egg'],
                    'prep_time_minutes' => 5,
                    'is_vegetarian' => false,
                ],
            ],
        ];

        foreach ($foodItems as $item) {
            $product = Product::firstOrCreate(
                [
                    'shop_owner_id' => $shopOwner->id,
                    'name' => $item['name'],
                ],
                [
                    'shop_owner_id' => $shopOwner->id,
                    'category_id' => $item['category']->id,
                    'name' => $item['name'],
                    'base_unit_id' => null,
                    'pricing_mode' => 'fixed',
                    'cost_price' => $item['cost_price'],
                    'blueprint_id' => $blueprintB->id,
                    'blueprint_version' => 1,
                    'product_type' => 'neutral',
                    'custom_fields' => $item['custom_fields'],
                    'status' => $item['opening_stock'] === 0 ? 'inactive' : 'active',
                    'track_stock' => true,
                ]
            );

            $baseProductUnit = ProductUnit::updateOrCreate(
                ['product_id' => $product->id, 'unit_id' => $item['unit']->id],
                [
                    'product_id' => $product->id,
                    'unit_id' => $item['unit']->id,
                    'conversion_factor' => 1,
                    'selling_price' => $item['selling_price'],
                    'purchase_price' => $item['cost_price'],
                ]
            );

            $product->update(['base_unit_id' => $baseProductUnit->id]);

            if ($item['opening_stock'] > 0) {
                \App\Models\InventoryMovement::create([
                    'product_id' => $product->id,
                    'movement_type' => 'opening',
                    'quantity' => $item['opening_stock'],
                    'base_quantity' => $item['opening_stock'],
                    'unit_id' => $baseProductUnit->id,
                    'note' => 'Opening stock',
                ]);
            }
        }

        echo "Created food items" . PHP_EOL;

        // Create combos (bundles)
        $latteProduct = Product::where('shop_owner_id', $shopOwner->id)->where('name', 'Latte')->first();
        $croissantProduct = Product::where('shop_owner_id', $shopOwner->id)->where('name', 'Croissant')->first();
        $cakeProduct = Product::where('shop_owner_id', $shopOwner->id)->where('name', 'Chocolate Cake Slice')->first();

        if ($latteProduct && $croissantProduct) {
            // Get the latte product's base unit
            $latteBaseUnit = ProductUnit::where('product_id', $latteProduct->id)->first();

            $comboBundle = Product::firstOrCreate(
                [
                    'shop_owner_id' => $shopOwner->id,
                    'name' => 'Coffee and Pastry Combo',
                ],
                [
                    'shop_owner_id' => $shopOwner->id,
                    'category_id' => $combosCategory->id,
                    'name' => 'Coffee and Pastry Combo',
                    'description' => 'Coffee drink with a pastry',
                    'base_unit_id' => null,
                    'pricing_mode' => 'fixed',
                    'cost_price' => 2000,
                    'blueprint_id' => $blueprintB->id,
                    'blueprint_version' => 1,
                    'product_type' => 'bundle',
                    'custom_fields' => ['prep_time_minutes' => 5],
                    'status' => 'active',
                    'track_stock' => false,
                ]
            );

            $baseProductUnit = ProductUnit::updateOrCreate(
                ['product_id' => $comboBundle->id, 'unit_id' => $pcsUnit->id],
                [
                    'product_id' => $comboBundle->id,
                    'unit_id' => $pcsUnit->id,
                    'conversion_factor' => 1,
                    'selling_price' => 3000,
                    'purchase_price' => 2000,
                ]
            );

            $comboBundle->update(['base_unit_id' => $baseProductUnit->id]);

            $productBundle = ProductBundle::updateOrCreate(
                ['product_id' => $comboBundle->id],
                [
                    'product_id' => $comboBundle->id,
                    'bundle_price' => 3000, // Below sum
                ]
            );

            // Add latte as component (using parent product, not variant, since bundle items don't support variants yet)
            if ($latteBaseUnit) {
                ProductBundleItem::updateOrCreate(
                    ['product_bundle_id' => $productBundle->id, 'component_product_id' => $latteProduct->id, 'unit_id' => $latteBaseUnit->id],
                    [
                        'product_bundle_id' => $productBundle->id,
                        'component_product_id' => $latteProduct->id,
                        'unit_id' => $latteBaseUnit->id,
                        'quantity' => 1,
                    ]
                );
            }

            // Add croissant as component
            if ($croissantProduct) {
                $croissantBaseUnit = ProductUnit::where('product_id', $croissantProduct->id)->first();
                if ($croissantBaseUnit) {
                    ProductBundleItem::updateOrCreate(
                        ['product_bundle_id' => $productBundle->id, 'component_product_id' => $croissantProduct->id, 'unit_id' => $croissantBaseUnit->id],
                        [
                            'product_bundle_id' => $productBundle->id,
                            'component_product_id' => $croissantProduct->id,
                            'unit_id' => $croissantBaseUnit->id,
                            'quantity' => 1,
                        ]
                    );
                }
            }

            echo "Created combo bundles" . PHP_EOL;
        }

        // Create retail items (Blueprint C)
        $retailItems = [
            [
                'name' => 'Coffee Beans 1kg',
                'category' => $beansCategory,
                'brand' => $brand1,
                'unit' => $kgUnit,
                'cost_price' => 15000,
                'selling_price' => 25000,
                'custom_fields' => [
                    'roast_level' => 'medium',
                    'origin' => 'Myanmar',
                ],
            ],
            [
                'name' => 'Coffee Mug',
                'category' => $merchCategory,
                'brand' => null,
                'unit' => $pcsUnit,
                'cost_price' => 3000,
                'selling_price' => 8000,
                'custom_fields' => [],
            ],
        ];

        foreach ($retailItems as $item) {
            $product = Product::firstOrCreate(
                [
                    'shop_owner_id' => $shopOwner->id,
                    'name' => $item['name'],
                ],
                [
                    'shop_owner_id' => $shopOwner->id,
                    'category_id' => $item['category']->id,
                    'brand_id' => $item['brand']?->id,
                    'name' => $item['name'],
                    'base_unit_id' => null,
                    'pricing_mode' => 'fixed',
                    'cost_price' => $item['cost_price'],
                    'blueprint_id' => $blueprintC->id,
                    'blueprint_version' => 1,
                    'product_type' => 'neutral',
                    'custom_fields' => $item['custom_fields'],
                    'status' => 'active',
                    'track_stock' => true,
                ]
            );

            $baseProductUnit = ProductUnit::updateOrCreate(
                ['product_id' => $product->id, 'unit_id' => $item['unit']->id],
                [
                    'product_id' => $product->id,
                    'unit_id' => $item['unit']->id,
                    'conversion_factor' => 1,
                    'selling_price' => $item['selling_price'],
                    'purchase_price' => $item['cost_price'],
                ]
            );

            $product->update(['base_unit_id' => $baseProductUnit->id]);

            \App\Models\InventoryMovement::create([
                'product_id' => $product->id,
                'movement_type' => 'opening',
                'quantity' => 50,
                'base_quantity' => 50,
                'unit_id' => $baseProductUnit->id,
                'note' => 'Opening stock',
            ]);
        }

        echo "Created retail items" . PHP_EOL;

        // Create customers
        $customers = [
            ['name' => 'Ko Nay Win', 'phone' => '09-111111115', 'notes' => 'Regular coffee lover'],
            ['name' => 'Ma Hla Hla', 'phone' => '09-222222226', 'notes' => ''],
            ['name' => 'ဦးခိုင်', 'phone' => '09-333333337', 'notes' => 'ကော်ဖီဝယ်ယူသူ'],
            ['name' => 'Thandar', 'phone' => '09-444444448', 'notes' => ''],
            ['name' => 'Min Aung', 'phone' => '09-555555559', 'notes' => 'VIP customer'],
            ['name' => 'Su Myat', 'phone' => '09-666666660', 'notes' => ''],
            ['name' => 'ဒေါ်နန်းနန်း', 'phone' => '09-777777771', 'notes' => 'ပုံမှန်ဝယ်ယူသူ'],
            ['name' => 'Zaw Lin', 'phone' => '09-888888882', 'notes' => ''],
        ];

        foreach ($customers as $customerData) {
            Customer::firstOrCreate(
                ['shop_owner_id' => $shopOwner->id, 'phone' => $customerData['phone']],
                [
                    'shop_owner_id' => $shopOwner->id,
                    'name' => $customerData['name'],
                    'phone' => $customerData['phone'],
                    'address' => null,
                    'status' => 'active',
                    'total_purchases' => 0,
                ]
            );
        }

        echo "Created customers" . PHP_EOL;
        echo "Cafe seeder completed successfully!" . PHP_EOL;
    }

    /**
     * Generate a valid 13-digit EAN-13 barcode with correct check digit
     */
    private function generateEAN13(): string
    {
        $digits = '';
        for ($i = 0; $i < 12; $i++) {
            $digits .= random_int(0, 9);
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int)$digits[$i];
            $weight = ($i % 2 === 0) ? 1 : 3;
            $sum += $digit * $weight;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        return $digits . $checkDigit;
    }
}

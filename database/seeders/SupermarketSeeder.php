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
use App\Models\ProductBarcode;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\ProductPriceRule;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\ShopOwner;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SupermarketSeeder extends Seeder
{
    public function run(): void
    {
        // Create user for this shop
        $user = User::firstOrCreate(
            ['email' => 'shweyaung@supermarket.com'],
            [
                'name' => 'Shwe Yaung Owner',
                'username' => 'shweyaung',
                'email' => 'shweyaung@supermarket.com',
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
                'shop_name' => 'Shwe Yaung Supermarket',
                'location' => 'Yangon',
                'address' => '123 Bogyoke Aung San Road, Yangon',
                'phone' => '09-123456789',
                'business_type' => 'Supermarket',
                'plan_id' => $freePlan ? $freePlan->id : null,
                'staff_count' => 0,
                'description' => 'Leading supermarket in Yangon with fresh produce and groceries',
            ]
        );

        echo "Created Shop Owner: {$shopOwner->shop_name} (ID: {$shopOwner->id})" . PHP_EOL;

        // Create branches
        $branches = [
            ['name' => 'Main Store', 'address' => '123 Bogyoke Aung San Road, Yangon'],
            ['name' => 'Express Store', 'address' => '456 Inya Road, Yangon'],
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
            ['name' => 'litre', 'symbol' => 'L', 'type' => 'volume'],
            ['name' => 'millilitre', 'symbol' => 'ml', 'type' => 'volume'],
            ['name' => 'pack', 'symbol' => 'pk', 'type' => 'quantity'],
            ['name' => 'carton', 'symbol' => 'ctn', 'type' => 'quantity'],
            ['name' => 'dozen', 'symbol' => 'dz', 'type' => 'quantity'],
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
            'Rice and Grains', 'Cooking Oil', 'Noodles', 'Beverages', 'Snacks',
            'Dairy', 'Fresh Produce', 'Meat and Fish', 'Frozen', 'Condiments',
            'Household', 'Personal Care', 'Baby', 'Bakery', 'ဆန်', 'ဆီ', 'ဟင်းသီးဟင်းရွက်'
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

        // Create brands
        $brands = [
            'Golden Harvest', 'Myanmar Rice Co', 'Fresh Foods Ltd', 'Ocean Bay',
            'Mountain Valley', 'Sunrise Foods', 'Green Earth', 'Pure Taste',
            'Tropical Delights', 'City Market', 'Royal Brand', 'Premium Choice',
            'Nature Best', 'Home Fresh', 'Daily Needs', 'Quality First',
            'မြန်မာအစားအသောက်', 'သဘာဝပစ္စည်း'
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

        // Create blueprint
        $blueprint = Blueprint::firstOrCreate(
            ['shop_owner_id' => $shopOwner->id, 'name' => 'Supermarket Grocery'],
            [
                'shop_owner_id' => $shopOwner->id,
                'name' => 'Supermarket Grocery',
                'description' => 'Blueprint for supermarket grocery items',
                'preset_key' => 'grocery',
                'is_default' => true,
                'status' => 'active',
                'capabilities' => [
                    'variants' => false,
                    'bundles' => true,
                    'batch_expiry' => true,
                    'serial_numbers' => false,
                    'decimal_quantities' => true,
                ],
                'pricing_policy' => [
                    'allowed_modes' => ['fixed', 'wholesale'],
                    'default_mode' => 'fixed',
                    'min_margin_percent' => 8,
                    'cost_required' => true,
                    'block_below_margin' => false,
                    'price_per_variant' => false,
                    'pos_price_override' => false,
                ],
                'unit_policy' => [
                    'default_base_unit' => 'piece',
                    'allowed_units' => ['piece', 'kilogram', 'gram', 'litre', 'millilitre', 'pack', 'carton', 'dozen'],
                    'price_per_unit' => true,
                    'barcode_per_unit' => false,
                    'buy_sell_different_units' => true,
                ],
                'system_fields' => [
                    'description' => ['hidden' => true, 'required' => false],
                    'min_stock' => ['hidden' => false, 'required' => true],
                    'brand_id' => ['hidden' => false, 'required' => false],
                ],
                'version' => 1,
            ]
        );

        echo "Created blueprint: {$blueprint->name}" . PHP_EOL;

        // Create field definitions for custom fields
        $fieldDefinitions = [
            [
                'key' => 'storage',
                'label' => 'Storage',
                'type' => 'select',
                'options' => [
                    ['value' => 'ambient', 'label' => 'Ambient', 'active' => true],
                    ['value' => 'chilled', 'label' => 'Chilled', 'active' => true],
                    ['value' => 'frozen', 'label' => 'Frozen', 'active' => true],
                ],
            ],
            [
                'key' => 'origin',
                'label' => 'Origin',
                'type' => 'text',
                'options' => [],
            ],
            [
                'key' => 'expiry_date',
                'label' => 'Expiry Date',
                'type' => 'date',
                'options' => [],
            ],
            [
                'key' => 'shelf_life_days',
                'label' => 'Shelf Life (Days)',
                'type' => 'number',
                'options' => [],
            ],
            [
                'key' => 'sold_loose',
                'label' => 'Sold Loose',
                'type' => 'checkbox',
                'options' => [],
            ],
        ];

        foreach ($fieldDefinitions as $fieldDef) {
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

            // Attach to blueprint
            BlueprintField::firstOrCreate(
                ['blueprint_id' => $blueprint->id, 'field_definition_id' => $fieldDefinition->id],
                [
                    'blueprint_id' => $blueprint->id,
                    'field_definition_id' => $fieldDefinition->id,
                    'section' => 'details',
                    'sort_order' => 1,
                    'required' => in_array($fieldDef['key'], ['storage', 'expiry_date']),
                    'show_in_list' => $fieldDef['key'] === 'expiry_date',
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

        // Get references for product creation
        $kgUnit = Unit::where('shop_owner_id', $shopOwner->id)->where('name', 'kilogram')->first();
        $pcsUnit = Unit::where('shop_owner_id', $shopOwner->id)->where('name', 'piece')->first();
        $lUnit = Unit::where('shop_owner_id', $shopOwner->id)->where('name', 'litre')->first();
        $riceCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Rice and Grains')->first();
        $beveragesCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Beverages')->first();
        $snacksCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Snacks')->first();
        $produceCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Fresh Produce')->first();
        $meatCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Meat and Fish')->first();
        $frozenCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Frozen')->first();
        $brand1 = Brand::where('shop_owner_id', $shopOwner->id)->where('name', 'Myanmar Rice Co')->first();
        $brand2 = Brand::where('shop_owner_id', $shopOwner->id)->where('name', 'Fresh Foods Ltd')->first();
        $brand3 = Brand::where('shop_owner_id', $shopOwner->id)->where('name', 'Ocean Bay')->first();

        // Create loose items (sold by kg or litre with decimal stock)
        $looseItems = [
            [
                'name' => 'Emata Rice',
                'category' => $riceCategory,
                'brand' => $brand1,
                'unit' => $kgUnit,
                'cost_price' => 3500,
                'selling_price' => 4000,
                'min_stock' => 100,
                'opening_stock' => 500.5,
                'custom_fields' => ['storage' => 'ambient', 'sold_loose' => true],
            ],
            [
                'name' => 'Onions',
                'category' => $produceCategory,
                'brand' => $brand2,
                'unit' => $kgUnit,
                'cost_price' => 1200,
                'selling_price' => 1500,
                'min_stock' => 50,
                'opening_stock' => 200.8,
                'custom_fields' => ['storage' => 'ambient', 'sold_loose' => true],
            ],
            [
                'name' => 'Tomatoes',
                'category' => $produceCategory,
                'brand' => $brand2,
                'unit' => $kgUnit,
                'cost_price' => 1800,
                'selling_price' => 2200,
                'min_stock' => 30,
                'opening_stock' => 0, // Zero stock
                'custom_fields' => ['storage' => 'ambient', 'sold_loose' => true],
            ],
            [
                'name' => 'Fresh Chicken',
                'category' => $meatCategory,
                'brand' => $brand2,
                'unit' => $kgUnit,
                'cost_price' => 8500,
                'selling_price' => 9500,
                'min_stock' => 20,
                'opening_stock' => 15.5, // Below min_stock
                'custom_fields' => ['storage' => 'chilled', 'sold_loose' => true],
            ],
            [
                'name' => 'Cooking Oil',
                'category' => Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Cooking Oil')->first(),
                'brand' => $brand3,
                'unit' => $lUnit,
                'cost_price' => 3500,
                'selling_price' => 4000,
                'min_stock' => 50,
                'opening_stock' => 100.2,
                'custom_fields' => ['storage' => 'ambient', 'sold_loose' => true],
            ],
        ];

        foreach ($looseItems as $item) {
            $product = Product::firstOrCreate(
                [
                    'shop_owner_id' => $shopOwner->id,
                    'name' => $item['name'],
                ],
                [
                    'shop_owner_id' => $shopOwner->id,
                    'category_id' => $item['category']?->id,
                    'brand_id' => $item['brand']?->id,
                    'name' => $item['name'],
                    'base_unit_id' => null, // Will be set after creating product unit
                    'pricing_mode' => 'fixed',
                    'cost_price' => $item['cost_price'],
                    'min_margin_percent' => 8,
                    'min_stock' => $item['min_stock'],
                    'blueprint_id' => $blueprint->id,
                    'blueprint_version' => 1,
                    'product_type' => 'neutral',
                    'custom_fields' => $item['custom_fields'],
                    'status' => 'active',
                    'track_stock' => true,
                ]
            );

            // Create base product unit
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

            // Update product with base_unit_id
            $product->update(['base_unit_id' => $baseProductUnit->id]);

            // Add scanned barcode for about 10% of products
            if (in_array($item['name'], ['Emata Rice', 'Fresh Chicken', 'Instant Noodles Chicken'])) {
                $existingBarcode = \App\Models\ProductBarcode::where('product_id', $product->id)->first();
                if (!$existingBarcode) {
                    $ean13 = $this->generateEAN13();
                    \App\Models\ProductBarcode::create([
                        'product_id' => $product->id,
                        'barcode' => $ean13,
                        'is_primary' => true,
                    ]);
                }
            }

            // Add opening stock via inventory movement
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

        echo "Created loose items" . PHP_EOL;

        // Create packed goods
        $packedGoods = [
            [
                'name' => 'Instant Noodles Chicken',
                'category' => Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Noodles')->first(),
                'brand' => Brand::where('shop_owner_id', $shopOwner->id)->where('name', 'Sunrise Foods')->first(),
                'unit' => $pcsUnit,
                'cost_price' => 300,
                'selling_price' => 450,
                'min_stock' => 100,
                'opening_stock' => 500,
                'custom_fields' => ['storage' => 'ambient'],
            ],
            [
                'name' => 'Biscuit Pack',
                'category' => $snacksCategory,
                'brand' => Brand::where('shop_owner_id', $shopOwner->id)->where('name', 'Tropical Delights')->first(),
                'unit' => $pcsUnit,
                'cost_price' => 500,
                'selling_price' => 700,
                'min_stock' => 50,
                'opening_stock' => 0, // Zero stock
                'custom_fields' => ['storage' => 'ambient'],
            ],
            [
                'name' => 'Carbonated Drink 500ml',
                'category' => $beveragesCategory,
                'brand' => Brand::where('shop_owner_id', $shopOwner->id)->where('name', 'Mountain Valley')->first(),
                'unit' => $pcsUnit,
                'cost_price' => 400,
                'selling_price' => 600,
                'min_stock' => 100,
                'opening_stock' => 30, // Below min_stock
                'custom_fields' => ['storage' => 'ambient'],
            ],
        ];

        foreach ($packedGoods as $item) {
            $product = Product::firstOrCreate(
                [
                    'shop_owner_id' => $shopOwner->id,
                    'name' => $item['name'],
                ],
                [
                    'shop_owner_id' => $shopOwner->id,
                    'category_id' => $item['category']?->id,
                    'brand_id' => $item['brand']?->id,
                    'name' => $item['name'],
                    'base_unit_id' => null,
                    'pricing_mode' => 'fixed',
                    'cost_price' => $item['cost_price'],
                    'min_margin_percent' => 8,
                    'min_stock' => $item['min_stock'],
                    'blueprint_id' => $blueprint->id,
                    'blueprint_version' => 1,
                    'product_type' => 'neutral',
                    'custom_fields' => $item['custom_fields'],
                    'status' => $item['opening_stock'] === 0 ? 'inactive' : 'active',
                    'track_stock' => true,
                ]
            );

            // Create base product unit
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

            // Add scanned barcode for Carbonated Drink (about 10% of products)
            if ($item['name'] === 'Carbonated Drink 500ml') {
                $existingBarcode = \App\Models\ProductBarcode::where('product_id', $product->id)->first();
                if (!$existingBarcode) {
                    $ean13 = $this->generateEAN13();
                    \App\Models\ProductBarcode::create([
                        'product_id' => $product->id,
                        'barcode' => $ean13,
                        'is_primary' => true,
                    ]);
                }
            }

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

        echo "Created packed goods" . PHP_EOL;

        // Create wholesale items with tiers
        $cartonUnit = Unit::where('shop_owner_id', $shopOwner->id)->where('name', 'carton')->first();
        $dozenUnit = Unit::where('shop_owner_id', $shopOwner->id)->where('name', 'dozen')->first();

        $wholesaleProduct = Product::firstOrCreate(
            [
                'shop_owner_id' => $shopOwner->id,
                'name' => 'Soft Drink Carton (24 cans)',
            ],
            [
                'shop_owner_id' => $shopOwner->id,
                'category_id' => $beveragesCategory->id,
                'brand_id' => $brand3->id,
                'name' => 'Soft Drink Carton (24 cans)',
                'base_unit_id' => null,
                'pricing_mode' => 'wholesale',
                'cost_price' => 8000,
                'min_margin_percent' => 8,
                'min_stock' => 20,
                'blueprint_id' => $blueprint->id,
                'blueprint_version' => 1,
                'product_type' => 'neutral',
                'custom_fields' => ['storage' => 'ambient'],
                'status' => 'active',
                'track_stock' => true,
            ]
        );

        // Base unit price
        $baseProductUnit = ProductUnit::updateOrCreate(
            ['product_id' => $wholesaleProduct->id, 'unit_id' => $pcsUnit->id],
            [
                'product_id' => $wholesaleProduct->id,
                'unit_id' => $pcsUnit->id,
                'conversion_factor' => 1,
                'selling_price' => 500,
                'purchase_price' => 333,
            ]
        );

        $wholesaleProduct->update(['base_unit_id' => $baseProductUnit->id]);

        // Add barcode for wholesale product (about 10% of products)
        $existingBarcode = \App\Models\ProductBarcode::where('product_id', $wholesaleProduct->id)->first();
        if (!$existingBarcode) {
            $ean13 = $this->generateEAN13();
            \App\Models\ProductBarcode::create([
                'product_id' => $wholesaleProduct->id,
                'barcode' => $ean13,
                'is_primary' => true,
            ]);
        }

        // Carton extra unit
        ProductUnit::updateOrCreate(
            ['product_id' => $wholesaleProduct->id, 'unit_id' => $cartonUnit->id],
            [
                'product_id' => $wholesaleProduct->id,
                'unit_id' => $cartonUnit->id,
                'conversion_factor' => 24,
                'selling_price' => 11000,
                'purchase_price' => 8000,
            ]
        );

        // Wholesale tiers
        ProductPriceRule::updateOrCreate(
            ['product_id' => $wholesaleProduct->id, 'unit_id' => $baseProductUnit->id, 'min_quantity' => 1],
            [
                'product_id' => $wholesaleProduct->id,
                'unit_id' => $baseProductUnit->id,
                'min_quantity' => 1,
                'max_quantity' => 9,
                'price' => 500,
            ]
        );

        ProductPriceRule::updateOrCreate(
            ['product_id' => $wholesaleProduct->id, 'unit_id' => $baseProductUnit->id, 'min_quantity' => 10],
            [
                'product_id' => $wholesaleProduct->id,
                'unit_id' => $baseProductUnit->id,
                'min_quantity' => 10,
                'max_quantity' => 49,
                'price' => 450,
            ]
        );

        ProductPriceRule::updateOrCreate(
            ['product_id' => $wholesaleProduct->id, 'unit_id' => $baseProductUnit->id, 'min_quantity' => 50],
            [
                'product_id' => $wholesaleProduct->id,
                'unit_id' => $baseProductUnit->id,
                'min_quantity' => 50,
                'max_quantity' => null,
                'price' => 400,
            ]
        );

        echo "Created wholesale items" . PHP_EOL;

        // Create bundles
        $riceProduct = Product::where('shop_owner_id', $shopOwner->id)->where('name', 'Emata Rice')->first();
        $noodleProduct = Product::where('shop_owner_id', $shopOwner->id)->where('name', 'Instant Noodles Chicken')->first();
        $drinkProduct = Product::where('shop_owner_id', $shopOwner->id)->where('name', 'Carbonated Drink 500ml')->first();

        if ($riceProduct && $noodleProduct && $drinkProduct) {
            $bundleProduct = Product::firstOrCreate(
                [
                    'shop_owner_id' => $shopOwner->id,
                    'name' => 'Breakfast Pack',
                ],
                [
                    'shop_owner_id' => $shopOwner->id,
                    'name' => 'Breakfast Pack',
                    'description' => 'Complete breakfast bundle with rice, noodles, and drink',
                    'base_unit_id' => null,
                    'pricing_mode' => 'fixed',
                    'cost_price' => 4000,
                    'blueprint_id' => $blueprint->id,
                    'blueprint_version' => 1,
                    'product_type' => 'bundle',
                    'custom_fields' => ['storage' => 'ambient'],
                    'status' => 'active',
                    'track_stock' => false,
                ]
            );

            // Create base product unit for bundle
            $baseProductUnit = ProductUnit::updateOrCreate(
                ['product_id' => $bundleProduct->id, 'unit_id' => $pcsUnit->id],
                [
                    'product_id' => $bundleProduct->id,
                    'unit_id' => $pcsUnit->id,
                    'conversion_factor' => 1,
                    'selling_price' => 5000,
                    'purchase_price' => 4000,
                ]
            );

            $bundleProduct->update(['base_unit_id' => $baseProductUnit->id]);

            // Add barcode for bundle (about 10% of products)
            $existingBarcode = \App\Models\ProductBarcode::where('product_id', $bundleProduct->id)->first();
            if (!$existingBarcode) {
                $ean13 = $this->generateEAN13();
                \App\Models\ProductBarcode::create([
                    'product_id' => $bundleProduct->id,
                    'barcode' => $ean13,
                    'is_primary' => true,
                ]);
            }

            // Create bundle
            $productBundle = ProductBundle::updateOrCreate(
                ['product_id' => $bundleProduct->id],
                [
                    'product_id' => $bundleProduct->id,
                    'bundle_price' => 5000, // Below sum of components
                ]
            );

            // Bundle items - need to use the actual product_bundle_id from the ProductBundle table
            $riceProductUnit = ProductUnit::where('product_id', $riceProduct->id)->where('unit_id', $kgUnit->id)->first();
            $noodleProductUnit = ProductUnit::where('product_id', $noodleProduct->id)->where('unit_id', $pcsUnit->id)->first();
            $drinkProductUnit = ProductUnit::where('product_id', $drinkProduct->id)->where('unit_id', $pcsUnit->id)->first();

            if ($riceProductUnit) {
                ProductBundleItem::updateOrCreate(
                    ['product_bundle_id' => $productBundle->id, 'component_product_id' => $riceProduct->id, 'unit_id' => $riceProductUnit->id],
                    [
                        'product_bundle_id' => $productBundle->id,
                        'component_product_id' => $riceProduct->id,
                        'unit_id' => $riceProductUnit->id,
                        'quantity' => 1,
                    ]
                );
            }

            if ($noodleProductUnit) {
                ProductBundleItem::updateOrCreate(
                    ['product_bundle_id' => $productBundle->id, 'component_product_id' => $noodleProduct->id, 'unit_id' => $noodleProductUnit->id],
                    [
                        'product_bundle_id' => $productBundle->id,
                        'component_product_id' => $noodleProduct->id,
                        'unit_id' => $noodleProductUnit->id,
                        'quantity' => 5,
                    ]
                );
            }

            if ($drinkProductUnit) {
                ProductBundleItem::updateOrCreate(
                    ['product_bundle_id' => $productBundle->id, 'component_product_id' => $drinkProduct->id, 'unit_id' => $drinkProductUnit->id],
                    [
                        'product_bundle_id' => $productBundle->id,
                        'component_product_id' => $drinkProduct->id,
                        'unit_id' => $drinkProductUnit->id,
                        'quantity' => 2,
                    ]
                );
            }

            echo "Created bundles" . PHP_EOL;
        }

        // Create customers
        $customers = [
            ['name' => 'Ma Ma', 'phone' => '09-111111111', 'notes' => 'Regular customer'],
            ['name' => 'Ko Ko', 'phone' => '09-222222222', 'notes' => 'Wholesale buyer'],
            ['name' => 'ဦးထွန်း', 'phone' => '09-333333333', 'notes' => 'ပုံမှန်ဝယ်ယူသူ'],
            ['name' => 'Daw Hla', 'phone' => '09-444444444', 'notes' => ''],
            ['name' => 'Thet Thet', 'phone' => '09-555555555', 'notes' => 'VIP customer'],
            ['name' => 'Zaw Zaw', 'phone' => '09-666666666', 'notes' => ''],
            ['name' => 'မမမမ', 'phone' => '09-777777777', 'notes' => 'အမြဲတမ်းဝယ်ယူသူ'],
            ['name' => 'Min Min', 'phone' => '09-888888888', 'notes' => ''],
            ['name' => 'Su Su', 'phone' => '09-999999999', 'notes' => 'New customer'],
            ['name' => 'Aung Aung', 'phone' => '09-000000000', 'notes' => 'Bulk orders'],
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
        echo "Supermarket seeder completed successfully!" . PHP_EOL;
    }

    /**
     * Generate a valid 13-digit EAN-13 barcode with correct check digit
     */
    private function generateEAN13(): string
    {
        // Generate 12 random digits
        $digits = '';
        for ($i = 0; $i < 12; $i++) {
            $digits .= random_int(0, 9);
        }

        // Calculate check digit
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int)$digits[$i];
            // Odd positions (1, 3, 5, ...) are multiplied by 1
            // Even positions (2, 4, 6, ...) are multiplied by 3
            $weight = ($i % 2 === 0) ? 1 : 3;
            $sum += $digit * $weight;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        return $digits . $checkDigit;
    }
}

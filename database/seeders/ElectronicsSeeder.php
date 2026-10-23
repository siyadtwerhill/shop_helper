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
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\ShopOwner;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ElectronicsSeeder extends Seeder
{
    public function run(): void
    {
        // Create user for this shop
        $user = User::firstOrCreate(
            ['email' => 'digitalhub@electronics.com'],
            [
                'name' => 'Digital Hub Owner',
                'username' => 'digitalhub',
                'email' => 'digitalhub@electronics.com',
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
                'shop_name' => 'Digital Hub Electronics',
                'location' => 'Yangon',
                'address' => '345 Kabar Aye Pagoda Road, Yangon',
                'phone' => '09-777777777',
                'business_type' => 'Electronics Retail',
                'plan_id' => $freePlan ? $freePlan->id : null,
                'staff_count' => 0,
                'description' => 'Premium electronics retailer with latest gadgets and accessories',
            ]
        );

        echo "Created Shop Owner: {$shopOwner->shop_name} (ID: {$shopOwner->id})" . PHP_EOL;

        // Create branches
        $branches = [
            ['name' => 'Downtown', 'address' => '345 Kabar Aye Pagoda Road, Yangon'],
            ['name' => 'Hledan', 'address' => '567 Hledan Junction, Yangon'],
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

        // Create units (only pcs for electronics)
        Unit::firstOrCreate(
            ['shop_owner_id' => $shopOwner->id, 'name' => 'piece'],
            [
                'shop_owner_id' => $shopOwner->id,
                'name' => 'piece',
                'symbol' => 'pcs',
                'type' => 'quantity',
                'is_system' => false,
            ]
        );

        echo "Created units" . PHP_EOL;

        // Create categories
        $categories = [
            'Smartphones', 'Laptops', 'Tablets', 'Audio', 'Chargers and Cables',
            'Cases and Protectors', 'Small Appliances', 'Networking', 'Smart Watches',
            'မိုဘိုင်းဖုန်း', 'လက်တော့ပ်'
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
            'TechNova', 'ElectroMax', 'SmartWave', 'DigitalDreams',
            'FutureTech', 'PowerHouse', 'SoundMaster', 'VisionPro',
            'ConnectX', 'MobileEdge', 'GadgetLab', 'Innovatech',
            'PrimeGear', 'DevicePlus', 'မြန်မာအီလက်ထရွန်းနစ်'
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

        // Blueprint A: Mobile and Gadgets
        $blueprintA = Blueprint::firstOrCreate(
            ['shop_owner_id' => $shopOwner->id, 'name' => 'Mobile and Gadgets'],
            [
                'shop_owner_id' => $shopOwner->id,
                'name' => 'Mobile and Gadgets',
                'description' => 'Blueprint for mobile devices and gadgets with variants and serial numbers',
                'preset_key' => 'electronics',
                'is_default' => true,
                'status' => 'active',
                'capabilities' => [
                    'variants' => true,
                    'bundles' => false,
                    'batch_expiry' => false,
                    'serial_numbers' => true,
                    'decimal_quantities' => false,
                ],
                'pricing_policy' => [
                    'allowed_modes' => ['fixed', 'negotiable', 'price_range'],
                    'default_mode' => 'fixed',
                    'min_margin_percent' => 5,
                    'cost_required' => true,
                    'block_below_margin' => false,
                    'price_per_variant' => true,
                    'pos_price_override' => true,
                ],
                'unit_policy' => [
                    'default_base_unit' => 'piece',
                    'allowed_units' => ['piece'],
                    'price_per_unit' => true,
                    'barcode_per_unit' => false,
                    'buy_sell_different_units' => false,
                ],
                'system_fields' => [
                    'brand_id' => ['hidden' => false, 'required' => true],
                    'description' => ['hidden' => false, 'required' => true],
                    'min_price' => ['hidden' => false, 'required' => false],
                ],
                'version' => 1,
            ]
        );

        echo "Created blueprint A: Mobile and Gadgets" . PHP_EOL;

        // Create field definitions for Blueprint A
        $fieldDefinitionsA = [
            [
                'key' => 'model',
                'label' => 'Model',
                'type' => 'text',
                'options' => [],
            ],
            [
                'key' => 'condition',
                'label' => 'Condition',
                'type' => 'select',
                'options' => [
                    ['value' => 'new', 'label' => 'New', 'active' => true],
                    ['value' => 'used', 'label' => 'Used', 'active' => true],
                    ['value' => 'refurbished', 'label' => 'Refurbished', 'active' => true],
                ],
            ],
            [
                'key' => 'warranty_months',
                'label' => 'Warranty (Months)',
                'type' => 'number',
                'options' => [],
            ],
            [
                'key' => 'connectivity',
                'label' => 'Connectivity',
                'type' => 'multi_select',
                'options' => [
                    ['value' => 'wifi', 'label' => 'WiFi', 'active' => true],
                    ['value' => 'bluetooth', 'label' => 'Bluetooth', 'active' => true],
                    ['value' => '4g', 'label' => '4G', 'active' => true],
                    ['value' => '5g', 'label' => '5G', 'active' => true],
                    ['value' => 'nfc', 'label' => 'NFC', 'active' => true],
                ],
            ],
            [
                'key' => 'screen_size',
                'label' => 'Screen Size',
                'type' => 'decimal',
                'options' => [],
            ],
            [
                'key' => 'serials',
                'label' => 'Serial Numbers',
                'type' => 'json',
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
                    'required' => in_array($fieldDef['key'], ['model', 'condition']),
                    'show_in_list' => false,
                    'show_in_pos' => false,
                    'show_on_label' => false,
                    'is_variant_axis' => false,
                    'is_filterable' => false,
                    'is_searchable' => $fieldDef['key'] === 'model',
                    'hidden' => $fieldDef['key'] === 'serials', // Serials is hidden for now
                ]
            );
        }

        // Create variant axes for Blueprint A
        $variantAxesA = [
            [
                'key' => 'storage',
                'label' => 'Storage',
                'type' => 'select',
                'options' => [
                    ['value' => '64gb', 'label' => '64GB', 'active' => true],
                    ['value' => '128gb', 'label' => '128GB', 'active' => true],
                    ['value' => '256gb', 'label' => '256GB', 'active' => true],
                    ['value' => '512gb', 'label' => '512GB', 'active' => true],
                ],
            ],
            [
                'key' => 'color',
                'label' => 'Color',
                'type' => 'select',
                'options' => [
                    ['value' => 'black', 'label' => 'Black', 'active' => true],
                    ['value' => 'white', 'label' => 'White', 'active' => true],
                    ['value' => 'blue', 'label' => 'Blue', 'active' => true],
                    ['value' => 'red', 'label' => 'Red', 'active' => true],
                    ['value' => 'silver', 'label' => 'Silver', 'active' => true],
                    ['value' => 'gold', 'label' => 'Gold', 'active' => true],
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
                    'required' => true,
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

        // Blueprint B: Accessories and Appliances
        $blueprintB = Blueprint::firstOrCreate(
            ['shop_owner_id' => $shopOwner->id, 'name' => 'Accessories and Appliances'],
            [
                'shop_owner_id' => $shopOwner->id,
                'name' => 'Accessories and Appliances',
                'description' => 'Blueprint for accessories and appliances',
                'preset_key' => 'generic',
                'is_default' => false,
                'status' => 'active',
                'capabilities' => [
                    'variants' => false,
                    'bundles' => false,
                    'batch_expiry' => false,
                    'serial_numbers' => false,
                    'decimal_quantities' => false,
                ],
                'pricing_policy' => [
                    'allowed_modes' => ['fixed', 'negotiable'],
                    'default_mode' => 'fixed',
                    'min_margin_percent' => null,
                    'cost_required' => false,
                    'block_below_margin' => false,
                    'price_per_variant' => false,
                    'pos_price_override' => false,
                ],
                'unit_policy' => [
                    'default_base_unit' => 'piece',
                    'allowed_units' => ['piece'],
                    'price_per_unit' => true,
                    'barcode_per_unit' => false,
                    'buy_sell_different_units' => false,
                ],
                'system_fields' => [],
                'version' => 1,
            ]
        );

        echo "Created blueprint B: Accessories and Appliances" . PHP_EOL;

        // Create field definitions for Blueprint B
        $fieldDefinitionsB = [
            [
                'key' => 'model',
                'label' => 'Model',
                'type' => 'text',
                'options' => [],
            ],
            [
                'key' => 'warranty_months',
                'label' => 'Warranty (Months)',
                'type' => 'number',
                'options' => [],
            ],
            [
                'key' => 'power_watts',
                'label' => 'Power (Watts)',
                'type' => 'number',
                'options' => [],
            ],
            [
                'key' => 'compatible_with',
                'label' => 'Compatible With',
                'type' => 'textarea',
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

        echo "Created blueprint fields" . PHP_EOL;

        // Get references
        $pcsUnit = Unit::where('shop_owner_id', $shopOwner->id)->where('name', 'piece')->first();

        $smartphoneCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Smartphones')->first();
        $laptopCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Laptops')->first();
        $audioCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Audio')->first();
        $chargersCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Chargers and Cables')->first();
        $appliancesCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Small Appliances')->first();

        $brand1 = Brand::where('shop_owner_id', $shopOwner->id)->where('name', 'TechNova')->first();
        $brand2 = Brand::where('shop_owner_id', $shopOwner->id)->where('name', 'ElectroMax')->first();
        $brand3 = Brand::where('shop_owner_id', $shopOwner->id)->where('name', 'SmartWave')->first();

        // Create smartphone variant product
        $phoneProduct = Product::firstOrCreate(
            [
                'shop_owner_id' => $shopOwner->id,
                'name' => 'Smartphone Pro',
            ],
            [
                'shop_owner_id' => $shopOwner->id,
                'category_id' => $smartphoneCategory->id,
                'brand_id' => $brand1->id,
                'name' => 'Smartphone Pro',
                'description' => 'High-performance smartphone with advanced features',
                'base_unit_id' => null,
                'pricing_mode' => 'fixed',
                'cost_price' => 500000,
                'min_margin_percent' => 5,
                'blueprint_id' => $blueprintA->id,
                'blueprint_version' => 1,
                'product_type' => 'variant',
                'custom_fields' => [
                    'model' => 'TN-PRO-2026',
                    'condition' => 'new',
                    'warranty_months' => 12,
                    'connectivity' => ['wifi', 'bluetooth', '4g', '5g', 'nfc'],
                    'screen_size' => 6.5,
                ],
                'status' => 'active',
                'track_stock' => true,
            ]
        );

        $baseProductUnit = ProductUnit::updateOrCreate(
            ['product_id' => $phoneProduct->id, 'unit_id' => $pcsUnit->id],
            [
                'product_id' => $phoneProduct->id,
                'unit_id' => $pcsUnit->id,
                'conversion_factor' => 1,
                'selling_price' => 600000,
                'purchase_price' => 500000,
            ]
        );

        $phoneProduct->update(['base_unit_id' => $baseProductUnit->id]);

        // Add barcode for the smartphone (about 10% of products)
        $ean13 = $this->generateEAN13();
        \App\Models\ProductBarcode::create([
            'product_id' => $phoneProduct->id,
            'barcode' => $ean13,
            'is_primary' => true,
        ]);

        // Create variants (storage x color)
        $storages = ['64gb', '128gb', '256gb'];
        $colors = ['black', 'white', 'blue'];
        $variantCount = 0;

        foreach ($storages as $storage) {
            foreach ($colors as $color) {
                $variantCount++;
                $storageMultiplier = match($storage) {
                    '64gb' => 1.0,
                    '128gb' => 1.2,
                    '256gb' => 1.4,
                };

                $variant = ProductVariant::firstOrCreate(
                    [
                        'product_id' => $phoneProduct->id,
                        'attributes' => json_encode(['storage' => $storage, 'color' => $color]),
                    ],
                    [
                        'product_id' => $phoneProduct->id,
                        'sku' => 'PHONE-' . strtoupper($storage) . '-' . strtoupper($color),
                        'attributes' => json_encode(['storage' => $storage, 'color' => $color]),
                        'selling_price' => 600000 * $storageMultiplier,
                        'purchase_price' => 500000 * $storageMultiplier,
                        'status' => 'active',
                    ]
                );

                // Add stock (serials stored for future feature)
                $stock = 5;
                $serials = [];
                for ($i = 1; $i <= $stock; $i++) {
                    $serials[] = 'IMEI' . str_pad(rand(0, 999999999999999), 15, '0', STR_PAD_LEFT);
                }

                \App\Models\InventoryMovement::create([
                    'product_id' => $phoneProduct->id,
                    'variant_id' => $variant->id,
                    'movement_type' => 'opening',
                    'quantity' => $stock,
                    'base_quantity' => $stock,
                    'unit_id' => $baseProductUnit->id,
                    'note' => 'Opening stock with serials: ' . implode(', ', $serials),
                ]);
            }
        }

        echo "Created smartphone product with {$variantCount} variants" . PHP_EOL;

        // Create laptop variant product with price_range
        $laptopProduct = Product::firstOrCreate(
            [
                'shop_owner_id' => $shopOwner->id,
                'name' => 'Laptop Ultra',
            ],
            [
                'shop_owner_id' => $shopOwner->id,
                'category_id' => $laptopCategory->id,
                'brand_id' => $brand2->id,
                'name' => 'Laptop Ultra',
                'description' => 'Powerful laptop for professionals',
                'base_unit_id' => null,
                'pricing_mode' => 'price_range',
                'cost_price' => 1500000,
                'min_margin_percent' => 5,
                'min_price' => 1600000,
                'blueprint_id' => $blueprintA->id,
                'blueprint_version' => 1,
                'product_type' => 'variant',
                'custom_fields' => [
                    'model' => 'EM-ULTRA-2026',
                    'condition' => 'new',
                    'warranty_months' => 24,
                    'connectivity' => ['wifi', 'bluetooth'],
                    'screen_size' => 15.6,
                ],
                'status' => 'active',
                'track_stock' => true,
            ]
        );

        $baseProductUnit = ProductUnit::updateOrCreate(
            ['product_id' => $laptopProduct->id, 'unit_id' => $pcsUnit->id],
            [
                'product_id' => $laptopProduct->id,
                'unit_id' => $pcsUnit->id,
                'conversion_factor' => 1,
                'selling_price' => 2000000,
                'purchase_price' => 1500000,
            ]
        );

        $laptopProduct->update(['base_unit_id' => $baseProductUnit->id]);

        // Create laptop variants
        $laptopStorages = ['128gb', '256gb', '512gb'];
        $laptopColors = ['black', 'silver'];

        foreach ($laptopStorages as $storage) {
            foreach ($laptopColors as $color) {
                $storageMultiplier = match($storage) {
                    '128gb' => 1.0,
                    '256gb' => 1.3,
                    '512gb' => 1.6,
                };

                $variant = ProductVariant::firstOrCreate(
                    [
                        'product_id' => $laptopProduct->id,
                        'attributes' => json_encode(['storage' => $storage, 'color' => $color]),
                    ],
                    [
                        'product_id' => $laptopProduct->id,
                        'sku' => 'LAPTOP-' . strtoupper($storage) . '-' . strtoupper($color),
                        'attributes' => json_encode(['storage' => $storage, 'color' => $color]),
                        'selling_price' => 2000000 * $storageMultiplier,
                        'purchase_price' => 1500000 * $storageMultiplier,
                        'status' => 'active',
                    ]
                );

                $stock = 3;
                $serials = [];
                for ($i = 1; $i <= $stock; $i++) {
                    $serials[] = 'SN' . str_pad(rand(0, 999999999999999), 15, '0', STR_PAD_LEFT);
                }

                \App\Models\InventoryMovement::create([
                    'product_id' => $laptopProduct->id,
                    'variant_id' => $variant->id,
                    'movement_type' => 'opening',
                    'quantity' => $stock,
                    'base_quantity' => $stock,
                    'unit_id' => $baseProductUnit->id,
                    'note' => 'Opening stock with serials: ' . implode(', ', $serials),
                ]);
            }
        }

        echo "Created laptop product with variants" . PHP_EOL;

        // Create used/refurbished items
        $usedPhone = Product::firstOrCreate(
            [
                'shop_owner_id' => $shopOwner->id,
                'name' => 'Refurbished Smartphone',
            ],
            [
                'shop_owner_id' => $shopOwner->id,
                'category_id' => $smartphoneCategory->id,
                'brand_id' => $brand1->id,
                'name' => 'Refurbished Smartphone',
                'description' => 'Refurbished smartphone in good condition',
                'base_unit_id' => null,
                'pricing_mode' => 'fixed',
                'cost_price' => 200000,
                'min_margin_percent' => 5,
                'blueprint_id' => $blueprintA->id,
                'blueprint_version' => 1,
                'product_type' => 'variant',
                'custom_fields' => [
                    'model' => 'TN-OLD-2025',
                    'condition' => 'refurbished',
                    'warranty_months' => 6,
                    'connectivity' => ['wifi', 'bluetooth', '4g'],
                    'screen_size' => 6.0,
                ],
                'status' => 'active',
                'track_stock' => true,
            ]
        );

        $baseProductUnit = ProductUnit::updateOrCreate(
            ['product_id' => $usedPhone->id, 'unit_id' => $pcsUnit->id],
            [
                'product_id' => $usedPhone->id,
                'unit_id' => $pcsUnit->id,
                'conversion_factor' => 1,
                'selling_price' => 250000,
                'purchase_price' => 200000,
            ]
        );

        $usedPhone->update(['base_unit_id' => $baseProductUnit->id]);

        // Add one variant for used phone
        $usedVariant = ProductVariant::firstOrCreate(
            [
                'product_id' => $usedPhone->id,
                'attributes' => json_encode(['storage' => '64gb', 'color' => 'black']),
            ],
            [
                'product_id' => $usedPhone->id,
                'sku' => 'REFURB-64GB-BLK',
                'attributes' => json_encode(['storage' => '64gb', 'color' => 'black']),
                'selling_price' => 250000,
                'purchase_price' => 200000,
                'status' => 'active',
            ]
        );

        echo "Created refurbished items" . PHP_EOL;

        // Create accessories and appliances (Blueprint B)
        $accessories = [
            [
                'name' => 'USB-C Charger',
                'category' => $chargersCategory,
                'brand' => $brand3,
                'cost_price' => 8000,
                'selling_price' => 12000,
                'min_stock' => 30,
                'opening_stock' => 0, // Zero stock
                'custom_fields' => [
                    'model' => 'SW-CHG-001',
                    'warranty_months' => 6,
                    'power_watts' => 65,
                    'compatible_with' => 'USB-C devices',
                ],
            ],
            [
                'name' => 'Bluetooth Earbuds',
                'category' => $audioCategory,
                'brand' => $brand3,
                'cost_price' => 15000,
                'selling_price' => 25000,
                'min_stock' => 20,
                'opening_stock' => 15, // Below min_stock
                'custom_fields' => [
                    'model' => 'SW-AUD-001',
                    'warranty_months' => 12,
                    'power_watts' => null,
                    'compatible_with' => 'Bluetooth 5.0+ devices',
                ],
            ],
            [
                'name' => 'Electric Kettle',
                'category' => $appliancesCategory,
                'brand' => $brand2,
                'cost_price' => 25000,
                'selling_price' => 35000,
                'min_stock' => 10,
                'opening_stock' => 5, // Below min_stock
                'custom_fields' => [
                    'model' => 'EM-KTL-001',
                    'warranty_months' => 12,
                    'power_watts' => 1500,
                    'compatible_with' => null,
                ],
            ],
        ];

        foreach ($accessories as $item) {
            $product = Product::firstOrCreate(
                [
                    'shop_owner_id' => $shopOwner->id,
                    'name' => $item['name'],
                ],
                [
                    'shop_owner_id' => $shopOwner->id,
                    'category_id' => $item['category']->id,
                    'brand_id' => $item['brand']->id,
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
                ['product_id' => $product->id, 'unit_id' => $pcsUnit->id],
                [
                    'product_id' => $product->id,
                    'unit_id' => $pcsUnit->id,
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

        echo "Created accessories and appliances" . PHP_EOL;

        // Create customers
        $customers = [
            ['name' => 'Ko Pyae', 'phone' => '09-111111114', 'notes' => 'Regular tech buyer'],
            ['name' => 'Ma Thet', 'phone' => '09-222222225', 'notes' => ''],
            ['name' => 'ဦးအောင်', 'phone' => '09-333333336', 'notes' => 'လက်တော့ပ်ဝယ်ယူသူ'],
            ['name' => 'Nay Min', 'phone' => '09-444444447', 'notes' => ''],
            ['name' => 'Su Hlaing', 'phone' => '09-555555558', 'notes' => 'VIP customer'],
            ['name' => 'Thura', 'phone' => '09-666666669', 'notes' => ''],
            ['name' => 'ဒေါ်ခင်မြ', 'phone' => '09-777777770', 'notes' => 'အမြဲတမ်းဝယ်ယူသူ'],
            ['name' => 'Zin Myo', 'phone' => '09-888888881', 'notes' => ''],
            ['name' => 'Kaung Set', 'phone' => '09-999999992', 'notes' => 'New customer'],
            ['name' => 'Min Khant', 'phone' => '09-123456790', 'notes' => 'Bulk orders'],
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
        echo "Electronics seeder completed successfully!" . PHP_EOL;
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

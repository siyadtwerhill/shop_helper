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
use App\Models\ShopOwner;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PharmacySeeder extends Seeder
{
    public function run(): void
    {
        // Create user for this shop
        $user = User::firstOrCreate(
            ['email' => 'aryu@pharmacy.com'],
            [
                'name' => 'Ar Yu Owner',
                'username' => 'aryu',
                'email' => 'aryu@pharmacy.com',
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
                'shop_name' => 'Ar Yu Pharmacy',
                'location' => 'Yangon',
                'address' => '456 Pyay Road, Yangon',
                'phone' => '09-555555555',
                'business_type' => 'Pharmacy',
                'plan_id' => $freePlan ? $freePlan->id : null,
                'staff_count' => 0,
                'description' => 'Trusted pharmacy with quality medicines and healthcare products',
            ]
        );

        echo "Created Shop Owner: {$shopOwner->shop_name} (ID: {$shopOwner->id})" . PHP_EOL;

        // Create branches
        $branches = [
            ['name' => 'Main', 'address' => '456 Pyay Road, Yangon'],
            ['name' => '24-hour Outlet', 'address' => '789 Sule Pagoda Road, Yangon'],
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
            ['name' => 'tablet', 'symbol' => 'tab', 'type' => 'quantity'],
            ['name' => 'capsule', 'symbol' => 'cap', 'type' => 'quantity'],
            ['name' => 'strip', 'symbol' => 'str', 'type' => 'quantity'],
            ['name' => 'box', 'symbol' => 'bx', 'type' => 'quantity'],
            ['name' => 'bottle', 'symbol' => 'bt', 'type' => 'quantity'],
            ['name' => 'tube', 'symbol' => 'tb', 'type' => 'quantity'],
            ['name' => 'sachet', 'symbol' => 'sch', 'type' => 'quantity'],
            ['name' => 'piece', 'symbol' => 'pcs', 'type' => 'quantity'],
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
            'Pain and Fever', 'Cold and Cough', 'Stomach', 'Vitamins',
            'First Aid', 'Skin Care', 'Oral Care', 'Baby Care',
            'Medical Devices', 'Personal Care', 'ဆေးဝါး', 'ကျန်းမာရေး'
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

        // Create brands (manufacturers)
        $brands = [
            'MediCare Pharma', 'HealthPlus Laboratories', 'Life Sciences Inc',
            'Wellness Corp', 'CureAll Pharmaceuticals', 'Nature Remedies',
            'BioHealth Solutions', 'PharmaTech Industries', 'SafeMed Co',
            'Trust Pharmaceuticals', 'Myanmar Health Labs', 'Golden Cure',
            'Pure Medical', 'Advanced Pharma', 'မြန်မာဆေးဝါး'
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

        // Blueprint A: Pharmacy
        $blueprintA = Blueprint::firstOrCreate(
            ['shop_owner_id' => $shopOwner->id, 'name' => 'Pharmacy'],
            [
                'shop_owner_id' => $shopOwner->id,
                'name' => 'Pharmacy',
                'description' => 'Blueprint for pharmacy items with expiry tracking',
                'preset_key' => 'pharmacy',
                'is_default' => true,
                'status' => 'active',
                'capabilities' => [
                    'variants' => false,
                    'bundles' => false,
                    'batch_expiry' => true,
                    'serial_numbers' => false,
                    'decimal_quantities' => false,
                ],
                'pricing_policy' => [
                    'allowed_modes' => ['fixed'],
                    'default_mode' => 'fixed',
                    'min_margin_percent' => 10,
                    'cost_required' => true,
                    'block_below_margin' => true,
                    'price_per_variant' => false,
                    'pos_price_override' => false,
                ],
                'unit_policy' => [
                    'default_base_unit' => 'tablet',
                    'allowed_units' => ['tablet', 'capsule', 'strip', 'box', 'bottle', 'tube', 'sachet', 'piece'],
                    'price_per_unit' => true,
                    'barcode_per_unit' => false,
                    'buy_sell_different_units' => false,
                ],
                'system_fields' => [
                    'brand_id' => ['hidden' => false, 'required' => true],
                    'category_id' => ['hidden' => false, 'required' => true],
                    'image' => ['hidden' => true, 'required' => false],
                    'internal_notes' => ['hidden' => true, 'required' => false],
                ],
                'version' => 1,
            ]
        );

        echo "Created blueprint A: Pharmacy" . PHP_EOL;

        // Create field definitions for Blueprint A
        $fieldDefinitionsA = [
            [
                'key' => 'generic_name',
                'label' => 'Generic Name',
                'type' => 'text',
                'options' => [],
            ],
            [
                'key' => 'dosage_form',
                'label' => 'Dosage Form',
                'type' => 'select',
                'options' => [
                    ['value' => 'tablet', 'label' => 'Tablet', 'active' => true],
                    ['value' => 'capsule', 'label' => 'Capsule', 'active' => true],
                    ['value' => 'syrup', 'label' => 'Syrup', 'active' => true],
                    ['value' => 'cream', 'label' => 'Cream', 'active' => true],
                    ['value' => 'drops', 'label' => 'Drops', 'active' => true],
                    ['value' => 'sachet', 'label' => 'Sachet', 'active' => true],
                ],
            ],
            [
                'key' => 'strength',
                'label' => 'Strength',
                'type' => 'text',
                'options' => [],
            ],
            [
                'key' => 'requires_prescription',
                'label' => 'Requires Prescription',
                'type' => 'boolean',
                'options' => [],
            ],
            [
                'key' => 'storage_condition',
                'label' => 'Storage Condition',
                'type' => 'select',
                'options' => [
                    ['value' => 'room_temperature', 'label' => 'Room temperature', 'active' => true],
                    ['value' => 'cool', 'label' => 'Cool', 'active' => true],
                    ['value' => 'refrigerated', 'label' => 'Refrigerated', 'active' => true],
                ],
            ],
            [
                'key' => 'expiry_date',
                'label' => 'Expiry Date',
                'type' => 'date',
                'options' => [],
            ],
            [
                'key' => 'batch_no',
                'label' => 'Batch Number',
                'type' => 'text',
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
                    'required' => in_array($fieldDef['key'], ['generic_name', 'expiry_date', 'batch_no']),
                    'show_in_list' => false,
                    'show_in_pos' => false,
                    'show_on_label' => false,
                    'is_variant_axis' => false,
                    'is_filterable' => false,
                    'is_searchable' => $fieldDef['key'] === 'generic_name',
                    'hidden' => false,
                ]
            );
        }

        // Blueprint B: Health and Personal Care
        $blueprintB = Blueprint::firstOrCreate(
            ['shop_owner_id' => $shopOwner->id, 'name' => 'Health and Personal Care'],
            [
                'shop_owner_id' => $shopOwner->id,
                'name' => 'Health and Personal Care',
                'description' => 'Blueprint for health and personal care products',
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
                    'allowed_units' => ['piece', 'bottle', 'box'],
                    'price_per_unit' => true,
                    'barcode_per_unit' => false,
                    'buy_sell_different_units' => false,
                ],
                'system_fields' => [],
                'version' => 1,
            ]
        );

        echo "Created blueprint B: Health and Personal Care" . PHP_EOL;

        // Create field definitions for Blueprint B
        $fieldDefinitionsB = [
            [
                'key' => 'warranty_months',
                'label' => 'Warranty (Months)',
                'type' => 'number',
                'options' => [],
            ],
            [
                'key' => 'pack_size',
                'label' => 'Pack Size',
                'type' => 'text',
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
        $tabletUnit = Unit::where('shop_owner_id', $shopOwner->id)->where('name', 'tablet')->first();
        $stripUnit = Unit::where('shop_owner_id', $shopOwner->id)->where('name', 'strip')->first();
        $boxUnit = Unit::where('shop_owner_id', $shopOwner->id)->where('name', 'box')->first();
        $bottleUnit = Unit::where('shop_owner_id', $shopOwner->id)->where('name', 'bottle')->first();
        $pcsUnit = Unit::where('shop_owner_id', $shopOwner->id)->where('name', 'piece')->first();

        $painCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Pain and Fever')->first();
        $vitaminsCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Vitamins')->first();
        $firstAidCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'First Aid')->first();
        $personalCareCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Personal Care')->first();

        $brand1 = Brand::where('shop_owner_id', $shopOwner->id)->where('name', 'MediCare Pharma')->first();
        $brand2 = Brand::where('shop_owner_id', $shopOwner->id)->where('name', 'HealthPlus Laboratories')->first();
        $brand3 = Brand::where('shop_owner_id', $shopOwner->id)->where('name', 'Life Sciences Inc')->first();

        // Create pharmacy products (Blueprint A)
        $pharmacyProducts = [
            [
                'name' => 'Paracetamol 500mg',
                'category' => $painCategory,
                'brand' => $brand1,
                'unit' => $tabletUnit,
                'cost_price' => 100,
                'selling_price' => 150,
                'min_stock' => 100,
                'opening_stock' => 500,
                'custom_fields' => [
                    'generic_name' => 'Paracetamol',
                    'dosage_form' => 'tablet',
                    'strength' => '500mg',
                    'requires_prescription' => false,
                    'storage_condition' => 'room_temperature',
                    'expiry_date' => '2027-12-31',
                    'batch_no' => 'PCT-2026-001',
                ],
            ],
            [
                'name' => 'Vitamin C 500mg',
                'category' => $vitaminsCategory,
                'brand' => $brand2,
                'unit' => $tabletUnit,
                'cost_price' => 200,
                'selling_price' => 300,
                'min_stock' => 50,
                'opening_stock' => 0, // Zero stock
                'custom_fields' => [
                    'generic_name' => 'Ascorbic Acid',
                    'dosage_form' => 'tablet',
                    'strength' => '500mg',
                    'requires_prescription' => false,
                    'storage_condition' => 'room_temperature',
                    'expiry_date' => '2027-06-30',
                    'batch_no' => 'VTC-2026-001',
                ],
            ],
            [
                'name' => 'Bandage Pack',
                'category' => $firstAidCategory,
                'brand' => $brand3,
                'unit' => $pcsUnit,
                'cost_price' => 500,
                'selling_price' => 800,
                'min_stock' => 30,
                'opening_stock' => 20, // Below min_stock
                'custom_fields' => [
                    'generic_name' => 'Sterile Bandage',
                    'dosage_form' => null,
                    'strength' => null,
                    'requires_prescription' => false,
                    'storage_condition' => 'room_temperature',
                    'expiry_date' => '2028-01-01',
                    'batch_no' => 'BND-2026-001',
                ],
            ],
            [
                'name' => 'Antiseptic Solution',
                'category' => $firstAidCategory,
                'brand' => $brand1,
                'unit' => $bottleUnit,
                'cost_price' => 1500,
                'selling_price' => 2200,
                'min_stock' => 20,
                'opening_stock' => 15, // Below min_stock
                'custom_fields' => [
                    'generic_name' => 'Chlorhexidine',
                    'dosage_form' => null,
                    'strength' => '2%',
                    'requires_prescription' => false,
                    'storage_condition' => 'room_temperature',
                    'expiry_date' => '2026-11-30', // Expiring soon
                    'batch_no' => 'ANT-2026-001',
                ],
            ],
            [
                'name' => 'Calcium Tablets',
                'category' => $vitaminsCategory,
                'brand' => $brand2,
                'unit' => $tabletUnit,
                'cost_price' => 300,
                'selling_price' => 350, // Below margin (should be blocked)
                'min_stock' => 50,
                'opening_stock' => 100,
                'custom_fields' => [
                    'generic_name' => 'Calcium Carbonate',
                    'dosage_form' => 'tablet',
                    'strength' => '600mg',
                    'requires_prescription' => false,
                    'storage_condition' => 'room_temperature',
                    'expiry_date' => '2027-09-30',
                    'batch_no' => 'CAL-2026-001',
                ],
            ],
        ];

        foreach ($pharmacyProducts as $item) {
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
                    'min_margin_percent' => 10,
                    'blueprint_id' => $blueprintA->id,
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

            // Add barcode for Paracetamol (about 10% of products)
            if ($item['name'] === 'Paracetamol 500mg') {
                $ean13 = $this->generateEAN13();
                \App\Models\ProductBarcode::create([
                    'product_id' => $product->id,
                    'barcode' => $ean13,
                    'is_primary' => true,
                ]);
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

            // Add extra units (strip = 10 tablets, box = 10 strips)
            if ($item['unit']->name === 'tablet') {
                ProductUnit::updateOrCreate(
                    ['product_id' => $product->id, 'unit_id' => $stripUnit->id],
                    [
                        'product_id' => $product->id,
                        'unit_id' => $stripUnit->id,
                        'conversion_factor' => 10,
                        'selling_price' => $item['selling_price'] * 10,
                        'purchase_price' => $item['cost_price'] * 10,
                    ]
                );

                ProductUnit::updateOrCreate(
                    ['product_id' => $product->id, 'unit_id' => $boxUnit->id],
                    [
                        'product_id' => $product->id,
                        'unit_id' => $boxUnit->id,
                        'conversion_factor' => 100,
                        'selling_price' => $item['selling_price'] * 100,
                        'purchase_price' => $item['cost_price'] * 100,
                    ]
                );
            }
        }

        echo "Created pharmacy products" . PHP_EOL;

        // Create health and personal care products (Blueprint B)
        $healthProducts = [
            [
                'name' => 'Digital Thermometer',
                'category' => Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Medical Devices')->first(),
                'brand' => $brand3,
                'unit' => $pcsUnit,
                'cost_price' => 5000,
                'selling_price' => 8000,
                'min_stock' => 10,
                'opening_stock' => 20,
                'custom_fields' => [
                    'warranty_months' => 12,
                    'pack_size' => '1 piece',
                ],
            ],
            [
                'name' => 'Face Masks (50 pack)',
                'category' => $personalCareCategory,
                'brand' => $brand1,
                'unit' => $boxUnit,
                'cost_price' => 2000,
                'selling_price' => 3500,
                'min_stock' => 20,
                'opening_stock' => 0, // Zero stock
                'custom_fields' => [
                    'warranty_months' => null,
                    'pack_size' => '50 pieces',
                ],
            ],
            [
                'name' => 'Hand Sanitizer 500ml',
                'category' => $personalCareCategory,
                'brand' => $brand2,
                'unit' => $bottleUnit,
                'cost_price' => 1500,
                'selling_price' => 2500,
                'min_stock' => 30,
                'opening_stock' => 10, // Below min_stock
                'custom_fields' => [
                    'warranty_months' => null,
                    'pack_size' => '500ml',
                ],
            ],
        ];

        foreach ($healthProducts as $item) {
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

        echo "Created health and personal care products" . PHP_EOL;

        // Create customers
        $customers = [
            ['name' => 'Dr. Than', 'phone' => '09-111111113', 'notes' => 'Regular customer'],
            ['name' => 'Daw Myint', 'phone' => '09-222222224', 'notes' => ''],
            ['name' => 'ဆရာဝန်မြင့်', 'phone' => '09-333333335', 'notes' => 'ဆရာဝန်'],
            ['name' => 'Ko Nay', 'phone' => '09-444444446', 'notes' => ''],
            ['name' => 'Ma Su', 'phone' => '09-555555557', 'notes' => 'VIP customer'],
            ['name' => 'U Win', 'phone' => '09-666666668', 'notes' => ''],
            ['name' => 'ဒေါ်ခင်ခင်', 'phone' => '09-777777779', 'notes' => 'ပုံမှန်ဝယ်ယူသူ'],
            ['name' => 'Zaw Lwin', 'phone' => '09-888888880', 'notes' => ''],
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
        echo "Pharmacy seeder completed successfully!" . PHP_EOL;
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

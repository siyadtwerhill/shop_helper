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

class ClothingSeeder extends Seeder
{
    public function run(): void
    {
        // Create user for this shop
        $user = User::firstOrCreate(
            ['email' => 'thazin@fashion.com'],
            [
                'name' => 'Thazin Owner',
                'username' => 'thazin',
                'email' => 'thazin@fashion.com',
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
                'shop_name' => 'Thazin Fashion House',
                'location' => 'Mandalay',
                'address' => '78 26th Street, Mandalay',
                'phone' => '09-987654321',
                'business_type' => 'Fashion Retail',
                'plan_id' => $freePlan ? $freePlan->id : null,
                'staff_count' => 0,
                'description' => 'Premium fashion retailer in Mandalay',
            ]
        );

        echo "Created Shop Owner: {$shopOwner->shop_name} (ID: {$shopOwner->id})" . PHP_EOL;

        // Create branches
        $branches = [
            ['name' => 'Zay Cho Street', 'address' => '78 26th Street, Mandalay'],
            ['name' => 'City Mall', 'address' => 'Shopping Mall, Mandalay'],
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

        // Create units (only pcs for fashion)
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
            "Men's Tops", "Women's Tops", "Dresses", "Longyi", "Htamein",
            "Eingyi", "Trousers", "Shoes", "Bags", "Accessories", "Kids", "Uniforms",
            'အမျိုးသားအဝတ်အစား', 'အမျိုးသမီးအဝတ်အစား'
        ];

        foreach ($categories as $categoryName) {
            Category::firstOrCreate(
                ['shop_owner_id' => $shopOwner->id, 'name' => $categoryName],
                [
                    'shop_owner_id' => $shopOwner->id,
                    'name' => $categoryName,
                    'slug' => strtolower(str_replace([' ', "'"], '-', $categoryName)),
                ]
            );
        }

        echo "Created categories" . PHP_EOL;

        // Create brands
        $brands = [
            'Royal Fashion', 'Elegance Wear', 'Urban Style', 'Classic Lines',
            'Modern Trends', 'Premium Collection', 'Signature Style', 'Fashion House',
            'Elite Wear', 'Trend Setter', 'Designer Choice', 'Luxury Brand',
            'Casual Comfort', 'Smart Look', 'မြန်မာပုံစံ'
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

        // Create blueprint with variants
        $blueprint = Blueprint::firstOrCreate(
            ['shop_owner_id' => $shopOwner->id, 'name' => 'Fashion Retail'],
            [
                'shop_owner_id' => $shopOwner->id,
                'name' => 'Fashion Retail',
                'description' => 'Blueprint for fashion retail with variants',
                'preset_key' => 'fashion',
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
                    'allowed_modes' => ['fixed', 'negotiable'],
                    'default_mode' => 'fixed',
                    'min_margin_percent' => 30,
                    'cost_required' => true,
                    'block_below_margin' => false,
                    'price_per_variant' => true,
                    'pos_price_override' => false,
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
                    'category_id' => ['hidden' => false, 'required' => true],
                    'min_stock' => ['hidden' => true, 'required' => false],
                    'internal_notes' => ['hidden' => true, 'required' => false],
                ],
                'version' => 1,
            ]
        );

        echo "Created blueprint: {$blueprint->name}" . PHP_EOL;

        // Create field definitions for custom fields
        $fieldDefinitions = [
            [
                'key' => 'material',
                'label' => 'Material',
                'type' => 'text',
                'options' => [],
            ],
            [
                'key' => 'season',
                'label' => 'Season',
                'type' => 'select',
                'options' => [
                    ['value' => 'spring', 'label' => 'Spring', 'active' => true],
                    ['value' => 'summer', 'label' => 'Summer', 'active' => true],
                    ['value' => 'autumn', 'label' => 'Autumn', 'active' => true],
                    ['value' => 'winter', 'label' => 'Winter', 'active' => true],
                ],
            ],
            [
                'key' => 'gender',
                'label' => 'Gender',
                'type' => 'select',
                'options' => [
                    ['value' => 'men', 'label' => 'Men', 'active' => true],
                    ['value' => 'women', 'label' => 'Women', 'active' => true],
                    ['value' => 'unisex', 'label' => 'Unisex', 'active' => true],
                    ['value' => 'kids', 'label' => 'Kids', 'active' => true],
                ],
            ],
            [
                'key' => 'care_notes',
                'label' => 'Care Notes',
                'type' => 'textarea',
                'options' => [],
            ],
        ];

        foreach ($fieldDefinitions as $fieldDef) {
            $fieldDefinition = FieldDefinition::firstOrCreate(
                ['shop_owner_id' => $shopOwner->id, 'key' => $fieldDef['key']],
                [
                    'shop_owner_id' => $shopOwner->id,
                    'key' => $fieldDef['key'],
                    'label' => $fieldDef['label'],
                    'type' => $fieldDef['type'],
                    'options' => $fieldDef['options'],
                ]
            );

            BlueprintField::firstOrCreate(
                ['blueprint_id' => $blueprint->id, 'field_definition_id' => $fieldDefinition->id],
                [
                    'blueprint_id' => $blueprint->id,
                    'field_definition_id' => $fieldDefinition->id,
                    'section' => 'details',
                    'sort_order' => 1,
                    'required' => $fieldDef['key'] === 'gender',
                    'show_in_list' => false,
                    'show_in_pos' => false,
                    'show_on_label' => false,
                    'is_variant_axis' => false,
                    'is_filterable' => false,
                    'is_searchable' => $fieldDef['key'] === 'material',
                    'hidden' => false,
                ]
            );
        }

        // Create variant axis field definitions
        $variantAxes = [
            [
                'key' => 'color',
                'label' => 'Color',
                'type' => 'select',
                'options' => [
                    ['value' => 'black', 'label' => 'Black', 'active' => true],
                    ['value' => 'white', 'label' => 'White', 'active' => true],
                    ['value' => 'red', 'label' => 'Red', 'active' => true],
                    ['value' => 'blue', 'label' => 'Blue', 'active' => true],
                    ['value' => 'green', 'label' => 'Green', 'active' => true],
                    ['value' => 'yellow', 'label' => 'Yellow', 'active' => true],
                    ['value' => 'pink', 'label' => 'ပန်းရောင်', 'active' => true],
                    ['value' => 'purple', 'label' => 'ခရမ်းရောင်', 'active' => true],
                ],
            ],
            [
                'key' => 'size',
                'label' => 'Size',
                'type' => 'select',
                'options' => [
                    ['value' => 'xs', 'label' => 'XS', 'active' => true],
                    ['value' => 's', 'label' => 'S', 'active' => true],
                    ['value' => 'm', 'label' => 'M', 'active' => true],
                    ['value' => 'l', 'label' => 'L', 'active' => true],
                    ['value' => 'xl', 'label' => 'XL', 'active' => true],
                    ['value' => 'xxl', 'label' => 'XXL', 'active' => true],
                    ['value' => 'free', 'label' => 'Free', 'active' => true],
                ],
            ],
            [
                'key' => 'fit',
                'label' => 'Fit',
                'type' => 'select',
                'options' => [
                    ['value' => 'regular', 'label' => 'Regular', 'active' => true],
                    ['value' => 'slim', 'label' => 'Slim', 'active' => true],
                    ['value' => 'loose', 'label' => 'Loose', 'active' => true],
                ],
            ],
        ];

        foreach ($variantAxes as $axisDef) {
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
                ['blueprint_id' => $blueprint->id, 'field_definition_id' => $fieldDefinition->id],
                [
                    'blueprint_id' => $blueprint->id,
                    'field_definition_id' => $fieldDefinition->id,
                    'section' => 'variant_axes',
                    'sort_order' => 1,
                    'required' => in_array($axisDef['key'], ['color', 'size']),
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

        echo "Created blueprint fields and variant axes" . PHP_EOL;

        // Get references
        $pcsUnit = Unit::where('shop_owner_id', $shopOwner->id)->where('name', 'piece')->first();
        $menTopsCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', "Men's Tops")->first();
        $womenTopsCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', "Women's Tops")->first();
        $longyiCategory = Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Longyi')->first();
        $brand1 = Brand::where('shop_owner_id', $shopOwner->id)->where('name', 'Royal Fashion')->first();
        $brand2 = Brand::where('shop_owner_id', $shopOwner->id)->where('name', 'Elegance Wear')->first();

        // Create variant product with 48 combinations (6 colors x 8 sizes - but we only have 7 sizes, so let's do a smaller set)
        // Let's create a product with color and size variants
        $shirtProduct = Product::firstOrCreate(
            [
                'shop_owner_id' => $shopOwner->id,
                'name' => 'Cotton T-Shirt',
            ],
            [
                'shop_owner_id' => $shopOwner->id,
                'category_id' => $menTopsCategory->id,
                'brand_id' => $brand1->id,
                'name' => 'Cotton T-Shirt',
                'description' => 'Comfortable cotton t-shirt for everyday wear',
                'base_unit_id' => null,
                'pricing_mode' => 'fixed',
                'cost_price' => 5000,
                'min_margin_percent' => 30,
                'blueprint_id' => $blueprint->id,
                'blueprint_version' => 1,
                'product_type' => 'variant',
                'custom_fields' => [
                    'material' => '100% Cotton',
                    'season' => 'summer',
                    'gender' => 'men',
                    'care_notes' => 'Machine wash cold',
                ],
                'status' => 'active',
                'track_stock' => true,
            ]
        );

        // Create base product unit
        $baseProductUnit = ProductUnit::updateOrCreate(
            ['product_id' => $shirtProduct->id, 'unit_id' => $pcsUnit->id],
            [
                'product_id' => $shirtProduct->id,
                'unit_id' => $pcsUnit->id,
                'conversion_factor' => 1,
                'selling_price' => 7500,
                'purchase_price' => 5000,
            ]
        );

        $shirtProduct->update(['base_unit_id' => $baseProductUnit->id]);

        // Add barcode for the shirt product (about 10% of products)
        $ean13 = $this->generateEAN13();
        \App\Models\ProductBarcode::create([
            'product_id' => $shirtProduct->id,
            'barcode' => $ean13,
            'is_primary' => true,
        ]);

        // Create variants for the shirt (color x size combinations)
        $colors = ['black', 'white', 'blue', 'red'];
        $sizes = ['s', 'm', 'l', 'xl'];
        $variantCount = 0;

        foreach ($colors as $color) {
            foreach ($sizes as $size) {
                $variantCount++;
                $priceMultiplier = $size === 'xl' ? 1.2 : 1.0; // XL costs 20% more

                $variant = ProductVariant::firstOrCreate(
                    [
                        'product_id' => $shirtProduct->id,
                        'attributes' => json_encode(['color' => $color, 'size' => $size]),
                    ],
                    [
                        'product_id' => $shirtProduct->id,
                        'sku' => 'TSHIRT-' . strtoupper($color) . '-' . strtoupper($size),
                        'attributes' => json_encode(['color' => $color, 'size' => $size]),
                        'selling_price' => $shirtProduct->cost_price * 1.5 * $priceMultiplier,
                        'purchase_price' => $shirtProduct->cost_price,
                        'status' => 'active',
                    ]
                );

                // Add stock for most variants
                if ($variantCount % 4 !== 0) {
                    \App\Models\InventoryMovement::create([
                        'product_id' => $shirtProduct->id,
                        'variant_id' => $variant->id,
                        'movement_type' => 'opening',
                        'quantity' => 10,
                        'base_quantity' => 10,
                        'unit_id' => $baseProductUnit->id,
                        'note' => 'Opening stock',
                    ]);
                }
            }
        }

        echo "Created shirt product with {$variantCount} variants" . PHP_EOL;

        // Create longyi with color and "Free" size only
        $longyiProduct = Product::firstOrCreate(
            [
                'shop_owner_id' => $shopOwner->id,
                'name' => 'Traditional Longyi',
            ],
            [
                'shop_owner_id' => $shopOwner->id,
                'category_id' => $longyiCategory->id,
                'brand_id' => $brand2->id,
                'name' => 'Traditional Longyi',
                'description' => 'Traditional Myanmar longyi with beautiful patterns',
                'base_unit_id' => null,
                'pricing_mode' => 'fixed',
                'cost_price' => 8000,
                'min_margin_percent' => 30,
                'blueprint_id' => $blueprint->id,
                'blueprint_version' => 1,
                'product_type' => 'variant',
                'custom_fields' => [
                    'material' => 'Cotton Blend',
                    'season' => 'all',
                    'gender' => 'unisex',
                    'care_notes' => 'Hand wash recommended',
                ],
                'status' => 'active',
                'track_stock' => true,
            ]
        );

        $baseProductUnit = ProductUnit::updateOrCreate(
            ['product_id' => $longyiProduct->id, 'unit_id' => $pcsUnit->id],
            [
                'product_id' => $longyiProduct->id,
                'unit_id' => $pcsUnit->id,
                'conversion_factor' => 1,
                'selling_price' => 12000,
                'purchase_price' => 8000,
            ]
        );

        $longyiProduct->update(['base_unit_id' => $baseProductUnit->id]);

        // Create variants with only color, Free size
        $longyiColors = ['black', 'white', 'red', 'green', 'blue'];
        foreach ($longyiColors as $color) {
            ProductVariant::firstOrCreate(
                [
                    'product_id' => $longyiProduct->id,
                    'attributes' => json_encode(['color' => $color, 'size' => 'free']),
                ],
                [
                    'product_id' => $longyiProduct->id,
                    'sku' => 'LONGYI-' . strtoupper($color) . '-FREE',
                    'attributes' => json_encode(['color' => $color, 'size' => 'free']),
                    'selling_price' => 12000,
                    'purchase_price' => 8000,
                    'status' => 'active',
                ]
            );
        }

        echo "Created longyi product with color variants" . PHP_EOL;

        // Create a product where every variant has zero stock
        $dressProduct = Product::firstOrCreate(
            [
                'shop_owner_id' => $shopOwner->id,
                'name' => 'Summer Dress',
            ],
            [
                'shop_owner_id' => $shopOwner->id,
                'category_id' => Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Dresses')->first()->id,
                'brand_id' => $brand1->id,
                'name' => 'Summer Dress',
                'description' => 'Light and comfortable summer dress',
                'base_unit_id' => null,
                'pricing_mode' => 'fixed',
                'cost_price' => 10000,
                'min_margin_percent' => 30,
                'blueprint_id' => $blueprint->id,
                'blueprint_version' => 1,
                'product_type' => 'variant',
                'custom_fields' => [
                    'material' => 'Rayon',
                    'season' => 'summer',
                    'gender' => 'women',
                    'care_notes' => 'Dry clean only',
                ],
                'status' => 'active',
                'track_stock' => true,
            ]
        );

        $baseProductUnit = ProductUnit::updateOrCreate(
            ['product_id' => $dressProduct->id, 'unit_id' => $pcsUnit->id],
            [
                'product_id' => $dressProduct->id,
                'unit_id' => $pcsUnit->id,
                'conversion_factor' => 1,
                'selling_price' => 15000,
                'purchase_price' => 10000,
            ]
        );

        $dressProduct->update(['base_unit_id' => $baseProductUnit->id]);

        // Create variants with zero stock
        $dressColors = ['pink', 'yellow', 'blue'];
        $dressSizes = ['s', 'm', 'l'];

        foreach ($dressColors as $color) {
            foreach ($dressSizes as $size) {
                ProductVariant::firstOrCreate(
                    [
                        'product_id' => $dressProduct->id,
                        'attributes' => json_encode(['color' => $color, 'size' => $size]),
                    ],
                    [
                        'product_id' => $dressProduct->id,
                        'sku' => 'DRESS-' . strtoupper($color) . '-' . strtoupper($size),
                        'attributes' => json_encode(['color' => $color, 'size' => $size]),
                        'selling_price' => 15000,
                        'purchase_price' => 10000,
                        'status' => 'active',
                    ]
                );
                // No stock added - all variants have zero stock
            }
        }

        echo "Created dress product with zero stock variants" . PHP_EOL;

        // Create non-variant products (neutral)
        $neutralProducts = [
            [
                'name' => 'Leather Bag',
                'category' => Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Bags')->first(),
                'brand' => $brand2,
                'cost_price' => 15000,
                'selling_price' => 25000,
                'stock' => 20,
            ],
            [
                'name' => 'Leather Belt',
                'category' => Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Accessories')->first(),
                'brand' => $brand1,
                'cost_price' => 5000,
                'selling_price' => 8000,
                'stock' => 50,
            ],
            [
                'name' => 'Cotton Socks (3-pack)',
                'category' => Category::where('shop_owner_id', $shopOwner->id)->where('name', 'Accessories')->first(),
                'brand' => null,
                'cost_price' => 2000,
                'selling_price' => 3500,
                'stock' => 100,
            ],
        ];

        foreach ($neutralProducts as $item) {
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
                    'min_margin_percent' => 30,
                    'blueprint_id' => $blueprint->id,
                    'blueprint_version' => 1,
                    'product_type' => 'neutral',
                    'custom_fields' => ['gender' => 'unisex'],
                    'status' => 'active',
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

            if ($item['stock'] > 0) {
                \App\Models\InventoryMovement::create([
                    'product_id' => $product->id,
                    'movement_type' => 'opening',
                    'quantity' => $item['stock'],
                    'base_quantity' => $item['stock'],
                    'unit_id' => $baseProductUnit->id,
                    'note' => 'Opening stock',
                ]);
            }
        }

        echo "Created neutral products" . PHP_EOL;

        // Create customers
        $customers = [
            ['name' => 'Nu Nu', 'phone' => '09-111111112', 'notes' => 'Regular fashion buyer'],
            ['name' => 'Mya Mya', 'phone' => '09-222222223', 'notes' => 'Prefers traditional wear'],
            ['name' => 'ဒေါ်သန်းသန်း', 'phone' => '09-333333334', 'notes' => 'မြန်မာအဝတ်အစားဝယ်ယူသူ'],
            ['name' => 'Tun Tun', 'phone' => '09-444444445', 'notes' => ''],
            ['name' => 'Khine Khine', 'phone' => '09-555555556', 'notes' => 'VIP customer'],
            ['name' => 'Aye Aye', 'phone' => '09-666666667', 'notes' => ''],
            ['name' => 'ဦးမောင်မောင်', 'phone' => '09-777777778', 'notes' => 'အမြဲတမ်းဝယ်ယူသူ'],
            ['name' => 'Zaw Min', 'phone' => '09-888888889', 'notes' => ''],
            ['name' => 'Thuza', 'phone' => '09-999999990', 'notes' => 'New customer'],
            ['name' => 'Kyaw Kyaw', 'phone' => '09-123456789', 'notes' => 'Bulk orders'],
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
        echo "Clothing seeder completed successfully!" . PHP_EOL;
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

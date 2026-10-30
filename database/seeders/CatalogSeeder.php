<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Actions\GenerateProductVariantsAction;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    /**
     * Run the catalog seeds.
     */
    public function run(): void
    {
        // 1. Seed Warehouse
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'DHK-CENTRAL'],
            [
                'name' => 'Dhaka Central Distribution Hub (Tejgaon)',
                'address' => 'Plot 42, Tejgaon Industrial Area, Dhaka-1208',
                'contact_number' => '+8801711000000',
                'is_primary' => true,
                'is_active' => true,
            ]
        );

        // 2. Seed Categories
        $categoriesData = [
            [
                'name' => 'Smartphones & Mobile Accessories',
                'description' => 'Flagship smartphones, fast chargers, earbuds, cables, and cases.',
                'is_featured' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Consumer Electronics',
                'description' => 'Audio gear, smartwatches, power banks, and portable gadgets.',
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Computers & Office IT',
                'description' => 'Laptops, monitors, mechanical keyboards, and network accessories.',
                'is_featured' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Home & Kitchen Appliances',
                'description' => 'Blenders, air fryers, induction cookers, and robotic vacuums.',
                'is_featured' => false,
                'sort_order' => 4,
            ],
            [
                'name' => 'Fashion & Lifestyle',
                'description' => 'Premium watches, backpacks, leather wallets, and sunglasses.',
                'is_featured' => false,
                'sort_order' => 5,
            ],
        ];

        $categories = [];
        foreach ($categoriesData as $data) {
            $categories[$data['name']] = Category::firstOrCreate(
                ['slug' => Str::slug($data['name'])],
                array_merge($data, ['is_active' => true])
            );
        }

        // 3. Seed Brands
        $brandsData = [
            ['name' => 'Apple', 'origin_country' => 'United States', 'is_featured' => true],
            ['name' => 'Samsung', 'origin_country' => 'South Korea', 'is_featured' => true],
            ['name' => 'Xiaomi', 'origin_country' => 'China', 'is_featured' => true],
            ['name' => 'Anker', 'origin_country' => 'China', 'is_featured' => true],
            ['name' => 'Sony', 'origin_country' => 'Japan', 'is_featured' => true],
            ['name' => 'Walton', 'origin_country' => 'Bangladesh', 'is_featured' => true],
            ['name' => 'Baseus', 'origin_country' => 'China', 'is_featured' => false],
        ];

        $brands = [];
        foreach ($brandsData as $data) {
            $brands[$data['name']] = Brand::firstOrCreate(
                ['slug' => Str::slug($data['name'])],
                array_merge($data, ['is_active' => true])
            );
        }

        $generateVariantsAction = app(GenerateProductVariantsAction::class);

        // Helper function to seed stock for variants
        $seedStockForVariants = function ($variants) use ($warehouse) {
            foreach ($variants as $variant) {
                InventoryStock::firstOrCreate(
                    [
                        'warehouse_id' => $warehouse->id,
                        'product_variant_id' => $variant->id,
                    ],
                    [
                        'physical_quantity' => 50,
                        'reserved_quantity' => 0,
                        'bin_location' => 'ZONE-A-'.rand(10, 99),
                    ]
                );
            }
        };

        // 4. Seed Products
        // Product 1: Samsung Galaxy S24 Ultra (Flagship Import)
        $p1 = Product::firstOrCreate(
            ['slug' => 'samsung-galaxy-s24-ultra-5g'],
            [
                'name' => 'Samsung Galaxy S24 Ultra 5G (Snapdragon 8 Gen 3)',
                'category_id' => $categories['Smartphones & Mobile Accessories']->id,
                'brand_id' => $brands['Samsung']->id,
                'hs_code' => '8517.13.00',
                'origin_country' => 'Vietnam',
                'warranty_policy' => '1 Year Official Brand Warranty (With Mushak 6.3)',
                'description' => 'Titanium frame with Galaxy AI live translation and Circle to Search. 200MP quad telephoto camera with embedded S-Pen stylus. 5000mAh battery with 45W super fast charging.',
                'specifications' => [
                    'Display' => '6.8" Dynamic AMOLED 2X, 120Hz, 2600 nits',
                    'Processor' => 'Qualcomm Snapdragon 8 Gen 3 for Galaxy',
                    'Main Camera' => '200MP Wide + 50MP Periscope + 12MP Ultra-Wide',
                    'Battery' => '5000mAh with 45W Super Fast Charging 2.0',
                    'Build' => 'Titanium Frame, Corning Gorilla Armor Glass',
                ],
                'is_published' => true,
                'is_featured' => true,
            ]
        );
        if ($p1->variants()->count() === 0) {
            $vars = $generateVariantsAction(
                $p1,
                [
                    'Color' => ['Titanium Gray', 'Titanium Black', 'Titanium Violet'],
                    'Storage' => ['256GB', '512GB'],
                ],
                138000.0,
                112000.0,
                0.232
            );
            $seedStockForVariants($vars);
        }

        // Product 2: Apple iPhone 15 Pro Max
        $p2 = Product::firstOrCreate(
            ['slug' => 'apple-iphone-15-pro-max'],
            [
                'name' => 'Apple iPhone 15 Pro Max (Aerospace Titanium)',
                'category_id' => $categories['Smartphones & Mobile Accessories']->id,
                'brand_id' => $brands['Apple']->id,
                'hs_code' => '8517.13.00',
                'origin_country' => 'United States',
                'warranty_policy' => '1 Year Official International Warranty (BTRC Approved)',
                'description' => 'Forged in aerospace-grade titanium with the groundbreaking A17 Pro chip. Customizable Action button, USB-C connector with USB 3 speeds, and 5x optical telephoto zoom.',
                'specifications' => [
                    'Display' => '6.7" Super Retina XDR OLED, ProMotion 120Hz',
                    'Processor' => 'A17 Pro (3nm architecture)',
                    'Camera' => '48MP Main + 12MP Ultra Wide + 12MP 5x Telephoto',
                    'Port' => 'USB-C (supports USB 3 up to 10Gb/s)',
                ],
                'is_published' => true,
                'is_featured' => true,
            ]
        );
        if ($p2->variants()->count() === 0) {
            $vars = $generateVariantsAction(
                $p2,
                [
                    'Color' => ['Natural Titanium', 'Blue Titanium', 'Black Titanium'],
                    'Storage' => ['256GB', '512GB'],
                ],
                149000.0,
                124000.0,
                0.221
            );
            $seedStockForVariants($vars);
        }

        // Product 3: Anker 65W GaNPrime Fast Charger
        $p3 = Product::firstOrCreate(
            ['slug' => 'anker-65w-ganprime-fast-charger'],
            [
                'name' => 'Anker 65W GaNPrime Fast Charger (3-Port)',
                'category_id' => $categories['Consumer Electronics']->id,
                'brand_id' => $brands['Anker']->id,
                'hs_code' => '8504.40.90',
                'origin_country' => 'China',
                'warranty_policy' => '18 Months Official Replacement Warranty',
                'description' => 'Ultra-compact 65W GaN charger with dual USB-C and one USB-A port. High speed charging for laptops and phones with PowerIQ 4.0 dynamic power distribution.',
                'specifications' => [
                    'Output Power' => '65W Max',
                    'Ports' => '2x USB-C, 1x USB-A',
                    'Technology' => 'GaNPrime III with ActiveShield 2.0',
                    'Input' => '100-240V ~ 50/60Hz',
                ],
                'is_published' => true,
                'is_featured' => true,
            ]
        );
        if ($p3->variants()->count() === 0) {
            $vars = $generateVariantsAction(
                $p3,
                ['Color' => ['Midnight Black', 'Pearl White']],
                4200.0,
                2800.0,
                0.180
            );
            $seedStockForVariants($vars);
        }

        // Product 4: Sony WH-1000XM5 Wireless Headphones
        $p4 = Product::firstOrCreate(
            ['slug' => 'sony-wh-1000xm5-wireless-noise-cancelling-headphones'],
            [
                'name' => 'Sony WH-1000XM5 Wireless Noise-Cancelling Headphones',
                'category_id' => $categories['Consumer Electronics']->id,
                'brand_id' => $brands['Sony']->id,
                'hs_code' => '8518.30.00',
                'origin_country' => 'Malaysia',
                'warranty_policy' => '1 Year Official Sony Warranty',
                'description' => 'Industry-leading noise cancellation with two processors and 8 microphones. Up to 30 hours battery life with quick charging. Integrated LDAC high-resolution wireless audio.',
                'specifications' => [
                    'Battery Life' => 'Up to 30 Hours (ANC On)',
                    'Driver' => '30mm Carbon Fiber Composite',
                    'Bluetooth' => 'v5.2 (LDAC, AAC, SBC)',
                    'Weight' => '250g',
                ],
                'is_published' => true,
                'is_featured' => true,
            ]
        );
        if ($p4->variants()->count() === 0) {
            $vars = $generateVariantsAction(
                $p4,
                ['Color' => ['Black', 'Silver']],
                41500.0,
                32000.0,
                0.250
            );
            $seedStockForVariants($vars);
        }

        // Product 5: Walton Primo HM7 (Local Bangladesh Sourcing)
        $p5 = Product::firstOrCreate(
            ['slug' => 'walton-primo-hm7-smartphone'],
            [
                'name' => 'Walton Primo HM7 (Domestic Procurement with Mushak 6.3)',
                'category_id' => $categories['Smartphones & Mobile Accessories']->id,
                'brand_id' => $brands['Walton']->id,
                'hs_code' => '8517.13.00',
                'origin_country' => 'Bangladesh',
                'warranty_policy' => '1 Year Official Walton Service Warranty',
                'description' => 'Locally assembled domestic procurement in BDT (৳) with supplier Mushak 6.3 invoice. 6.52" HD+ display, Octa-Core processor, and 5000mAh high capacity battery.',
                'specifications' => [
                    'Display' => '6.52" HD+ V-Notch IPS',
                    'Processor' => 'Octa-Core 1.6GHz Cortex-A55',
                    'Battery' => '5000mAh Li-Polymer',
                    'RAM/ROM' => '4GB RAM + 64GB Storage',
                    'Sourcing Origin' => 'Local Bangladesh Domestic Procurement (BDT ৳)',
                ],
                'is_published' => true,
                'is_featured' => false,
            ]
        );
        if ($p5->variants()->count() === 0) {
            $vars = $generateVariantsAction(
                $p5,
                ['Color' => ['Royal Blue', 'Emerald Green']],
                11999.0,
                9200.0,
                0.195
            );
            $seedStockForVariants($vars);
        }

        // Ensure all existing variants have stock
        foreach (Product::with('variants')->get() as $prod) {
            foreach ($prod->variants as $variant) {
                InventoryStock::firstOrCreate(
                    [
                        'warehouse_id' => $warehouse->id,
                        'product_variant_id' => $variant->id,
                    ],
                    [
                        'physical_quantity' => 50,
                        'reserved_quantity' => 0,
                        'bin_location' => 'ZONE-A-01',
                    ]
                );
            }
        }
    }
}

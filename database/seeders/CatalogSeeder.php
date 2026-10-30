<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Actions\GenerateProductVariantsAction;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    /**
     * Run the catalog seeds.
     */
    public function run(): void
    {
        // 1. Seed Categories
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

        // 2. Seed Brands
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

        // 3. Seed Sample Commercial Products if none exist
        if (Product::count() === 0) {
            $generateVariantsAction = app(GenerateProductVariantsAction::class);

            // Sample Product 1: Anker 65W Fast GaN Charger
            $p1 = Product::create([
                'name' => 'Anker 65W GaNPrime Fast Charger',
                'slug' => 'anker-65w-ganprime-fast-charger',
                'category_id' => $categories['Consumer Electronics']->id,
                'brand_id' => $brands['Anker']->id,
                'hs_code' => '8504.40.90',
                'origin_country' => 'China',
                'warranty_policy' => '18 Months Official Replacement Warranty',
                'description' => 'Ultra-compact 65W GaN charger with dual USB-C and one USB-A port. High speed charging for laptops and phones.',
                'specifications' => [
                    'Output Power' => '65W Max',
                    'Ports' => '2x USB-C, 1x USB-A',
                    'Technology' => 'GaNPrime III',
                    'Input' => '100-240V ~ 50/60Hz',
                ],
                'is_published' => true,
                'is_featured' => true,
            ]);

            $generateVariantsAction(
                $p1,
                ['Color' => ['Midnight Black', 'Pearl White']],
                4200.0,
                2800.0,
                0.180
            );

            // Sample Product 2: Sony WH-1000XM5 Wireless Headphones
            $p2 = Product::create([
                'name' => 'Sony WH-1000XM5 Wireless Noise-Cancelling Headphones',
                'slug' => 'sony-wh-1000xm5-wireless-noise-cancelling-headphones',
                'category_id' => $categories['Consumer Electronics']->id,
                'brand_id' => $brands['Sony']->id,
                'hs_code' => '8518.30.00',
                'origin_country' => 'Malaysia',
                'warranty_policy' => '1 Year Official Brand Warranty',
                'description' => 'Industry-leading noise cancellation with two processors and 8 microphones. Up to 30 hours battery life with quick charging.',
                'specifications' => [
                    'Battery Life' => 'Up to 30 Hours',
                    'Driver' => '30mm Carbon Fiber',
                    'Bluetooth' => 'v5.2 (LDAC, AAC, SBC)',
                    'Weight' => '250g',
                ],
                'is_published' => true,
                'is_featured' => true,
            ]);

            $generateVariantsAction(
                $p2,
                ['Color' => ['Black', 'Silver']],
                41500.0,
                32000.0,
                0.250
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Actions\GenerateProductVariantsAction;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Smart Watches',
            'is_active' => true,
        ]);

        $this->brand = Brand::create([
            'name' => 'Xiaomi Global',
            'origin_country' => 'China',
            'is_active' => true,
        ]);
    }

    public function test_product_creates_with_ulid_hs_code_and_specifications(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'name' => 'Xiaomi Watch S3',
            'hs_code' => '8517.62.90',
            'origin_country' => 'China',
            'warranty_policy' => '1 Year Official Warranty',
            'description' => 'AMOLED display smartwatch with interchangeable bezel.',
            'specifications' => [
                'Display' => '1.43 AMOLED',
                'Battery' => '486mAh up to 15 days',
                'Water Resistance' => '5ATM',
            ],
            'is_published' => true,
            'is_featured' => true,
        ]);

        $this->assertTrue(Str::isUlid($product->id));
        $this->assertEquals('8517.62.90', $product->hs_code);
        $this->assertEquals('China', $product->origin_country);
        $this->assertEquals('1.43 AMOLED', $product->specifications['Display']);
        $this->assertEquals('xiaomi-watch-s3', $product->slug);
    }

    public function test_variant_matrix_generator_produces_cartesian_combinations(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'name' => 'Fitness Smart Band 8',
            'hs_code' => '8517.62.90',
            'is_published' => true,
        ]);

        $action = new GenerateProductVariantsAction;

        // 2 Colors x 2 Strap Materials = 4 Variants
        $attributeOptions = [
            'Color' => ['Graphite Black', 'Gold Rose'],
            'Strap' => ['Silicone', 'Leather'],
        ];

        $variants = $action($product, $attributeOptions, 4500.00, 3200.00, 0.250);

        $this->assertCount(4, $variants);
        $this->assertDatabaseCount('product_variants', 4);

        // Verify all generated SKUs are unique
        $skus = $variants->pluck('sku')->toArray();
        $this->assertCount(4, array_unique($skus));

        // Check attributes structure
        $first = $variants->first();
        $this->assertArrayHasKey('Color', $first->attributes);
        $this->assertArrayHasKey('Strap', $first->attributes);
        $this->assertEquals(4500.00, (float) $first->selling_price_bdt);
        $this->assertEquals(3200.00, (float) $first->purchase_cost_bdt);

        // Check display name
        $this->assertStringContainsString('Fitness Smart Band 8', $first->display_name);
        $this->assertStringContainsString('Color:', $first->display_name);

        // Verify product price range accessors
        $this->assertEquals(4500.00, $product->min_price_bdt);
        $this->assertEquals(4500.00, $product->max_price_bdt);
    }
}


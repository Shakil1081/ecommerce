<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogAdminTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create(['name' => 'Tablets', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Apple', 'origin_country' => 'USA', 'is_active' => true]);
    }

    public function test_can_view_product_catalog_index(): void
    {
        Product::create([
            'category_id' => $this->category->id,
            'name' => 'iPad Air M2',
            'origin_country' => 'USA',
            'hs_code' => '8471.30.00',
            'is_published' => true,
        ]);

        $response = $this->get(route('admin.products.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Catalog/ProductIndex')
            ->has('products', 1)
        );
    }

    public function test_can_create_product_via_admin_endpoint(): void
    {
        $payload = [
            'name' => 'Apple Watch Ultra 2',
            'category_id' => $this->category->id,
            'brand_id' => null,
            'hs_code' => '8517.62.90',
            'origin_country' => 'USA',
            'warranty_policy' => '1 Year Apple Official Warranty',
            'description' => 'Titanium smartwatch for adventure.',
            'default_selling_price' => 95000.00,
            'default_purchase_cost' => 82000.00,
            'is_published' => true,
            'is_featured' => true,
        ];

        $response = $this->post(route('admin.products.store'), $payload);

        $response->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('products', ['name' => 'Apple Watch Ultra 2']);

        $product = Product::where('name', 'Apple Watch Ultra 2')->first();
        $this->assertNotNull($product);
        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id]);
    }

    public function test_matrix_generator_endpoint_creates_variants(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'MacBook Air M3',
            'origin_country' => 'USA',
            'hs_code' => '8471.30.00',
            'is_published' => true,
        ]);

        $payload = [
            'attributes' => [
                'Color' => ['Midnight', 'Starlight'],
                'RAM' => ['8GB', '16GB'],
            ],
            'selling_price_bdt' => 145000.00,
            'purchase_cost_bdt' => 125000.00,
            'weight_kg' => 1.240,
        ];

        $response = $this->post(route('admin.products.generate-variants', $product), $payload);

        $response->assertRedirect(route('admin.products.matrix', $product));

        // 2 Colors x 2 RAMs = 4 Variants
        $this->assertEquals(4, ProductVariant::where('product_id', $product->id)->count());
    }

    public function test_can_delete_product_and_cascade_variants(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Obsolete Gadget',
            'origin_country' => 'China',
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'OBSOLETE-001',
            'attributes' => [],
            'selling_price_bdt' => 500,
        ]);

        $response = $this->delete(route('admin.products.destroy', $product));

        $response->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_variants', ['sku' => 'OBSOLETE-001']);
    }
}

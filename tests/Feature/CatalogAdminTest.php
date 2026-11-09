<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ->has('products.data', 1)
        );
    }

    public function test_can_filter_product_catalog_by_search_query(): void
    {
        Product::create([
            'category_id' => $this->category->id,
            'name' => 'MacBook Pro M3 Max',
            'origin_country' => 'USA',
            'is_published' => true,
        ]);

        Product::create([
            'category_id' => $this->category->id,
            'name' => 'Dell XPS 15',
            'origin_country' => 'USA',
            'is_published' => true,
        ]);

        $response = $this->get(route('admin.products.index', ['search' => 'MacBook']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Catalog/ProductIndex')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'MacBook Pro M3 Max')
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

    public function test_creating_product_with_duplicate_slug_resolves_unique_slug(): void
    {
        $payload1 = [
            'name' => 'Brenden Ferrell',
            'category_id' => $this->category->id,
            'origin_country' => 'USA',
            'default_selling_price' => 1200.00,
        ];

        $response1 = $this->post(route('admin.products.store'), $payload1);
        $response1->assertRedirect(route('admin.products.index'));

        $first = Product::where('slug', 'brenden-ferrell')->first();
        $this->assertNotNull($first);

        // Try creating with the exact same name/slug again
        $payload2 = [
            'name' => 'Brenden Ferrell',
            'category_id' => $this->category->id,
            'origin_country' => 'USA',
            'default_selling_price' => 1200.00,
        ];

        $response2 = $this->post(route('admin.products.store'), $payload2);
        $response2->assertRedirect(route('admin.products.index'));

        $second = Product::where('slug', 'brenden-ferrell-1')->first();
        $this->assertNotNull($second);
    }

    public function test_can_create_product_with_uploaded_media_image(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('headphone.jpg', 600, 600)->size(300);

        $payload = [
            'name' => 'Bose QuietComfort 45',
            'category_id' => $this->category->id,
            'origin_country' => 'USA',
            'default_selling_price' => 32000.00,
            'image' => $file,
        ];

        $response = $this->post(route('admin.products.store'), $payload);

        $response->assertRedirect(route('admin.products.index'));

        $product = Product::where('name', 'Bose QuietComfort 45')->first();
        $this->assertNotNull($product);
        $this->assertTrue($product->hasMedia('products'));
    }

    public function test_can_create_product_with_initial_stock_saved_to_inventory(): void
    {
        $payload = [
            'name' => 'Sony WH-1000XM5',
            'category_id' => $this->category->id,
            'origin_country' => 'Japan',
            'default_selling_price' => 38000.00,
            'default_purchase_cost' => 31000.00,
            'stock_quantity' => 75,
            'is_published' => true,
        ];

        $response = $this->post(route('admin.products.store'), $payload);

        $response->assertRedirect(route('admin.products.index'));

        $product = Product::where('name', 'Sony WH-1000XM5')->first();
        $this->assertNotNull($product);

        $variant = $product->defaultVariant;
        $this->assertNotNull($variant);
        $this->assertEquals(75, $variant->available_quantity);

        $this->assertDatabaseHas('inventory_stocks', [
            'product_variant_id' => $variant->id,
            'physical_quantity' => 75,
        ]);
    }

    public function test_can_update_product_and_change_stock_quantity(): void
    {
        $payload = [
            'name' => 'DJI Osmo Pocket 3',
            'category_id' => $this->category->id,
            'origin_country' => 'China',
            'default_selling_price' => 62000.00,
            'stock_quantity' => 30,
            'is_published' => true,
        ];

        $this->post(route('admin.products.store'), $payload);

        $product = Product::where('name', 'DJI Osmo Pocket 3')->firstOrFail();
        $this->assertEquals(30, $product->defaultVariant->available_quantity);

        // Update stock to 125
        $updatePayload = [
            'name' => 'DJI Osmo Pocket 3 Updated',
            'category_id' => $this->category->id,
            'origin_country' => 'China',
            'default_selling_price' => 64000.00,
            'stock_quantity' => 125,
            'is_published' => true,
        ];

        $response = $this->put(route('admin.products.update', $product), $updatePayload);

        $response->assertRedirect(route('admin.products.index'));

        $product->refresh();
        $this->assertEquals('DJI Osmo Pocket 3 Updated', $product->name);
        $this->assertEquals(125, $product->defaultVariant->available_quantity);
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

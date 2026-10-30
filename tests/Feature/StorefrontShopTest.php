<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class StorefrontShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
    }

    public function test_customer_can_view_home_and_shop_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/Shop')
            ->has('products', 5)
            ->has('categories', 5)
            ->has('brands', 7)
            ->has('filters')
        );

        $shopResponse = $this->get('/shop');
        $shopResponse->assertOk();
        $shopResponse->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/Shop')
            ->has('products', 5)
        );
    }

    public function test_customer_can_filter_products_by_category(): void
    {
        $response = $this->get('/shop?category=consumer-electronics');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/Shop')
            ->has('products', 2)
            ->where('filters.category', 'consumer-electronics')
        );
    }

    public function test_customer_can_search_products(): void
    {
        $response = $this->get('/shop?search=Anker');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/Shop')
            ->has('products', 1)
            ->where('products.0.brand_name', 'Anker')
        );
    }

    public function test_customer_can_view_product_detail_page(): void
    {
        $response = $this->get('/products/samsung-galaxy-s24-ultra-5g');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/ProductDetail')
            ->has('product')
            ->where('product.name', 'Samsung Galaxy S24 Ultra 5G (Snapdragon 8 Gen 3)')
            ->where('product.origin_country', 'Vietnam')
            ->has('variants', 6)
            ->has('optionGroups')
            ->has('optionGroups.Color', 3)
            ->has('optionGroups.Storage', 2)
            ->has('gallery')
        );
    }

    public function test_product_detail_returns_404_for_non_existent_slug(): void
    {
        $response = $this->get('/products/non-existent-product-xyz');

        $response->assertNotFound();
    }
}

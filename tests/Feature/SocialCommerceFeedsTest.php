<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialCommerceFeedsTest extends TestCase
{
    use RefreshDatabase;

    private function createPublishedProduct(string $name, float $price = 1850.00, int $stock = 10, bool $published = true): Product
    {
        $category = Category::create([
            'name' => 'Smart Watches',
            'slug' => 'smart-watches-'.uniqid(),
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Amazfit',
            'slug' => 'amazfit-'.uniqid(),
            'origin_country' => 'China',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => $name,
            'slug' => 'product-'.uniqid(),
            'description' => 'Flagship smartwatch with AMOLED display and heart rate monitoring.',
            'is_published' => $published,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-WATCH-'.uniqid(),
            'barcode_ean' => '8901234567890',
            'attributes' => ['color' => 'Silver Grey'],
            'selling_price_bdt' => $price,
            'purchase_cost_bdt' => $price * 0.6,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'WH-DHAKA'],
            ['name' => 'Dhaka Central Hub', 'is_primary' => true, 'is_active' => true]
        );

        InventoryStock::create([
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'physical_quantity' => $stock,
            'reserved_quantity' => 0,
        ]);

        return $product;
    }

    public function test_google_merchant_feed_returns_valid_xml(): void
    {
        $published = $this->createPublishedProduct('Amazfit GTS 4 Mini', 8500.00, 20, true);
        $unpublished = $this->createPublishedProduct('Hidden Draft Watch', 5000.00, 5, false);

        $response = $this->get(route('feeds.google-merchant'));

        $response->assertOk();
        $this->assertStringContainsString('xml', (string) $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">', $content);
        $this->assertStringContainsString('<g:title><![CDATA[Amazfit GTS 4 Mini (Silver Grey)]]></g:title>', $content);
        $this->assertStringContainsString('<g:price>8500.00 BDT</g:price>', $content);
        $this->assertStringContainsString('<g:availability>in_stock</g:availability>', $content);
        $this->assertStringContainsString('<g:brand><![CDATA[Amazfit]]></g:brand>', $content);
        $this->assertStringContainsString('<g:gtin>8901234567890</g:gtin>', $content);

        // Verify draft/unpublished product is not included
        $this->assertStringNotContainsString('Hidden Draft Watch', $content);
    }

    public function test_facebook_catalog_feed_returns_valid_csv(): void
    {
        $product = $this->createPublishedProduct('Amazfit Band 7', 3500.00, 15, true);

        $response = $this->get(route('feeds.facebook-catalog'));

        $response->assertOk();
        $this->assertStringContainsString('csv', (string) $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $lines = explode("\n", trim($content));

        $this->assertGreaterThanOrEqual(2, count($lines));
        $this->assertStringContainsString('id,title,description,availability,condition,price,link,image_link,brand', $lines[0]);
        $this->assertStringContainsString('Amazfit Band 7', $lines[1]);
        $this->assertStringContainsString('3500.00 BDT', $lines[1]);
        $this->assertStringContainsString('in stock', $lines[1]);
    }

    public function test_tiktok_shop_feed_returns_valid_csv(): void
    {
        $product = $this->createPublishedProduct('Amazfit T-Rex 2', 17500.00, 8, true);

        $response = $this->get(route('feeds.tiktok-shop'));

        $response->assertOk();
        $this->assertStringContainsString('csv', (string) $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $lines = explode("\n", trim($content));

        $this->assertGreaterThanOrEqual(2, count($lines));
        $this->assertStringContainsString('sku_id,title,description,availability,price,product_url,main_image_url,brand', $lines[0]);
        $this->assertStringContainsString('Amazfit T-Rex 2', $lines[1]);
        $this->assertStringContainsString('17500.00 BDT', $lines[1]);
        $this->assertStringContainsString('in_stock', $lines[1]);
    }

    public function test_out_of_stock_variant_marked_accordingly(): void
    {
        $this->createPublishedProduct('Out of Stock Smartband', 1200.00, 0, true);

        $xmlResponse = $this->get(route('feeds.google-merchant'));
        $this->assertStringContainsString('<g:availability>out_of_stock</g:availability>', $xmlResponse->getContent());

        $csvResponse = $this->get(route('feeds.facebook-catalog'));
        $this->assertStringContainsString('out of stock', $csvResponse->getContent());
    }
}

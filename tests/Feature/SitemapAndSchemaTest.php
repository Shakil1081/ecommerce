<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Seo\DTOs\MetaTagData;
use App\Modules\Seo\Services\SchemaGeneratorService;
use Tests\TestCase;

class SitemapAndSchemaTest extends TestCase
{
    public function test_sitemap_xml_returns_valid_xml_response(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type') ?? '');

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $content);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $content);
        $this->assertStringContainsString('<loc>', $content);
        $this->assertStringContainsString('</urlset>', $content);
    }

    public function test_robots_txt_returns_proper_directives(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type') ?? '');

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('User-agent: *', $content);
        $this->assertStringContainsString('Disallow: /admin/', $content);
        $this->assertStringContainsString('Disallow: /checkout/', $content);
        $this->assertStringContainsString('Sitemap:', $content);
    }

    public function test_schema_generator_produces_valid_json_ld_structures(): void
    {
        $generator = new SchemaGeneratorService;

        // 1. Organization
        $org = $generator->forOrganization(
            name: 'TradingHub BD',
            url: 'https://tradinghub.com.bd',
            logoUrl: 'https://tradinghub.com.bd/logo.png',
            sameAs: ['https://facebook.com/tradinghubbd'],
            contactPoint: ['telephone' => '+8801700000000']
        );
        $this->assertEquals('Organization', $org['@type']);
        $this->assertEquals('TradingHub BD', $org['name']);

        // 2. WebSite
        $website = $generator->forWebSite(
            name: 'TradingHub BD',
            url: 'https://tradinghub.com.bd',
            searchUrlTemplate: 'https://tradinghub.com.bd/search?q={search_term_string}'
        );
        $this->assertEquals('WebSite', $website['@type']);
        $this->assertEquals('SearchAction', $website['potentialAction']['@type']);

        // 3. Product
        $product = $generator->forProduct(
            name: 'Original Smart Watch Pro',
            description: 'Direct imported high-precision smartwatch with BDT warranty.',
            sku: 'SW-PRO-001',
            images: ['https://tradinghub.com.bd/media/watch.jpg'],
            price: 3499.00,
            currency: 'BDT',
            brand: 'TechBrand',
            ratingValue: 4.8,
            reviewCount: 36
        );
        $this->assertEquals('Product', $product['@type']);
        $this->assertEquals('3499.00', $product['offers']['price']);
        $this->assertEquals('BDT', $product['offers']['priceCurrency']);
        $this->assertEquals('4.8', $product['aggregateRating']['ratingValue']);

        // 4. Breadcrumbs
        $breadcrumbs = $generator->forBreadcrumbList([
            ['name' => 'Home', 'url' => 'https://tradinghub.com.bd'],
            ['name' => 'Electronics', 'url' => 'https://tradinghub.com.bd/electronics'],
            ['name' => 'Smart Watches', 'url' => 'https://tradinghub.com.bd/electronics/smart-watches'],
        ]);
        $this->assertEquals('BreadcrumbList', $breadcrumbs['@type']);
        $this->assertCount(3, $breadcrumbs['itemListElement']);

        // 5. FAQ
        $faq = $generator->forFaqPage([
            ['question' => 'How long does delivery take inside Dhaka?', 'answer' => '24 to 48 hours.'],
            ['question' => 'Is Cash on Delivery available?', 'answer' => 'Yes, nationwide COD is available.'],
        ]);
        $this->assertEquals('FAQPage', $faq['@type']);
        $this->assertCount(2, $faq['mainEntity']);
    }

    public function test_meta_tag_dto_renders_json_ld_script_tags(): void
    {
        $generator = new SchemaGeneratorService;
        $orgSchema = $generator->forOrganization(
            name: 'TradingHub BD',
            url: 'https://tradinghub.com.bd',
            logoUrl: 'https://tradinghub.com.bd/logo.png'
        );

        $dto = new MetaTagData(
            title: 'Best Electronics in Bangladesh | TradingHub',
            description: 'Order 100% genuine imported electronics with fast nationwide delivery in Bangladesh.',
            canonicalUrl: 'https://tradinghub.com.bd',
            jsonLdSchemas: [$orgSchema]
        );

        $rendered = $dto->renderJsonLd();
        $this->assertStringContainsString('<script type="application/ld+json">', $rendered);
        $this->assertStringContainsString('"@type": "Organization"', $rendered);
        $this->assertStringContainsString('TradingHub BD', $rendered);
        $this->assertStringContainsString('</script>', $rendered);

        $props = $dto->toArray();
        $this->assertEquals('Best Electronics in Bangladesh | TradingHub', $props['title']);
        $this->assertEquals('index, follow', $props['robots']);
    }
}

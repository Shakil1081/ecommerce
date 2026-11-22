<?php

declare(strict_types=1);

namespace App\Modules\Seo\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Content\Models\StorePage;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Generate dynamic XML sitemap for search engines.
     */
    public function sitemap(): Response
    {
        $baseUrl = url('/');
        $now = date('c');

        $staticPages = [
            ['loc' => $baseUrl.'/', 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => $baseUrl.'/shop', 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => $baseUrl.'/track-order', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ];

        // Published store / policy pages (only real, routable URLs)
        foreach (StorePage::query()->published()->get(['slug', 'updated_at']) as $storePage) {
            $staticPages[] = [
                'loc' => $baseUrl.'/'.$storePage->slug,
                'priority' => in_array($storePage->slug, ['about', 'contact'], true) ? '0.7' : '0.4',
                'changefreq' => 'monthly',
                'lastmod' => $storePage->updated_at?->toAtomString(),
            ];
        }

        foreach (Product::query()->where('is_published', true)->get(['slug', 'updated_at']) as $product) {
            $staticPages[] = [
                'loc' => $baseUrl.'/products/'.rawurlencode((string) $product->slug),
                'priority' => '0.8',
                'changefreq' => 'weekly',
                'lastmod' => $product->updated_at?->toAtomString(),
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($staticPages as $page) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($page['loc'], ENT_XML1)."</loc>\n";
            $xml .= '    <lastmod>'.($page['lastmod'] ?? $now)."</lastmod>\n";
            $xml .= "    <changefreq>{$page['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$page['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }

    /**
     * Generate dynamic robots.txt file.
     */
    public function robots(): Response
    {
        $baseUrl = url('/');
        $sitemapUrl = $baseUrl.'/sitemap.xml';

        $lines = [
            'User-agent: *',
            'Disallow: /admin/',
            'Disallow: /checkout/',
            'Disallow: /cart/',
            'Disallow: /api/',
            'Allow: /',
            '',
            "Sitemap: {$sitemapUrl}",
        ];

        $content = implode("\n", $lines)."\n";

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}

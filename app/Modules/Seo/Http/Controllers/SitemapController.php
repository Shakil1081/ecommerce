<?php

declare(strict_types=1);

namespace App\Modules\Seo\Http\Controllers;

use App\Http\Controllers\Controller;
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
            ['loc' => $baseUrl.'/about-us', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => $baseUrl.'/contact', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => $baseUrl.'/track-order', 'priority' => '0.9', 'changefreq' => 'always'],
            ['loc' => $baseUrl.'/privacy-policy', 'priority' => '0.5', 'changefreq' => 'yearly'],
            ['loc' => $baseUrl.'/terms-and-conditions', 'priority' => '0.5', 'changefreq' => 'yearly'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($staticPages as $page) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$page['loc']}</loc>\n";
            $xml .= "    <lastmod>{$now}</lastmod>\n";
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


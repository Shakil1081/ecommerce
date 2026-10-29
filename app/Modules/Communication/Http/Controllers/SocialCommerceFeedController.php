<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Services\SocialCommerceFeedService;
use Illuminate\Http\Response;

class SocialCommerceFeedController extends Controller
{
    /**
     * Serve Google Merchant Center XML feed.
     */
    public function googleMerchant(SocialCommerceFeedService $service): Response
    {
        $xml = $service->generateGoogleMerchantXml();

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Serve Facebook Catalog & Instagram Shop CSV feed.
     */
    public function facebookCatalog(SocialCommerceFeedService $service): Response
    {
        $csv = $service->generateFacebookCatalogCsv();

        return response($csv, 200)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename="facebook-catalog.csv"')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Serve TikTok Shop Catalog CSV feed.
     */
    public function tiktokCatalog(SocialCommerceFeedService $service): Response
    {
        $csv = $service->generateTikTokCatalogCsv();

        return response($csv, 200)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename="tiktok-shop.csv"')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}

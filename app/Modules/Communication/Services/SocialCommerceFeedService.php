<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Catalog\Models\Product;

class SocialCommerceFeedService
{
    /**
     * Generate Google Merchant Center XML feed conforming to Google Shopping RSS 2.0 specifications.
     */
    public function generateGoogleMerchantXml(): string
    {
        $products = Product::with(['category', 'brand', 'variants.stocks'])
            ->where('is_published', true)
            ->get();

        $storeName = htmlspecialchars(config('app.name', 'E-Commerce BD'), ENT_XML1);
        $storeUrl = htmlspecialchars(url('/'), ENT_XML1);
        $storeDesc = htmlspecialchars('High-quality global imports and domestic consumer electronics & lifestyle goods in Bangladesh.', ENT_XML1);

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<rss version=\"2.0\" xmlns:g=\"http://base.google.com/ns/1.0\">\n";
        $xml .= "  <channel>\n";
        $xml .= "    <title>{$storeName}</title>\n";
        $xml .= "    <link>{$storeUrl}</link>\n";
        $xml .= "    <description>{$storeDesc}</description>\n";

        foreach ($products as $product) {
            $brandName = $product->brand?->name ?: config('app.name', 'Generic');
            $categoryName = $product->category?->name ?: 'General';
            $imageUrl = $product->getFirstMediaUrl('gallery') ?: url('/images/product-placeholder.png');

            $variants = $product->variants->where('is_active', true);
            if ($variants->isEmpty()) {
                continue;
            }

            foreach ($variants as $variant) {
                $sku = $variant->sku ?: (string) $variant->id;
                $priceBdt = number_format((float) $variant->selling_price_bdt, 2, '.', '').' BDT';
                $productUrl = url("/products/{$product->slug}?sku={$sku}");
                $inStock = $variant->available_quantity > 0 ? 'in_stock' : 'out_of_stock';

                $title = $product->name;
                if (! empty($variant->attributes)) {
                    $attrString = implode(' / ', array_values($variant->attributes));
                    $title .= " ({$attrString})";
                }

                $xml .= "    <item>\n";
                $xml .= "      <g:id>{$sku}</g:id>\n";
                $xml .= '      <g:title><![CDATA['.$title."]]></g:title>\n";
                $xml .= '      <g:description><![CDATA['.($product->description ?: $title)."]]></g:description>\n";
                $xml .= "      <g:link>{$productUrl}</g:link>\n";
                $xml .= "      <g:image_link>{$imageUrl}</g:image_link>\n";
                $xml .= "      <g:condition>new</g:condition>\n";
                $xml .= "      <g:availability>{$inStock}</g:availability>\n";
                $xml .= "      <g:price>{$priceBdt}</g:price>\n";
                $xml .= '      <g:brand><![CDATA['.$brandName."]]></g:brand>\n";
                $xml .= "      <g:mpn>{$sku}</g:mpn>\n";

                if (! empty($variant->barcode_ean)) {
                    $xml .= "      <g:gtin>{$variant->barcode_ean}</g:gtin>\n";
                }

                $xml .= '      <g:product_type><![CDATA['.$categoryName."]]></g:product_type>\n";
                $xml .= "    </item>\n";
            }
        }

        $xml .= "  </channel>\n";
        $xml .= '</rss>';

        return $xml;
    }

    /**
     * Generate Facebook Catalog & Instagram Shop CSV feed.
     */
    public function generateFacebookCatalogCsv(): string
    {
        $products = Product::with(['category', 'brand', 'variants.stocks'])
            ->where('is_published', true)
            ->get();

        $rows = [];
        $rows[] = ['id', 'title', 'description', 'availability', 'condition', 'price', 'link', 'image_link', 'brand', 'google_product_category'];

        foreach ($products as $product) {
            $brandName = $product->brand?->name ?: config('app.name', 'Generic');
            $categoryName = $product->category?->name ?: 'General';
            $imageUrl = $product->getFirstMediaUrl('gallery') ?: url('/images/product-placeholder.png');

            $variants = $product->variants->where('is_active', true);
            foreach ($variants as $variant) {
                $sku = $variant->sku ?: (string) $variant->id;
                $priceBdt = number_format((float) $variant->selling_price_bdt, 2, '.', '').' BDT';
                $productUrl = url("/products/{$product->slug}?sku={$sku}");
                $inStock = $variant->available_quantity > 0 ? 'in stock' : 'out of stock';

                $title = $product->name;
                if (! empty($variant->attributes)) {
                    $attrString = implode(' / ', array_values($variant->attributes));
                    $title .= " ({$attrString})";
                }

                $rows[] = [
                    $sku,
                    $title,
                    $product->description ?: $title,
                    $inStock,
                    'new',
                    $priceBdt,
                    $productUrl,
                    $imageUrl,
                    $brandName,
                    $categoryName,
                ];
            }
        }

        $fp = fopen('php://memory', 'r+');
        if ($fp === false) {
            return '';
        }

        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return (string) $csv;
    }

    /**
     * Generate TikTok Shop Catalog CSV export.
     */
    public function generateTikTokCatalogCsv(): string
    {
        $products = Product::with(['brand', 'variants.stocks'])
            ->where('is_published', true)
            ->get();

        $rows = [];
        $rows[] = ['sku_id', 'title', 'description', 'availability', 'price', 'product_url', 'main_image_url', 'brand'];

        foreach ($products as $product) {
            $brandName = $product->brand?->name ?: config('app.name', 'Generic');
            $imageUrl = $product->getFirstMediaUrl('gallery') ?: url('/images/product-placeholder.png');

            $variants = $product->variants->where('is_active', true);
            foreach ($variants as $variant) {
                $sku = $variant->sku ?: (string) $variant->id;
                $priceBdt = number_format((float) $variant->selling_price_bdt, 2, '.', '').' BDT';
                $productUrl = url("/products/{$product->slug}?sku={$sku}");
                $inStock = $variant->available_quantity > 0 ? 'in_stock' : 'out_of_stock';

                $title = $product->name;
                if (! empty($variant->attributes)) {
                    $attrString = implode(' / ', array_values($variant->attributes));
                    $title .= " ({$attrString})";
                }

                $rows[] = [
                    $sku,
                    $title,
                    $product->description ?: $title,
                    $inStock,
                    $priceBdt,
                    $productUrl,
                    $imageUrl,
                    $brandName,
                ];
            }
        }

        $fp = fopen('php://memory', 'r+');
        if ($fp === false) {
            return '';
        }

        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return (string) $csv;
    }
}

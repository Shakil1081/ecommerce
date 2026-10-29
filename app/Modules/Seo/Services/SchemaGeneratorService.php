<?php

declare(strict_types=1);

namespace App\Modules\Seo\Services;

class SchemaGeneratorService
{
    /**
     * Generate Schema.org Organization structured data.
     *
     * @param  array<int, string>  $sameAs
     * @param  array<string, string>  $contactPoint
     * @return array<string, mixed>
     */
    public function forOrganization(
        string $name,
        string $url,
        string $logoUrl,
        array $sameAs = [],
        array $contactPoint = []
    ): array {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $name,
            'url' => $url,
            'logo' => $logoUrl,
        ];

        if (! empty($sameAs)) {
            $schema['sameAs'] = $sameAs;
        }

        if (! empty($contactPoint)) {
            $schema['contactPoint'] = [
                '@type' => 'ContactPoint',
                'telephone' => $contactPoint['telephone'] ?? '+880-1700-000000',
                'contactType' => $contactPoint['contactType'] ?? 'customer service',
                'areaServed' => $contactPoint['areaServed'] ?? 'BD',
                'availableLanguage' => $contactPoint['availableLanguage'] ?? ['Bengali', 'English'],
            ];
        }

        return $schema;
    }

    /**
     * Generate Schema.org WebSite structured data with SearchAction for Google Sitelinks Searchbox.
     *
     * @return array<string, mixed>
     */
    public function forWebSite(string $name, string $url, ?string $searchUrlTemplate = null): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $name,
            'url' => $url,
        ];

        if ($searchUrlTemplate !== null) {
            $schema['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $searchUrlTemplate,
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $schema;
    }

    /**
     * Generate Schema.org Product structured data.
     *
     * @param  array<int, string>  $images
     * @return array<string, mixed>
     */
    public function forProduct(
        string $name,
        string $description,
        string $sku,
        array $images,
        float $price,
        string $currency = 'BDT',
        string $availability = 'https://schema.org/InStock',
        ?string $brand = null,
        ?float $ratingValue = null,
        ?int $reviewCount = null,
        ?string $url = null
    ): array {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $name,
            'image' => $images,
            'description' => $description,
            'sku' => $sku,
            'offers' => [
                '@type' => 'Offer',
                'price' => number_format($price, 2, '.', ''),
                'priceCurrency' => $currency,
                'availability' => $availability,
                'itemCondition' => 'https://schema.org/NewCondition',
                'priceValidUntil' => date('Y-12-31'),
            ],
        ];

        if ($url !== null) {
            $schema['offers']['url'] = $url;
        }

        if ($brand !== null) {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name' => $brand,
            ];
        }

        if ($ratingValue !== null && $reviewCount !== null && $reviewCount > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format($ratingValue, 1, '.', ''),
                'reviewCount' => $reviewCount,
            ];
        }

        return $schema;
    }

    /**
     * Generate Schema.org BreadcrumbList structured data.
     *
     * @param  array<int, array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    public function forBreadcrumbList(array $items): array
    {
        $itemList = [];
        $position = 1;

        foreach ($items as $item) {
            $itemList[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $item['name'],
                'item' => $item['url'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $itemList,
        ];
    }

    /**
     * Generate Schema.org FAQPage structured data.
     *
     * @param  array<int, array{question: string, answer: string}>  $qaList
     * @return array<string, mixed>
     */
    public function forFaqPage(array $qaList): array
    {
        $entities = [];

        foreach ($qaList as $qa) {
            $entities[] = [
                '@type' => 'Question',
                'name' => $qa['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $qa['answer'],
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }
}

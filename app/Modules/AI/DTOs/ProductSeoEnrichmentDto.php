<?php

declare(strict_types=1);

namespace App\Modules\AI\DTOs;

final readonly class ProductSeoEnrichmentDto
{
    /**
     * @param  array<int, string>  $keywords
     * @param  array<int, string>  $marketingBullets
     * @param  array<int, string>  $suggestedTags
     */
    public function __construct(
        public string $seoTitle,
        public string $metaDescription,
        public array $keywords,
        public array $marketingBullets,
        public string $shortDescription,
        public array $suggestedTags,
        public string $rawAiResponse = '',
    ) {}
}

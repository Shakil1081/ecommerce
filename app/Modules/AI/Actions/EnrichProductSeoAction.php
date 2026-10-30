<?php

declare(strict_types=1);

namespace App\Modules\AI\Actions;

use App\Modules\AI\DTOs\AiPromptRequestDto;
use App\Modules\AI\DTOs\ProductSeoEnrichmentDto;
use App\Modules\AI\Models\AiModelSetting;
use App\Modules\AI\Services\AiManager;
use App\Modules\Catalog\Models\Product;

class EnrichProductSeoAction
{
    public function __construct(
        protected AiManager $aiManager
    ) {}

    /**
     * Enrich a catalog product with AI-generated SEO titles, meta descriptions, and selling points.
     *
     * @param  array<string, mixed>  $options
     */
    public function __invoke(Product $product, array $options = []): ProductSeoEnrichmentDto
    {
        $product->loadMissing(['category', 'brand', 'variants']);

        $categoryName = $product->category?->name ?? 'General Merchandise';
        $brandName = $product->brand?->name ?? 'Generic';
        $specsJson = json_encode($product->specifications ?? [], JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
Product Name: {$product->name}
Brand: {$brandName}
Category: {$categoryName}
Origin Country: {$product->origin_country}
HS Code: {$product->hs_code}
Existing Specs: {$specsJson}
Additional Context: {$product->description}

Generate compelling catalog SEO content formatted as valid JSON:
{
  "seo_title": "Max 60 chars title with high purchase intent",
  "meta_description": "150-160 chars compelling meta description with call to action in BDT",
  "keywords": ["keyword 1", "keyword 2", "keyword 3", "keyword 4", "keyword 5"],
  "marketing_bullets": [
    "Feature benefit 1",
    "Feature benefit 2",
    "Feature benefit 3",
    "Feature benefit 4"
  ],
  "short_description": "2-3 engaging sales sentences highlighting value and official warranty.",
  "suggested_tags": ["tag1", "tag2", "tag3"]
}
PROMPT;

        $request = new AiPromptRequestDto(
            prompt: $prompt,
            systemPrompt: 'You are an elite e-commerce SEO catalog specialist for high-growth online retailers in Bangladesh. Always return ONLY valid JSON without markdown code fences or conversational text.',
            feature: AiModelSetting::FEATURE_CATALOG_ENRICHMENT,
            temperature: 0.4,
            maxTokens: 1024,
            userId: $options['user_id'] ?? null
        );

        $response = $this->aiManager->prompt($request);
        $cleanJson = $this->cleanJsonString($response->content);

        $parsed = json_decode($cleanJson, true) ?? [];

        $seoTitle = (string) ($parsed['seo_title'] ?? $product->name.' | Best Price in Bangladesh');
        $metaDescription = (string) ($parsed['meta_description'] ?? 'Buy authentic '.$product->name.' in Bangladesh. Best market price with fast home delivery.');
        $keywords = is_array($parsed['keywords'] ?? null) ? array_values(array_map('strval', $parsed['keywords'])) : [];
        $marketingBullets = is_array($parsed['marketing_bullets'] ?? null) ? array_values(array_map('strval', $parsed['marketing_bullets'])) : [];
        $shortDescription = (string) ($parsed['short_description'] ?? $metaDescription);
        $suggestedTags = is_array($parsed['suggested_tags'] ?? null) ? array_values(array_map('strval', $parsed['suggested_tags'])) : [];

        if (! empty($options['persist'])) {
            $product->seo_title = $seoTitle;
            $product->seo_description = $metaDescription;
            $product->save();
        }

        return new ProductSeoEnrichmentDto(
            seoTitle: $seoTitle,
            metaDescription: $metaDescription,
            keywords: $keywords,
            marketingBullets: $marketingBullets,
            shortDescription: $shortDescription,
            suggestedTags: $suggestedTags,
            rawAiResponse: $response->content
        );
    }

    /**
     * Strip markdown code blocks if the LLM wrapped response in ```json ... ```.
     */
    private function cleanJsonString(string $raw): string
    {
        $trimmed = trim($raw);
        if (str_starts_with($trimmed, '```json')) {
            $trimmed = substr($trimmed, 7);
        } elseif (str_starts_with($trimmed, '```')) {
            $trimmed = substr($trimmed, 3);
        }

        if (str_ends_with($trimmed, '```')) {
            $trimmed = substr($trimmed, 0, -3);
        }

        return trim($trimmed);
    }
}

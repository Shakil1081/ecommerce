<?php

declare(strict_types=1);

namespace App\Modules\Seo\DTOs;

readonly class MetaTagData
{
    /**
     * @param  array<int, array<string, mixed>>  $jsonLdSchemas
     */
    public function __construct(
        public string $title,
        public string $description,
        public ?string $canonicalUrl = null,
        public string $ogType = 'website',
        public ?string $ogImage = null,
        public string $ogLocale = 'en_BD',
        public string $twitterCard = 'summary_large_image',
        public array $jsonLdSchemas = [],
        public ?string $keywords = null,
        public string $robots = 'index, follow',
    ) {}

    /**
     * Convert DTO into array for Inertia props.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonicalUrl' => $this->canonicalUrl,
            'ogType' => $this->ogType,
            'ogImage' => $this->ogImage,
            'ogLocale' => $this->ogLocale,
            'twitterCard' => $this->twitterCard,
            'jsonLd' => $this->renderJsonLd(),
            'keywords' => $this->keywords,
            'robots' => $this->robots,
        ];
    }

    /**
     * Render all attached JSON-LD schemas as HTML script blocks.
     */
    public function renderJsonLd(): string
    {
        if (empty($this->jsonLdSchemas)) {
            return '';
        }

        $scripts = [];
        foreach ($this->jsonLdSchemas as $schema) {
            $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $scripts[] = '<script type="application/ld+json">'."\n".$json."\n".'</script>';
        }

        return implode("\n", $scripts);
    }
}

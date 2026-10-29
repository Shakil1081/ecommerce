<?php

declare(strict_types=1);

namespace App\Modules\Common\Models;

use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

/**
 * Enterprise Media Model with responsive breakpoint utilities,
 * blurhash placeholders for zero CLS (Cumulative Layout Shift),
 * and WebP/AVIF asset resolution.
 */
class Media extends BaseMedia
{
    /**
     * Get the stored blurhash placeholder string.
     */
    public function getBlurhashAttribute(): ?string
    {
        return $this->getCustomProperty('blurhash');
    }

    /**
     * Get the stored LQIP (Low-Quality Image Placeholder) base64 data URI.
     */
    public function getLqipAttribute(): ?string
    {
        return $this->getCustomProperty('lqip');
    }

    /**
     * Resolve all responsive breakpoint URLs for this media.
     *
     * @return array<string, string>
     */
    public function getBreakpoints(): array
    {
        $conversions = [
            'thumb_300',
            'thumb_300_webp',
            'card_600',
            'card_600_webp',
            'gallery_1200',
            'gallery_1200_webp',
            'zoom_2000',
            'zoom_2000_webp',
        ];

        $urls = [];
        foreach ($conversions as $conversion) {
            if ($this->hasGeneratedConversion($conversion)) {
                $urls[$conversion] = $this->getUrl($conversion);
            }
        }

        return $urls;
    }

    /**
     * Format into an enterprise JSON-serializable array for Vue/Inertia components.
     *
     * @return array<string, mixed>
     */
    public function toEnterpriseArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'original_url' => $this->getUrl(),
            'blurhash' => $this->blurhash,
            'lqip' => $this->lqip,
            'breakpoints' => $this->getBreakpoints(),
            'custom_properties' => $this->custom_properties,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

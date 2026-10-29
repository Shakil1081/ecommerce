<?php

declare(strict_types=1);

namespace App\Modules\Common\Traits;

use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Trait providing standardized responsive image breakpoints and WebP conversions
 * across catalog products, category banners, brand logos, and trade documents.
 */
trait HasStandardMediaConversions
{
    use InteractsWithMedia;

    /**
     * Register standard responsive breakpoint conversions.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $shouldQueue = (bool) config('media-library.queue_conversions_by_default', true);

        // Thumbnail: 300x300 (Cart, lists, autocomplete)
        $thumb = $this->addMediaConversion('thumb_300')
            ->width(300)
            ->height(300)
            ->sharpen(10);
        if (! $shouldQueue) {
            $thumb->nonQueued();
        }

        $thumbWebp = $this->addMediaConversion('thumb_300_webp')
            ->width(300)
            ->height(300)
            ->format('webp');
        if (! $shouldQueue) {
            $thumbWebp->nonQueued();
        }

        // Card: 600x600 (Catalog grid cards)
        $card = $this->addMediaConversion('card_600')
            ->width(600)
            ->height(600);
        if (! $shouldQueue) {
            $card->nonQueued();
        }

        $cardWebp = $this->addMediaConversion('card_600_webp')
            ->width(600)
            ->height(600)
            ->format('webp');
        if (! $shouldQueue) {
            $cardWebp->nonQueued();
        }

        // Gallery: 1200x1200 (Product detail page main gallery)
        $gallery = $this->addMediaConversion('gallery_1200')
            ->width(1200)
            ->height(1200);
        if (! $shouldQueue) {
            $gallery->nonQueued();
        }

        $galleryWebp = $this->addMediaConversion('gallery_1200_webp')
            ->width(1200)
            ->height(1200)
            ->format('webp');
        if (! $shouldQueue) {
            $galleryWebp->nonQueued();
        }

        // Zoom: 2000x2000 (Pinch / zoom / lens modal)
        $zoom = $this->addMediaConversion('zoom_2000')
            ->width(2000)
            ->height(2000);
        if (! $shouldQueue) {
            $zoom->nonQueued();
        }

        $zoomWebp = $this->addMediaConversion('zoom_2000_webp')
            ->width(2000)
            ->height(2000)
            ->format('webp');
        if (! $shouldQueue) {
            $zoomWebp->nonQueued();
        }
    }
}

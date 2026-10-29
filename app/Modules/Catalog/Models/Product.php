<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Common\Traits\HasStandardMediaConversions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

/**
 * Enterprise Matrix Product Model.
 */
class Product extends Model implements HasMedia
{
    use HasStandardMediaConversions, HasUlids;

    protected $table = 'products';

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'hs_code',
        'origin_country',
        'warranty_policy',
        'description',
        'specifications',
        'is_featured',
        'is_published',
        'seo_title',
        'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'specifications' => 'array',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    /**
     * Product category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Product brand / manufacturer.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * All SKU variants for this matrix product.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    /**
     * Primary or lowest price variant.
     */
    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class, 'product_id')->oldestOfMany();
    }

    /**
     * All customer reviews for this product.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'product_id');
    }

    /**
     * Approved reviews visible to shoppers.
     */
    public function approvedReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'product_id')->where('status', ProductReview::STATUS_APPROVED);
    }

    /**
     * Average star rating of approved reviews.
     */
    public function getAverageRatingAttribute(): float
    {
        $avg = $this->approvedReviews()->avg('rating');

        return $avg !== null ? round((float) $avg, 1) : 0.0;
    }

    /**
     * Total count of approved reviews.
     */
    public function getReviewCountAttribute(): int
    {
        return $this->approvedReviews()->count();
    }

    /**
     * Minimum selling price across all active variants.
     */
    public function getMinPriceBdtAttribute(): float
    {
        return (float) ($this->variants()->where('is_active', true)->min('selling_price_bdt') ?? 0);
    }

    /**
     * Maximum selling price across all active variants.
     */
    public function getMaxPriceBdtAttribute(): float
    {
        return (float) ($this->variants()->where('is_active', true)->max('selling_price_bdt') ?? 0);
    }

    /**
     * Scope published products.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope featured products.
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Register Spatie Media Collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('thumbnail')
            ->singleFile()
            ->useDisk((string) config('media-library.disk_name', 'media'));

        $this->addMediaCollection('images')
            ->useDisk((string) config('media-library.disk_name', 'media'));

        $this->addMediaCollection('documents')
            ->useDisk((string) config('media-library.disk_name', 'media'));
    }
}

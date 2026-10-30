<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Common\Traits\HasStandardMediaConversions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

/**
 * Manufacturer / Sourcing Brand Model.
 */
class Brand extends Model implements HasMedia
{
    use HasStandardMediaConversions;

    protected $table = 'brands';

    protected $fillable = [
        'name',
        'slug',
        'origin_country',
        'website_url',
        'description',
        'is_featured',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Brand $brand) {
            if (empty($brand->slug)) {
                $brand->slug = Str::slug($brand->name);
            }
        });
    }

    /**
     * Scope active brands.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope featured brands.
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Products manufactured or sourced under this brand.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_id');
    }

    /**
     * Register Spatie Media Collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->useDisk((string) config('media-library.disk_name', 'media'));

        $this->addMediaCollection('banner')
            ->singleFile()
            ->useDisk((string) config('media-library.disk_name', 'media'));
    }
}

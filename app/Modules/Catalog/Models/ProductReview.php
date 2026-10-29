<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Models\User;
use App\Modules\Common\Traits\HasStandardMediaConversions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;

/**
 * Verified Customer Product Review Model with Photo Uploads.
 */
class ProductReview extends Model implements HasMedia
{
    use HasStandardMediaConversions, HasUlids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'product_reviews';

    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'customer_name',
        'customer_email',
        'rating',
        'title',
        'review_text',
        'is_verified_purchase',
        'status',
        'moderated_by_user_id',
        'moderated_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_verified_purchase' => 'boolean',
            'moderated_at' => 'datetime',
        ];
    }

    /**
     * Parent product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Reviewing customer user account (if logged in).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Staff moderator who reviewed this entry.
     */
    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by_user_id');
    }

    /**
     * Scope approved reviews visible to public storefront shoppers.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope pending reviews waiting in the moderation queue.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Register Spatie Media Collections for review customer photography.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('review_photos')
            ->useDisk((string) config('media-library.disk_name', 'media'));
    }
}

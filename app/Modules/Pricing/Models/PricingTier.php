<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wholesale & Volume Tier Pricing Model.
 */
class PricingTier extends Model
{
    public const GROUP_RETAIL = 'retail';

    public const GROUP_WHOLESALE = 'wholesale_b2b';

    public const GROUP_VIP = 'corporate_vip';

    protected $table = 'pricing_tiers';

    protected $fillable = [
        'product_variant_id',
        'customer_group',
        'min_quantity',
        'tier_price_bdt',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_quantity' => 'integer',
            'tier_price_bdt' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Parent product variant.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Scope active pricing tiers.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope tiers for a specific customer group.
     */
    public function scopeForGroup(Builder $query, string $group): Builder
    {
        return $query->where('customer_group', $group);
    }

    /**
     * Scope wholesale B2B tiers.
     */
    public function scopeWholesale(Builder $query): Builder
    {
        return $query->where('customer_group', self::GROUP_WHOLESALE);
    }
}

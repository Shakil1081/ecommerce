<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Product Variant SKU Model.
 */
class ProductVariant extends Model
{
    use HasUlids;

    protected $table = 'product_variants';

    protected $fillable = [
        'product_id',
        'sku',
        'barcode_ean',
        'attributes',
        'purchase_cost_bdt',
        'selling_price_bdt',
        'compare_at_price_bdt',
        'weight_kg',
        'low_stock_threshold',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'purchase_cost_bdt' => 'decimal:4',
            'selling_price_bdt' => 'decimal:4',
            'compare_at_price_bdt' => 'decimal:4',
            'weight_kg' => 'decimal:3',
            'low_stock_threshold' => 'integer',
            'is_active' => 'boolean',
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
     * Formatted display name incorporating matrix attributes.
     */
    public function getDisplayNameAttribute(): string
    {
        $matrixAttrs = $this->getAttribute('attributes');
        $attrSummary = collect(is_array($matrixAttrs) ? $matrixAttrs : [])
            ->map(fn ($val, $key) => "{$key}: {$val}")
            ->join(', ');

        $productName = $this->product?->name ?? 'Product';

        return empty($attrSummary) ? $productName : "{$productName} ({$attrSummary})";
    }

    /**
     * Scope active variants.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

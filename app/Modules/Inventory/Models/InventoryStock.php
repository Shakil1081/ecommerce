<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Multi-Warehouse Physical & Reserved Inventory Stock.
 */
class InventoryStock extends Model
{
    protected $table = 'inventory_stocks';

    protected $fillable = [
        'warehouse_id',
        'product_variant_id',
        'physical_quantity',
        'reserved_quantity',
        'bin_location',
    ];

    protected function casts(): array
    {
        return [
            'physical_quantity' => 'integer',
            'reserved_quantity' => 'integer',
        ];
    }

    /**
     * Parent warehouse depot.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * Associated product variant.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Available stock on-hand for new orders (Physical minus Reserved).
     */
    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->physical_quantity - $this->reserved_quantity);
    }

    /**
     * Determine if stock has hit or fallen below low stock reorder point.
     */
    public function getIsLowStockAttribute(): bool
    {
        $threshold = $this->variant?->low_stock_threshold ?? 5;

        return $this->available_quantity <= $threshold;
    }

    /**
     * Scope stock with available quantity greater than zero.
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereRaw('physical_quantity > reserved_quantity');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Warehouse / Fulfillment Depot Hub Model.
 */
class Warehouse extends Model
{
    protected $table = 'warehouses';

    protected $fillable = [
        'name',
        'code',
        'address',
        'contact_number',
        'is_primary',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Stock records in this warehouse.
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class, 'warehouse_id');
    }

    /**
     * Product variants stocked in this warehouse.
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'inventory_stocks',
            'warehouse_id',
            'product_variant_id'
        )->withPivot(['physical_quantity', 'reserved_quantity', 'bin_location']);
    }

    /**
     * Scope active warehouses.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope primary central warehouse.
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }
}


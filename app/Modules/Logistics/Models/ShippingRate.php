<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'zone_id',
        'courier_code',
        'base_weight_kg',
        'base_rate_bdt',
        'extra_rate_per_kg_bdt',
        'free_shipping_threshold_bdt',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'base_weight_kg' => 'decimal:3',
        'base_rate_bdt' => 'decimal:4',
        'extra_rate_per_kg_bdt' => 'decimal:4',
        'free_shipping_threshold_bdt' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'zone_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForCourier(Builder $query, ?string $courierCode = null): Builder
    {
        if ($courierCode === null) {
            return $query->whereNull('courier_code');
        }

        return $query->where(function (Builder $q) use ($courierCode): void {
            $q->where('courier_code', $courierCode)
                ->orWhereNull('courier_code');
        });
    }
}

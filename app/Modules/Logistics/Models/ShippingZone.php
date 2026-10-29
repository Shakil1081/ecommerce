<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Models;

use App\Modules\Common\Models\BangladeshDistrict;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShippingZone extends Model
{
    public const CODE_INSIDE_DHAKA = 'inside_dhaka';

    public const CODE_DHAKA_SUBURBS = 'dhaka_suburbs';

    public const CODE_OUTSIDE_DHAKA = 'outside_dhaka';

    public const DEFAULT_ZONES = [
        self::CODE_INSIDE_DHAKA => [
            'name' => 'Inside Dhaka (ঢাকা সিটি)',
            'description' => 'Dhaka Metropolitan City delivery within 24-48 hours',
            'days_min' => 1,
            'days_max' => 2,
            'default_rate' => '70.0000',
            'extra_rate' => '20.0000',
        ],
        self::CODE_DHAKA_SUBURBS => [
            'name' => 'Dhaka Suburbs (ঢাকা উপশহর)',
            'description' => 'Savar, Gazipur, Keraniganj, Narayanganj sub-districts within 48-72 hours',
            'days_min' => 2,
            'days_max' => 3,
            'default_rate' => '100.0000',
            'extra_rate' => '25.0000',
        ],
        self::CODE_OUTSIDE_DHAKA => [
            'name' => 'Outside Dhaka (ঢাকার বাইরে সারা বাংলাদেশ)',
            'description' => 'Nationwide divisional and district delivery within 3-5 days',
            'days_min' => 3,
            'days_max' => 5,
            'default_rate' => '130.0000',
            'extra_rate' => '30.0000',
        ],
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'sort_order',
        'estimated_days_min',
        'estimated_days_max',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'estimated_days_min' => 'integer',
        'estimated_days_max' => 'integer',
    ];

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class, 'zone_id');
    }

    public function defaultRate(): HasOne
    {
        return $this->hasOne(ShippingRate::class, 'zone_id')->whereNull('courier_code');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * Resolve the ShippingZone matching a Bangladesh District's zone_type.
     */
    public static function resolveForDistrict(?BangladeshDistrict $district): ?self
    {
        $zoneCode = $district ? $district->zone_type : self::CODE_OUTSIDE_DHAKA;

        $zone = self::where('code', $zoneCode)->where('is_active', true)->first();

        if (! $zone && $zoneCode !== self::CODE_OUTSIDE_DHAKA) {
            $zone = self::where('code', self::CODE_OUTSIDE_DHAKA)->where('is_active', true)->first();
        }

        return $zone;
    }
}

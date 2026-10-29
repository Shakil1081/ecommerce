<?php

declare(strict_types=1);

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bangladesh Administrative District (64 Districts) with Courier Logistics Zone Classification.
 */
class BangladeshDistrict extends Model
{
    public const ZONE_INSIDE_DHAKA = 'inside_dhaka';

    public const ZONE_DHAKA_SUBURBS = 'dhaka_suburbs';

    public const ZONE_OUTSIDE_DHAKA = 'outside_dhaka';

    protected $table = 'bangladesh_districts';

    protected $fillable = [
        'division_id',
        'name_en',
        'name_bn',
        'zone_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Parent division.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(BangladeshDivision::class, 'division_id');
    }

    /**
     * Thanas / Upazilas in this district.
     */
    public function thanas(): HasMany
    {
        return $this->hasMany(BangladeshThana::class, 'district_id');
    }

    /**
     * Scope active districts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope Inside Dhaka delivery zone.
     */
    public function scopeInsideDhaka(Builder $query): Builder
    {
        return $query->where('zone_type', self::ZONE_INSIDE_DHAKA);
    }

    /**
     * Scope Dhaka Suburbs delivery zone.
     */
    public function scopeDhakaSuburbs(Builder $query): Builder
    {
        return $query->where('zone_type', self::ZONE_DHAKA_SUBURBS);
    }

    /**
     * Scope Outside Dhaka delivery zone.
     */
    public function scopeOutsideDhaka(Builder $query): Builder
    {
        return $query->where('zone_type', self::ZONE_OUTSIDE_DHAKA);
    }
}

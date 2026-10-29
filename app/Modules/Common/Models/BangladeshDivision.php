<?php

declare(strict_types=1);

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Bangladesh Administrative Division (8 Divisions).
 */
class BangladeshDivision extends Model
{
    protected $table = 'bangladesh_divisions';

    protected $fillable = [
        'name_en',
        'name_bn',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Districts belonging to this division.
     */
    public function districts(): HasMany
    {
        return $this->hasMany(BangladeshDistrict::class, 'division_id');
    }

    /**
     * Thanas belonging to this division through districts.
     */
    public function thanas(): HasManyThrough
    {
        return $this->hasManyThrough(BangladeshThana::class, BangladeshDistrict::class, 'division_id', 'district_id');
    }

    /**
     * Scope only active divisions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

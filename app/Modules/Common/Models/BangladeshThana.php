<?php

declare(strict_types=1);

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bangladesh Thana / Upazila.
 */
class BangladeshThana extends Model
{
    protected $table = 'bangladesh_thanas';

    protected $fillable = [
        'district_id',
        'name_en',
        'name_bn',
        'post_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Parent district.
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(BangladeshDistrict::class, 'district_id');
    }

    /**
     * Scope active thanas.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}


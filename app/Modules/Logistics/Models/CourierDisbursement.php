<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CourierDisbursement extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RECONCILED = 'reconciled';

    public const STATUS_DISCREPANCY = 'discrepancy';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'courier_code',
        'reference_invoice',
        'disbursement_date',
        'total_collected_bdt',
        'total_courier_fee_bdt',
        'net_disbursed_bdt',
        'status',
        'notes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'disbursement_date' => 'date',
        'total_collected_bdt' => 'decimal:4',
        'total_courier_fee_bdt' => 'decimal:4',
        'net_disbursed_bdt' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::creating(function (CourierDisbursement $model): void {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(CourierDisbursementItem::class, 'disbursement_id');
    }

    public function scopeReconciled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RECONCILED);
    }

    public function scopeDiscrepancy(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DISCREPANCY);
    }
}

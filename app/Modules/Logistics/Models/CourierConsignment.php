<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Models;

use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CourierConsignment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_BOOKED = 'booked';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_PARTIAL_DELIVERED = 'partial_delivered';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'order_id',
        'courier_code',
        'consignment_id',
        'tracking_code',
        'status',
        'delivery_zone',
        'delivery_fee_bdt',
        'cod_amount_bdt',
        'recipient_name',
        'recipient_phone',
        'recipient_address',
        'courier_notes',
        'booking_response_payload',
        'last_webhook_payload',
        'dispatched_at',
        'delivered_at',
        'returned_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'delivery_fee_bdt' => 'decimal:4',
        'cod_amount_bdt' => 'decimal:4',
        'booking_response_payload' => 'array',
        'last_webhook_payload' => 'array',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CourierConsignment $model): void {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeActiveTracking(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_BOOKED,
            self::STATUS_IN_TRANSIT,
            self::STATUS_OUT_FOR_DELIVERY,
        ]);
    }

    public function scopeCourier(Builder $query, string $courierCode): Builder
    {
        return $query->where('courier_code', $courierCode);
    }
}

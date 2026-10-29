<?php

declare(strict_types=1);

namespace App\Modules\Payment\Models;

use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentTransaction extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESSFUL = 'successful';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_CANCELLED = 'cancelled';

    public const PURPOSE_ORDER_FULL = 'order_full';

    public const PURPOSE_ADVANCE_DELIVERY_CHARGE = 'cod_advance_delivery_charge';

    public const PURPOSE_PARTIAL = 'partial_payment';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'order_id',
        'gateway_code',
        'transaction_id',
        'payment_purpose',
        'amount_bdt',
        'gateway_fee_bdt',
        'currency',
        'status',
        'customer_account',
        'raw_request_payload',
        'raw_response_payload',
        'error_message',
        'idempotency_key',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'amount_bdt' => 'decimal:4',
        'gateway_fee_bdt' => 'decimal:4',
        'raw_request_payload' => 'array',
        'raw_response_payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (PaymentTransaction $model): void {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    /**
     * Associated customer order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope to successful transactions.
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUCCESSFUL);
    }

    /**
     * Scope to transactions for a given order.
     */
    public function scopeForOrder(Builder $query, int $orderId): Builder
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Whether the transaction succeeded.
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESSFUL;
    }
}

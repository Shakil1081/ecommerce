<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Models;

use App\Modules\Cart\Models\Cart;
use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CartRecoveryLog extends Model
{
    public const STEP_ONE = 1;

    public const STEP_TWO = 2;

    public const STEP_THREE = 3;

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_EMAIL = 'email';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RECOVERED = 'recovered';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'cart_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'step',
        'channel',
        'status',
        'incentive_coupon_code',
        'recovered_order_id',
        'dispatched_at',
        'recovered_at',
        'message_content',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'step' => 'integer',
        'dispatched_at' => 'datetime',
        'recovered_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CartRecoveryLog $model): void {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'recovered_order_id');
    }

    public function scopeRecovered(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RECOVERED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }
}

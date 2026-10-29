<?php

declare(strict_types=1);

namespace App\Modules\Order\Models;

use App\Models\User;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Common\Models\BangladeshThana;
use App\Modules\Logistics\Models\CourierConsignment;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\TaxCompliance\Models\Mushak63Invoice;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property string $order_number
 * @property int|null $user_id
 * @property string|null $guest_token
 * @property string $status
 * @property string $payment_status
 * @property string $payment_method
 * @property string $customer_name
 * @property string $customer_phone
 * @property string|null $customer_email
 * @property string|null $ip_address
 * @property string|null $device_fingerprint
 * @property string|null $user_agent
 * @property int $division_id
 * @property int $district_id
 * @property int|null $thana_id
 * @property string $delivery_zone
 * @property string $delivery_address
 * @property string|null $customer_notes
 * @property string $subtotal_bdt
 * @property string $discount_amount_bdt
 * @property string $shipping_fee_bdt
 * @property string $total_bdt
 * @property string $advance_paid_bdt
 * @property string $payable_cod_amount_bdt
 * @property string|null $coupon_code
 * @property int $fraud_risk_score
 * @property string $fraud_risk_level
 * @property string|null $courier_code
 * @property string|null $courier_consignment_id
 * @property string|null $courier_tracking_code
 * @property string|null $mushak_6_3_number
 * @property CarbonInterface|null $confirmed_at
 * @property CarbonInterface|null $shipped_at
 * @property CarbonInterface|null $delivered_at
 * @property CarbonInterface|null $cancelled_at
 * @property Collection<int, OrderItem> $items
 * @property Collection<int, OrderStatusHistory> $statusHistories
 * @property User|null $user
 * @property BangladeshDivision $division
 * @property BangladeshDistrict $district
 * @property BangladeshThana|null $thana
 */
class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PICKED_AND_PACKED = 'picked_and_packed';

    public const STATUS_HANDED_TO_COURIER = 'handed_to_courier';

    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_PARTIAL_ADVANCE = 'partial_advance';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_REFUNDED = 'refunded';

    public const PAYMENT_FAILED = 'failed';

    public const ALLOWED_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
        self::STATUS_PROCESSING => [self::STATUS_PICKED_AND_PACKED, self::STATUS_CANCELLED],
        self::STATUS_PICKED_AND_PACKED => [self::STATUS_HANDED_TO_COURIER, self::STATUS_CANCELLED],
        self::STATUS_HANDED_TO_COURIER => [self::STATUS_OUT_FOR_DELIVERY, self::STATUS_RETURNED, self::STATUS_DELIVERED],
        self::STATUS_OUT_FOR_DELIVERY => [self::STATUS_DELIVERED, self::STATUS_RETURNED],
        self::STATUS_DELIVERED => [self::STATUS_RETURNED],
        self::STATUS_RETURNED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'ulid',
        'order_number',
        'user_id',
        'guest_token',
        'status',
        'payment_status',
        'payment_method',
        'customer_name',
        'customer_phone',
        'customer_email',
        'ip_address',
        'device_fingerprint',
        'user_agent',
        'division_id',
        'district_id',
        'thana_id',
        'delivery_zone',
        'delivery_address',
        'customer_notes',
        'subtotal_bdt',
        'discount_amount_bdt',
        'shipping_fee_bdt',
        'total_bdt',
        'advance_paid_bdt',
        'payable_cod_amount_bdt',
        'coupon_code',
        'fraud_risk_score',
        'fraud_risk_level',
        'courier_code',
        'courier_consignment_id',
        'courier_tracking_code',
        'mushak_6_3_number',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'payment_status' => self::PAYMENT_PENDING,
        'payment_method' => 'cash_on_delivery',
        'delivery_zone' => 'inside_dhaka',
        'subtotal_bdt' => '0.0000',
        'discount_amount_bdt' => '0.0000',
        'shipping_fee_bdt' => '0.0000',
        'total_bdt' => '0.0000',
        'advance_paid_bdt' => '0.0000',
        'payable_cod_amount_bdt' => '0.0000',
        'fraud_risk_score' => 0,
        'fraud_risk_level' => 'low',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_bdt' => 'decimal:4',
            'discount_amount_bdt' => 'decimal:4',
            'shipping_fee_bdt' => 'decimal:4',
            'total_bdt' => 'decimal:4',
            'advance_paid_bdt' => 'decimal:4',
            'payable_cod_amount_bdt' => 'decimal:4',
            'fraud_risk_score' => 'integer',
            'confirmed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (empty($order->ulid)) {
                $order->ulid = (string) Str::ulid();
            }
        });
    }

    public function canTransitionTo(string $targetStatus): bool
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status] ?? [];

        return in_array($targetStatus, $allowed, true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id')->orderBy('created_at', 'desc');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'order_id')->orderBy('created_at', 'desc');
    }

    public function consignments(): HasMany
    {
        return $this->hasMany(CourierConsignment::class, 'order_id')->orderBy('created_at', 'desc');
    }

    public function latestConsignment(): HasOne
    {
        return $this->hasOne(CourierConsignment::class, 'order_id')->latestOfMany();
    }

    public function mushak63Invoice(): HasOne
    {
        return $this->hasOne(Mushak63Invoice::class, 'order_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(BangladeshDivision::class, 'division_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(BangladeshDistrict::class, 'district_id');
    }

    public function thana(): BelongsTo
    {
        return $this->belongsTo(BangladeshThana::class, 'thana_id');
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }
}

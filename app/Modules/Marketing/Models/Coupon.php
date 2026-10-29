<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $discount_type
 * @property string $discount_value
 * @property string $min_order_amount_bdt
 * @property string|null $max_discount_amount_bdt
 * @property int|null $usage_limit_total
 * @property int $usage_limit_per_user
 * @property int $total_used_count
 * @property bool $is_active
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $expires_at
 * @property Collection<int, CouponUsage> $usages
 */
class Coupon extends Model
{
    use HasFactory;

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed_amount';

    public const TYPE_FREE_SHIPPING = 'free_shipping';

    protected $fillable = [
        'ulid',
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'min_order_amount_bdt',
        'max_discount_amount_bdt',
        'usage_limit_total',
        'usage_limit_per_user',
        'total_used_count',
        'is_active',
        'starts_at',
        'expires_at',
    ];

    protected $attributes = [
        'discount_type' => self::TYPE_PERCENTAGE,
        'discount_value' => '0.0000',
        'min_order_amount_bdt' => '0.0000',
        'usage_limit_per_user' => 1,
        'total_used_count' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:4',
            'min_order_amount_bdt' => 'decimal:4',
            'max_discount_amount_bdt' => 'decimal:4',
            'usage_limit_total' => 'integer',
            'usage_limit_per_user' => 'integer',
            'total_used_count' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Coupon $coupon): void {
            if (empty($coupon->ulid)) {
                $coupon->ulid = (string) Str::ulid();
            }
            $coupon->code = strtoupper(trim($coupon->code));
        });

        static::updating(function (Coupon $coupon): void {
            $coupon->code = strtoupper(trim($coupon->code));
        });
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class, 'coupon_id');
    }

    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = Carbon::now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && $now->gt($this->expires_at)) {
            return false;
        }

        if ($this->usage_limit_total !== null && $this->total_used_count >= $this->usage_limit_total) {
            return false;
        }

        return true;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $sub): void {
                $sub->whereNull('starts_at')->orWhere('starts_at', '<=', Carbon::now());
            })
            ->where(function (Builder $sub): void {
                $sub->whereNull('expires_at')->orWhere('expires_at', '>=', Carbon::now());
            });
    }
}

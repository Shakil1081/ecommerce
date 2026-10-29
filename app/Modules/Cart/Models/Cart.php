<?php

declare(strict_types=1);

namespace App\Modules\Cart\Models;

use App\Models\User;
use App\Modules\Marketing\Models\CartRecoveryLog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int|null $user_id
 * @property string|null $guest_token
 * @property string $status
 * @property string|null $coupon_code
 * @property string $subtotal_bdt
 * @property string $discount_amount_bdt
 * @property string $total_bdt
 * @property int $total_items_count
 * @property CarbonInterface|null $last_activity_at
 * @property User|null $user
 * @property Collection<int, CartItem> $items
 */
class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'ulid',
        'user_id',
        'guest_token',
        'customer_name',
        'customer_phone',
        'customer_email',
        'status',
        'coupon_code',
        'subtotal_bdt',
        'discount_amount_bdt',
        'total_bdt',
        'total_items_count',
        'last_activity_at',
    ];

    protected $attributes = [
        'status' => 'active',
        'subtotal_bdt' => '0.0000',
        'discount_amount_bdt' => '0.0000',
        'total_bdt' => '0.0000',
        'total_items_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'subtotal_bdt' => 'decimal:4',
            'discount_amount_bdt' => 'decimal:4',
            'total_bdt' => 'decimal:4',
            'total_items_count' => 'integer',
            'last_activity_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Cart $cart): void {
            if (empty($cart->ulid)) {
                $cart->ulid = (string) Str::ulid();
            }
            if (empty($cart->last_activity_at)) {
                $cart->last_activity_at = Carbon::now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class, 'cart_id');
    }

    public function recoveryLogs(): HasMany
    {
        return $this->hasMany(CartRecoveryLog::class, 'cart_id');
    }

    public function touchActivity(): void
    {
        $this->update(['last_activity_at' => Carbon::now()]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeAbandoned(Builder $query): Builder
    {
        return $query->where('status', 'abandoned');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $coupon_id
 * @property int|null $user_id
 * @property int|null $order_id
 * @property string|null $guest_token
 * @property string $discount_amount_bdt
 * @property CarbonInterface $redeemed_at
 * @property Coupon $coupon
 * @property User|null $user
 */
class CouponUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_id',
        'user_id',
        'order_id',
        'guest_token',
        'discount_amount_bdt',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount_bdt' => 'decimal:4',
            'redeemed_at' => 'datetime',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

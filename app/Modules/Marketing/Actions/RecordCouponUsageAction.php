<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Actions;

use App\Models\User;
use App\Modules\Marketing\Models\Coupon;
use App\Modules\Marketing\Models\CouponUsage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class RecordCouponUsageAction
{
    /**
     * Record a coupon redemption and atomically increment usage count.
     */
    public function __invoke(
        Coupon $coupon,
        ?User $user = null,
        ?string $guestToken = null,
        ?int $orderId = null,
        string $discountAmountBdt = '0.0000'
    ): CouponUsage {
        return DB::transaction(function () use ($coupon, $user, $guestToken, $orderId, $discountAmountBdt): CouponUsage {
            /** @var CouponUsage $usage */
            $usage = CouponUsage::create([
                'coupon_id' => $coupon->id,
                'user_id' => $user?->id,
                'order_id' => $orderId,
                'guest_token' => $guestToken,
                'discount_amount_bdt' => $discountAmountBdt,
                'redeemed_at' => Carbon::now(),
            ]);

            $coupon->increment('total_used_count');

            return $usage;
        });
    }
}

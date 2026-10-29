<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Actions;

use App\Models\User;
use App\Modules\Cart\Models\Cart;
use App\Modules\Marketing\Models\Coupon;
use App\Modules\Marketing\Models\CouponUsage;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class ApplyCouponToCartAction
{
    /**
     * Validate and apply a promotional coupon code to the active cart.
     *
     * @throws ValidationException
     */
    public function __invoke(
        Cart $cart,
        string $code,
        ?User $user = null,
        ?string $guestToken = null
    ): Cart {
        $normalizedCode = strtoupper(trim($code));

        /** @var Coupon|null $coupon */
        $coupon = Coupon::query()->where('code', $normalizedCode)->first();

        if (! $coupon) {
            throw ValidationException::withMessages([
                'code' => ['The promotional code you entered is invalid.'],
            ]);
        }

        if (! $coupon->is_active) {
            throw ValidationException::withMessages([
                'code' => ['This promotional coupon is currently inactive.'],
            ]);
        }

        $now = Carbon::now();

        if ($coupon->starts_at && $now->lt($coupon->starts_at)) {
            throw ValidationException::withMessages([
                'code' => ['This promotional coupon is not active yet.'],
            ]);
        }

        if ($coupon->expires_at && $now->gt($coupon->expires_at)) {
            throw ValidationException::withMessages([
                'code' => ['This promotional coupon has expired.'],
            ]);
        }

        if ($coupon->usage_limit_total !== null && $coupon->total_used_count >= $coupon->usage_limit_total) {
            throw ValidationException::withMessages([
                'code' => ['This promotional coupon has reached its maximum redemption limit.'],
            ]);
        }

        // Check per-user / per-customer usage
        $usageQuery = CouponUsage::query()->where('coupon_id', $coupon->id);
        if ($user) {
            $usageQuery->where('user_id', $user->id);
        } else {
            $token = $guestToken ?: $cart->guest_token;
            if ($token) {
                $usageQuery->where('guest_token', $token);
            }
        }

        if ($usageQuery->count() >= $coupon->usage_limit_per_user) {
            throw ValidationException::withMessages([
                'code' => ['You have already reached the maximum redemption limit for this coupon.'],
            ]);
        }

        // Validate minimum subtotal requirement
        $subtotal = (string) $cart->subtotal_bdt;
        if (bccomp($subtotal, (string) $coupon->min_order_amount_bdt, 4) < 0) {
            $minFormatted = number_format((float) $coupon->min_order_amount_bdt, 2);
            throw ValidationException::withMessages([
                'code' => ["This coupon requires a minimum cart subtotal of ৳{$minFormatted}."],
            ]);
        }

        // Compute discount amount
        $discount = '0.0000';

        if ($coupon->discount_type === Coupon::TYPE_PERCENTAGE) {
            $calculated = bcdiv(bcmul($subtotal, (string) $coupon->discount_value, 6), '100', 4);

            if ($coupon->max_discount_amount_bdt !== null && bccomp($calculated, (string) $coupon->max_discount_amount_bdt, 4) > 0) {
                $discount = (string) $coupon->max_discount_amount_bdt;
            } else {
                $discount = $calculated;
            }
        } elseif ($coupon->discount_type === Coupon::TYPE_FIXED) {
            if (bccomp((string) $coupon->discount_value, $subtotal, 4) > 0) {
                $discount = $subtotal;
            } else {
                $discount = (string) $coupon->discount_value;
            }
        } elseif ($coupon->discount_type === Coupon::TYPE_FREE_SHIPPING) {
            $discount = '0.0000';
        }

        $total = bcsub($subtotal, $discount, 4);
        if (bccomp($total, '0.0000', 4) < 0) {
            $total = '0.0000';
        }

        $cart->update([
            'coupon_code' => $coupon->code,
            'discount_amount_bdt' => $discount,
            'total_bdt' => $total,
        ]);

        return $cart->fresh(['items.variant.product']);
    }
}

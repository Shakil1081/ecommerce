<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Actions;

use App\Modules\Cart\Models\Cart;

final class RemoveCouponFromCartAction
{
    /**
     * Remove applied coupon and clear promotional discounts from the cart.
     */
    public function __invoke(Cart $cart): Cart
    {
        $cart->update([
            'coupon_code' => null,
            'discount_amount_bdt' => '0.0000',
            'total_bdt' => $cart->subtotal_bdt,
        ]);

        return $cart->fresh(['items.variant.product']);
    }
}

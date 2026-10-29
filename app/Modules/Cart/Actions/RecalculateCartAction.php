<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Models\Cart;
use App\Modules\Marketing\Models\Coupon;
use App\Modules\Pricing\Actions\CalculateApplicablePriceAction;
use App\Modules\Pricing\Models\PricingTier;
use Illuminate\Support\Carbon;

final class RecalculateCartAction
{
    public function __construct(
        private readonly CalculateApplicablePriceAction $calculatePriceAction
    ) {}

    /**
     * Recalculate all item prices, volume tier discounts, and cart subtotal/total.
     */
    public function __invoke(Cart $cart, string $customerGroup = PricingTier::GROUP_RETAIL): Cart
    {
        $cart->load('items.variant');

        $subtotal = '0.0000';
        $totalItems = 0;

        foreach ($cart->items as $item) {
            if ($item->variant) {
                // Dynamically apply tier pricing
                $priceInfo = ($this->calculatePriceAction)(
                    variant: $item->variant,
                    quantity: (int) $item->quantity,
                    customerGroup: $customerGroup
                );

                $unitPrice = number_format($priceInfo['unit_price_bdt'], 4, '.', '');
                $tierSavings = number_format($priceInfo['savings_per_unit_bdt'], 4, '.', '');
                $itemSubtotal = bcmul($unitPrice, (string) $item->quantity, 4);

                $item->update([
                    'unit_price_bdt' => $unitPrice,
                    'tier_savings_bdt' => $tierSavings,
                    'subtotal_bdt' => $itemSubtotal,
                ]);

                $subtotal = bcadd($subtotal, $itemSubtotal, 4);
                $totalItems += (int) $item->quantity;
            }
        }

        $discount = '0.0000';
        $couponCode = $cart->coupon_code;

        if ($couponCode !== null) {
            /** @var Coupon|null $coupon */
            $coupon = Coupon::query()->where('code', $couponCode)->first();
            if ($coupon && $coupon->isUsable() && bccomp($subtotal, (string) $coupon->min_order_amount_bdt, 4) >= 0) {
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
                }
            } else {
                $couponCode = null;
                $discount = '0.0000';
            }
        }

        $total = bcsub($subtotal, $discount, 4);
        if (bccomp($total, '0.0000', 4) < 0) {
            $total = '0.0000';
        }

        $cart->update([
            'coupon_code' => $couponCode,
            'discount_amount_bdt' => $discount,
            'subtotal_bdt' => $subtotal,
            'total_bdt' => $total,
            'total_items_count' => $totalItems,
            'last_activity_at' => Carbon::now(),
        ]);

        return $cart->fresh(['items.variant.product']);
    }
}

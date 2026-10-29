<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Pricing\Actions\CalculateApplicablePriceAction;
use App\Modules\Pricing\Models\PricingTier;
use Illuminate\Support\Facades\DB;

final class UpdateCartItemQuantityAction
{
    public function __construct(
        private readonly CalculateApplicablePriceAction $calculatePriceAction,
        private readonly RecalculateCartAction $recalculateCartAction
    ) {}

    /**
     * Update quantity for a variant in cart, or remove if quantity is zero or less.
     */
    public function __invoke(
        Cart $cart,
        string $productVariantId,
        int $quantity,
        string $customerGroup = PricingTier::GROUP_RETAIL
    ): Cart {
        return DB::transaction(function () use ($cart, $productVariantId, $quantity, $customerGroup): Cart {
            /** @var CartItem|null $item */
            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_variant_id', $productVariantId)
                ->first();

            if (! $item) {
                return $cart;
            }

            if ($quantity <= 0) {
                $item->delete();
            } else {
                $variant = ProductVariant::findOrFail($productVariantId);
                $pricing = ($this->calculatePriceAction)($variant, $quantity, $customerGroup);
                $unitPrice = number_format($pricing['unit_price_bdt'], 4, '.', '');
                $tierSavings = number_format($pricing['savings_per_unit_bdt'], 4, '.', '');
                $subtotal = bcmul($unitPrice, (string) $quantity, 4);

                $item->update([
                    'quantity' => $quantity,
                    'unit_price_bdt' => $unitPrice,
                    'tier_savings_bdt' => $tierSavings,
                    'subtotal_bdt' => $subtotal,
                ]);
            }

            return ($this->recalculateCartAction)($cart, $customerGroup);
        });
    }
}

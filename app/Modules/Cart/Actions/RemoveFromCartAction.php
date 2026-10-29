<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Pricing\Models\PricingTier;
use Illuminate\Support\Facades\DB;

final class RemoveFromCartAction
{
    public function __construct(
        private readonly RecalculateCartAction $recalculateCartAction
    ) {}

    /**
     * Remove a variant item completely from the shopping cart.
     */
    public function __invoke(
        Cart $cart,
        string $productVariantId,
        string $customerGroup = PricingTier::GROUP_RETAIL
    ): Cart {
        return DB::transaction(function () use ($cart, $productVariantId, $customerGroup): Cart {
            CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_variant_id', $productVariantId)
                ->delete();

            return ($this->recalculateCartAction)($cart, $customerGroup);
        });
    }
}

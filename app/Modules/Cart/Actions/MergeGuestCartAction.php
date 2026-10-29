<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Models\User;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Pricing\Models\PricingTier;
use Illuminate\Support\Facades\DB;

final class MergeGuestCartAction
{
    public function __construct(
        private readonly GetActiveCartAction $getActiveCartAction,
        private readonly RecalculateCartAction $recalculateCartAction
    ) {}

    /**
     * Merge guest session cart into authenticated user cart upon login.
     */
    public function __invoke(
        Cart $guestCart,
        User $user,
        string $customerGroup = PricingTier::GROUP_RETAIL
    ): Cart {
        return DB::transaction(function () use ($guestCart, $user, $customerGroup): Cart {
            $userCart = ($this->getActiveCartAction)($user);

            $guestCart->load('items.variant');
            $userCart->load('items');

            foreach ($guestCart->items as $guestItem) {
                /** @var CartItem|null $existingUserItem */
                $existingUserItem = CartItem::query()
                    ->where('cart_id', $userCart->id)
                    ->where('product_variant_id', $guestItem->product_variant_id)
                    ->first();

                if ($existingUserItem) {
                    $existingUserItem->update([
                        'quantity' => $existingUserItem->quantity + $guestItem->quantity,
                    ]);
                } else {
                    CartItem::create([
                        'cart_id' => $userCart->id,
                        'product_variant_id' => $guestItem->product_variant_id,
                        'quantity' => $guestItem->quantity,
                        'unit_price_bdt' => $guestItem->unit_price_bdt,
                        'tier_savings_bdt' => $guestItem->tier_savings_bdt,
                        'subtotal_bdt' => $guestItem->subtotal_bdt,
                    ]);
                }
            }

            // Mark guest cart as converted
            $guestCart->update(['status' => 'converted']);

            return ($this->recalculateCartAction)($userCart, $customerGroup);
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Pricing\Actions\CalculateApplicablePriceAction;
use App\Modules\Pricing\Models\PricingTier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AddToCartAction
{
    public function __construct(
        private readonly CalculateApplicablePriceAction $calculatePriceAction,
        private readonly RecalculateCartAction $recalculateCartAction
    ) {}

    /**
     * Add or increment variant item in the shopping cart with dynamic tier pricing.
     */
    public function __invoke(
        Cart $cart,
        ProductVariant $variant,
        int $quantity = 1,
        string $customerGroup = PricingTier::GROUP_RETAIL
    ): Cart {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity to add must be greater than zero.');
        }

        return DB::transaction(function () use ($cart, $variant, $quantity, $customerGroup): Cart {
            /** @var CartItem|null $existingItem */
            $existingItem = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_variant_id', $variant->id)
                ->first();

            $newQuantity = $existingItem ? $existingItem->quantity + $quantity : $quantity;

            $pricing = ($this->calculatePriceAction)($variant, $newQuantity, $customerGroup);
            $unitPrice = number_format($pricing['unit_price_bdt'], 4, '.', '');
            $tierSavings = number_format($pricing['savings_per_unit_bdt'], 4, '.', '');
            $subtotal = bcmul($unitPrice, (string) $newQuantity, 4);

            if ($existingItem) {
                $existingItem->update([
                    'quantity' => $newQuantity,
                    'unit_price_bdt' => $unitPrice,
                    'tier_savings_bdt' => $tierSavings,
                    'subtotal_bdt' => $subtotal,
                ]);
            } else {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => $newQuantity,
                    'unit_price_bdt' => $unitPrice,
                    'tier_savings_bdt' => $tierSavings,
                    'subtotal_bdt' => $subtotal,
                ]);
            }

            return ($this->recalculateCartAction)($cart, $customerGroup);
        });
    }
}

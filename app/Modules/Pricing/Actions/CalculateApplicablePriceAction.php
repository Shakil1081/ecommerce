<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Actions;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Pricing\Models\PricingTier;

class CalculateApplicablePriceAction
{
    /**
     * Calculate the applicable unit and total price for a product variant
     * based on customer tier and volume order quantity.
     *
     * @return array{
     *     unit_price_bdt: float,
     *     total_price_bdt: float,
     *     applied_tier_id: ?int,
     *     applied_customer_group: string,
     *     savings_per_unit_bdt: float,
     *     total_savings_bdt: float,
     *     is_tier_applied: bool
     * }
     */
    public function __invoke(
        ProductVariant $variant,
        int $quantity = 1,
        string $customerGroup = PricingTier::GROUP_RETAIL
    ): array {
        $baseUnitPrice = (float) $variant->selling_price_bdt;

        // Find applicable volume tier for the requested group where quantity >= min_quantity
        // Ordered by min_quantity desc to find the best volume break
        $matchingTier = PricingTier::query()
            ->where('product_variant_id', $variant->id)
            ->where('customer_group', $customerGroup)
            ->where('min_quantity', '<=', $quantity)
            ->where('is_active', true)
            ->orderBy('min_quantity', 'desc')
            ->first();

        // If no tier found for the specific group and group is not retail, check general retail volume tiers
        if (! $matchingTier && $customerGroup !== PricingTier::GROUP_RETAIL) {
            $matchingTier = PricingTier::query()
                ->where('product_variant_id', $variant->id)
                ->where('customer_group', PricingTier::GROUP_RETAIL)
                ->where('min_quantity', '<=', $quantity)
                ->where('is_active', true)
                ->orderBy('min_quantity', 'desc')
                ->first();
        }

        if ($matchingTier) {
            $unitPrice = (float) $matchingTier->tier_price_bdt;
            $appliedTierId = $matchingTier->id;
            $appliedGroup = $matchingTier->customer_group;
            $isTierApplied = true;
        } else {
            $unitPrice = $baseUnitPrice;
            $appliedTierId = null;
            $appliedGroup = PricingTier::GROUP_RETAIL;
            $isTierApplied = false;
        }

        $totalPrice = round($unitPrice * $quantity, 4);
        $savingsPerUnit = max(0.0, round($baseUnitPrice - $unitPrice, 4));
        $totalSavings = round($savingsPerUnit * $quantity, 4);

        return [
            'unit_price_bdt' => $unitPrice,
            'total_price_bdt' => $totalPrice,
            'applied_tier_id' => $appliedTierId,
            'applied_customer_group' => $appliedGroup,
            'savings_per_unit_bdt' => $savingsPerUnit,
            'total_savings_bdt' => $totalSavings,
            'is_tier_applied' => $isTierApplied,
        ];
    }
}


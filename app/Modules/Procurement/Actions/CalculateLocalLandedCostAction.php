<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Actions;

use App\Modules\Procurement\Models\LandedCostAllocation;
use App\Modules\Procurement\Models\LocalPurchaseOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CalculateLocalLandedCostAction
{
    /**
     * Compute and proportionately allocate landed costs and unit COGS in BDT for domestic purchases.
     *
     * @param array{
     *     freight_cost_bdt?: string|float,
     *     handling_cost_bdt?: string|float,
     *     other_costs_bdt?: string|float
     * } $overheads
     */
    public function __invoke(
        LocalPurchaseOrder $po,
        array $overheads = [],
        bool $applyToVariants = false
    ): LandedCostAllocation {
        return DB::transaction(function () use ($po, $overheads, $applyToVariants): LandedCostAllocation {
            $po->loadMissing('items.variant');

            if ($po->items->isEmpty()) {
                throw new InvalidArgumentException('Local purchase order contains no items to allocate costs.');
            }

            $baseCost = (string) $po->subtotal_bdt;
            if (bccomp($baseCost, '0.0000', 4) <= 0) {
                $po->recalculateTotals();
                $baseCost = (string) $po->fresh()->subtotal_bdt;
            }

            $freight = number_format((float) ($overheads['freight_cost_bdt'] ?? 0), 4, '.', '');
            $handling = number_format((float) ($overheads['handling_cost_bdt'] ?? 0), 4, '.', '');
            $other = number_format((float) ($overheads['other_costs_bdt'] ?? 0), 4, '.', '');

            $totalOverhead = bcadd(bcadd($freight, $handling, 4), $other, 4);
            $totalLandedCost = bcadd($baseCost, $totalOverhead, 4);

            $breakdown = [];

            foreach ($po->items as $item) {
                $itemSubtotal = (string) $item->subtotal_bdt;
                $qty = (int) $item->quantity_ordered;

                // Proportional overhead allocation: (itemSubtotal * totalOverhead) / baseCost
                $allocatedOverhead = bcdiv(bcmul($itemSubtotal, $totalOverhead, 6), $baseCost, 4);
                $itemLanded = bcadd($itemSubtotal, $allocatedOverhead, 4);
                $unitCogs = $qty > 0 ? bcdiv($itemLanded, (string) $qty, 4) : '0.0000';

                $breakdown[] = [
                    'po_item_id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'sku' => $item->variant?->sku ?? '',
                    'quantity' => $qty,
                    'base_subtotal_bdt' => $itemSubtotal,
                    'allocated_overhead_bdt' => $allocatedOverhead,
                    'total_landed_cost_bdt' => $itemLanded,
                    'unit_cogs_bdt' => $unitCogs,
                ];

                if ($applyToVariants && $item->variant) {
                    $item->variant->update([
                        'purchase_cost_bdt' => $unitCogs,
                    ]);
                }
            }

            $year = Carbon::today()->format('Y');
            $count = LandedCostAllocation::query()->whereYear('created_at', $year)->count() + 1;
            $allocationNumber = sprintf('LCA-LOC-%s-%05d', $year, $count);

            return LandedCostAllocation::create([
                'allocation_number' => $allocationNumber,
                'allocation_date' => Carbon::today()->toDateString(),
                'reference_type' => 'local',
                'local_purchase_order_id' => $po->id,
                'base_purchase_cost_bdt' => $baseCost,
                'freight_cost_bdt' => $freight,
                'insurance_cost_bdt' => '0.0000',
                'port_cnf_cost_bdt' => '0.0000',
                'customs_duties_bdt' => '0.0000',
                'transport_carrying_bdt' => '0.0000',
                'handling_cost_bdt' => $handling,
                'other_costs_bdt' => $other,
                'total_landed_cost_bdt' => $totalLandedCost,
                'allocated_items_breakdown' => $breakdown,
                'status' => $applyToVariants ? 'applied_to_inventory' : 'allocated',
            ]);
        });
    }
}

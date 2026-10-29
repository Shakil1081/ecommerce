<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Actions;

use App\Modules\Procurement\Models\BillOfEntry;
use App\Modules\Procurement\Models\ImportPurchaseOrder;
use App\Modules\Procurement\Models\LandedCostAllocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CalculateImportLandedCostAction
{
    /**
     * Compute and proportionately allocate landed costs and unit COGS in BDT for imported goods.
     *
     * @param array{
     *     freight_cost_bdt?: string|float,
     *     insurance_cost_bdt?: string|float,
     *     port_cnf_cost_bdt?: string|float,
     *     transport_carrying_bdt?: string|float,
     *     other_costs_bdt?: string|float
     * } $overheads
     */
    public function __invoke(
        ImportPurchaseOrder $order,
        ?BillOfEntry $boe = null,
        array $overheads = [],
        bool $applyToVariants = false
    ): LandedCostAllocation {
        return DB::transaction(function () use ($order, $boe, $overheads, $applyToVariants): LandedCostAllocation {
            $order->loadMissing('items.variant');

            if ($order->items->isEmpty()) {
                throw new InvalidArgumentException('Import purchase order contains no items to allocate costs.');
            }

            $baseCost = (string) $order->total_fob_bdt;
            if (bccomp($baseCost, '0.0000', 4) <= 0) {
                $order->recalculateTotals();
                $baseCost = (string) $order->fresh()->total_fob_bdt;
            }

            $freight = number_format((float) ($overheads['freight_cost_bdt'] ?? 0), 4, '.', '');
            $insurance = number_format((float) ($overheads['insurance_cost_bdt'] ?? 0), 4, '.', '');
            $cnf = number_format((float) ($overheads['port_cnf_cost_bdt'] ?? 0), 4, '.', '');
            $carrying = number_format((float) ($overheads['transport_carrying_bdt'] ?? 0), 4, '.', '');
            $other = number_format((float) ($overheads['other_costs_bdt'] ?? 0), 4, '.', '');

            // Capitalizable customs duties: CD + RD + SD (excluding rebatable AT 5% and VAT)
            $customsDuties = '0.0000';
            if ($boe) {
                $customsDuties = bcadd(
                    bcadd((string) $boe->customs_duty_cd, (string) $boe->regulatory_duty_rd, 4),
                    (string) $boe->supplementary_duty_sd,
                    4
                );
            }

            // Total ancillary overhead costs
            $totalOverhead = bcadd(
                bcadd(
                    bcadd(bcadd(bcadd($freight, $insurance, 4), $cnf, 4), $customsDuties, 4),
                    $carrying,
                    4
                ),
                $other,
                4
            );

            $totalLandedCost = bcadd($baseCost, $totalOverhead, 4);

            $breakdown = [];

            foreach ($order->items as $item) {
                $itemFob = (string) $item->total_fob_bdt;
                $qty = (int) $item->quantity_ordered;

                // Proportional overhead allocation: (itemFob * totalOverhead) / baseCost
                $allocatedOverhead = bcdiv(bcmul($itemFob, $totalOverhead, 6), $baseCost, 4);
                $itemLanded = bcadd($itemFob, $allocatedOverhead, 4);
                $unitCogs = $qty > 0 ? bcdiv($itemLanded, (string) $qty, 4) : '0.0000';

                $breakdown[] = [
                    'po_item_id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'sku' => $item->variant?->sku ?? '',
                    'quantity' => $qty,
                    'base_fob_bdt' => $itemFob,
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
            $allocationNumber = sprintf('LCA-IMP-%s-%05d', $year, $count);

            return LandedCostAllocation::create([
                'allocation_number' => $allocationNumber,
                'allocation_date' => Carbon::today()->toDateString(),
                'reference_type' => 'import',
                'import_purchase_order_id' => $order->id,
                'bill_of_entry_id' => $boe?->id,
                'base_purchase_cost_bdt' => $baseCost,
                'freight_cost_bdt' => $freight,
                'insurance_cost_bdt' => $insurance,
                'port_cnf_cost_bdt' => $cnf,
                'customs_duties_bdt' => $customsDuties,
                'transport_carrying_bdt' => $carrying,
                'handling_cost_bdt' => '0.0000',
                'other_costs_bdt' => $other,
                'total_landed_cost_bdt' => $totalLandedCost,
                'allocated_items_breakdown' => $breakdown,
                'status' => $applyToVariants ? 'applied_to_inventory' : 'allocated',
            ]);
        });
    }
}

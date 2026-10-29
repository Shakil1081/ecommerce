<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Actions;

use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Procurement\Models\GoodsReceivedNote;
use App\Modules\Procurement\Models\ImportPurchaseOrder;
use App\Modules\Procurement\Models\LocalPurchaseOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReceiveGoodsNoteAction
{
    /**
     * Atomically receive goods into physical warehouse inventory, update PO quantities,
     * and reconcile discrepancies.
     */
    public function __invoke(GoodsReceivedNote $grn): GoodsReceivedNote
    {
        return DB::transaction(function () use ($grn): GoodsReceivedNote {
            $grn->loadMissing('items.variant', 'warehouse');

            if ($grn->items->isEmpty()) {
                throw new InvalidArgumentException('GRN contains no items to receive.');
            }

            if (in_array($grn->status, ['completed', 'discrepancy_flagged'], true)) {
                throw new InvalidArgumentException('GRN has already been processed and committed to inventory.');
            }

            $totalOrdered = 0;
            $totalReceived = 0;
            $totalDamaged = 0;

            foreach ($grn->items as $item) {
                $ordered = (int) $item->quantity_ordered;
                $received = (int) $item->quantity_received;
                $damaged = (int) $item->quantity_damaged;
                $usable = max(0, $received - $damaged);

                $totalOrdered += $ordered;
                $totalReceived += $received;
                $totalDamaged += $damaged;

                // 1. Atomically commit usable stock into physical inventory
                if ($usable > 0) {
                    $stock = InventoryStock::firstOrCreate(
                        [
                            'warehouse_id' => $grn->warehouse_id,
                            'product_variant_id' => $item->product_variant_id,
                        ],
                        [
                            'physical_quantity' => 0,
                            'reserved_quantity' => 0,
                            'bin_location' => $item->bin_location,
                        ]
                    );

                    // Row-level locking to avoid race conditions during concurrent inward receiving
                    /** @var InventoryStock $lockedStock */
                    $lockedStock = InventoryStock::query()
                        ->where('id', $stock->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $lockedStock->increment('physical_quantity', $usable);

                    if (! empty($item->bin_location) && empty($lockedStock->bin_location)) {
                        $lockedStock->update(['bin_location' => $item->bin_location]);
                    }
                }

                // 2. Increment parent PO item received quantity
                if ($item->import_po_item_id && $item->importPoItem) {
                    $item->importPoItem()->increment('quantity_received', $received);
                } elseif ($item->local_po_item_id && $item->localPoItem) {
                    $item->localPoItem()->increment('quantity_received', $received);
                }
            }

            // 3. Status evaluation
            $hasDiscrepancy = ($totalDamaged > 0) || ($totalReceived < $totalOrdered);
            $grnStatus = $hasDiscrepancy ? 'discrepancy_flagged' : 'completed';

            $grn->update([
                'total_ordered_quantity' => $totalOrdered,
                'total_received_quantity' => $totalReceived,
                'total_damaged_quantity' => $totalDamaged,
                'status' => $grnStatus,
            ]);

            // 4. Reconcile parent PO lifecycle status
            if ($grn->import_purchase_order_id) {
                $importOrder = ImportPurchaseOrder::with('items')->find($grn->import_purchase_order_id);
                if ($importOrder) {
                    $allReceived = $importOrder->items->every(fn ($i) => $i->quantity_received >= $i->quantity_ordered);
                    $importOrder->update([
                        'status' => $allReceived ? 'received' : 'customs_clearing',
                    ]);
                }
            } elseif ($grn->local_purchase_order_id) {
                $localOrder = LocalPurchaseOrder::with('items')->find($grn->local_purchase_order_id);
                if ($localOrder) {
                    $allReceived = $localOrder->items->every(fn ($i) => $i->quantity_received >= $i->quantity_ordered);
                    $localOrder->update([
                        'status' => $allReceived ? 'received' : 'partially_received',
                    ]);
                }
            }

            return $grn->fresh(['items', 'warehouse']);
        });
    }
}

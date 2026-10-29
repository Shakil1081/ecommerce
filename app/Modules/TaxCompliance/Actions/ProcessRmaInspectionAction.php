<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Actions;

use App\Modules\Inventory\Actions\AdjustInventoryStockAction;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\TaxCompliance\Models\ReturnMerchandiseAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProcessRmaInspectionAction
{
    public function __construct(
        protected AdjustInventoryStockAction $adjustStockAction,
        protected IssueMushak67CreditNoteAction $issueCreditNoteAction,
    ) {}

    /**
     * Inspect returned RMA goods, atomically reverse inventory stock if approved,
     * issue a statutory NBR Mushak 6.7 Credit Note, and record customer refund.
     *
     * @param  array<string, mixed>  $options
     */
    public function __invoke(
        ReturnMerchandiseAuthorization $rma,
        string $inspectionOutcome,
        Warehouse $warehouse,
        ?string $adminNotes = null,
        array $options = []
    ): ReturnMerchandiseAuthorization {
        if (in_array($rma->status, ['completed', 'rejected', 'cancelled'], true)) {
            throw new InvalidArgumentException("RMA #{$rma->rma_number} is already {$rma->status} and cannot be inspected.");
        }

        $validOutcomes = ['restock_available', 'quarantine_damaged', 'scrap'];
        if (! in_array($inspectionOutcome, $validOutcomes, true)) {
            throw new InvalidArgumentException("Invalid inspection outcome: {$inspectionOutcome}. Must be one of: ".implode(', ', $validOutcomes));
        }

        $rma->loadMissing('items.variant', 'order');

        $now = CarbonImmutable::now();

        return DB::transaction(function () use (
            $rma,
            $inspectionOutcome,
            $warehouse,
            $adminNotes,
            $now,
            $options
        ): ReturnMerchandiseAuthorization {
            // 1. If restockable, atomically add stock back to warehouse
            if ($inspectionOutcome === 'restock_available') {
                foreach ($rma->items as $rmaItem) {
                    $qtyToRestock = (int) $rmaItem->quantity_approved;
                    if ($qtyToRestock > 0 && $rmaItem->variant) {
                        ($this->adjustStockAction)(
                            $warehouse,
                            $rmaItem->variant,
                            $qtyToRestock,
                            'rma_restock',
                            'RETURNS-BAY'
                        );
                    }
                }
            }

            // 2. Issue statutory Mushak 6.7 Credit Note
            $creditNoteItems = $rma->items->map(fn ($item): array => [
                'order_item_id' => $item->order_item_id,
                'quantity' => (int) $item->quantity_approved,
            ])->all();

            $reason = sprintf(
                'Customer Return (RMA #%s): %s - %s',
                $rma->rma_number,
                $rma->reason_category,
                $adminNotes ?? 'Approved after physical inspection'
            );

            $creditNote = ($this->issueCreditNoteAction)(
                $rma->order,
                $creditNoteItems,
                $reason,
                $rma,
                $options
            );

            // 3. Record customer financial refund
            $refundMethod = $options['refund_method'] ?? 'original_gateway';
            $refundRef = $options['refund_transaction_reference'] ?? ('REF-'.strtoupper(Str::random(10)));

            $rma->update([
                'status' => 'completed',
                'inspection_outcome' => $inspectionOutcome,
                'admin_notes' => $adminNotes,
                'refund_status' => 'processed',
                'refund_method' => $refundMethod,
                'refund_transaction_reference' => $refundRef,
                'inspected_at' => $now,
                'refunded_at' => $now,
            ]);

            $rma->load('creditNote.items', 'items');

            return $rma;
        });
    }
}

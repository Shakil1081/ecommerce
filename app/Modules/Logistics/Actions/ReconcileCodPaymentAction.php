<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Actions;

use App\Modules\Logistics\Models\CourierConsignment;
use App\Modules\Logistics\Models\CourierDisbursement;
use App\Modules\Logistics\Models\CourierDisbursementItem;
use App\Modules\Order\Models\Order;
use Illuminate\Support\Facades\DB;

class ReconcileCodPaymentAction
{
    /**
     * Process courier COD disbursement settlement statement and flag discrepancies.
     *
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    public function __invoke(
        string $courierCode,
        string $disbursementDate,
        ?string $referenceInvoice = null,
        array $itemsData = [],
        ?string $notes = null,
    ): CourierDisbursement {
        return DB::transaction(function () use ($courierCode, $disbursementDate, $referenceInvoice, $itemsData, $notes): CourierDisbursement {
            $disbursement = CourierDisbursement::create([
                'courier_code' => $courierCode,
                'disbursement_date' => $disbursementDate,
                'reference_invoice' => $referenceInvoice,
                'notes' => $notes,
                'status' => CourierDisbursement::STATUS_PENDING,
            ]);

            $totalCollected = '0.0000';
            $totalFee = '0.0000';
            $hasDiscrepancy = false;

            foreach ($itemsData as $data) {
                $consignmentId = (string) ($data['consignment_id'] ?? '');
                $orderNumber = (string) ($data['order_number'] ?? '');
                $collectedBdt = (string) ($data['collected_amount_bdt'] ?? '0.0000');
                $feeBdt = (string) ($data['courier_fee_bdt'] ?? '0.0000');
                $itemNotes = (string) ($data['notes'] ?? '');

                // Locate matching consignment or order
                $consignment = CourierConsignment::query()
                    ->when($consignmentId !== '', fn ($q) => $q->where('consignment_id', $consignmentId))
                    ->first();

                $order = null;
                if ($consignment) {
                    $order = $consignment->order;
                } elseif ($orderNumber !== '') {
                    $order = Order::where('order_number', $orderNumber)->first();
                }

                $expectedBdt = $consignment
                    ? (string) $consignment->cod_amount_bdt
                    : ($order ? (string) $order->payable_cod_amount_bdt : $collectedBdt);

                $diff = bcsub($collectedBdt, $expectedBdt, 4);
                $isReconciled = bccomp($diff, '0.0000', 4) === 0;

                if (! $isReconciled) {
                    $hasDiscrepancy = true;
                }

                CourierDisbursementItem::create([
                    'disbursement_id' => $disbursement->id,
                    'consignment_id' => $consignmentId ?: ($consignment?->consignment_id ?? 'N/A'),
                    'order_number' => $orderNumber ?: ($order?->order_number ?? null),
                    'order_id' => $order?->id,
                    'collected_amount_bdt' => $collectedBdt,
                    'expected_amount_bdt' => $expectedBdt,
                    'courier_fee_bdt' => $feeBdt,
                    'discrepancy_amount_bdt' => $diff,
                    'is_reconciled' => $isReconciled,
                    'notes' => $itemNotes,
                ]);

                $totalCollected = bcadd($totalCollected, $collectedBdt, 4);
                $totalFee = bcadd($totalFee, $feeBdt, 4);
            }

            $netDisbursed = bcsub($totalCollected, $totalFee, 4);

            $disbursement->update([
                'total_collected_bdt' => $totalCollected,
                'total_courier_fee_bdt' => $totalFee,
                'net_disbursed_bdt' => $netDisbursed,
                'status' => $hasDiscrepancy ? CourierDisbursement::STATUS_DISCREPANCY : CourierDisbursement::STATUS_RECONCILED,
            ]);

            return $disbursement->fresh(['items']);
        });
    }
}

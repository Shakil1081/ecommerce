<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Actions;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\TaxCompliance\Models\ReturnMerchandiseAuthorization;
use App\Modules\TaxCompliance\Models\RmaItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateRmaRequestAction
{
    /**
     * Create a customer Return Merchandise Authorization (RMA) request.
     *
     * @param  array<int, array{order_item_id: int, quantity: int, condition_note?: ?string}>  $items
     */
    public function __invoke(
        Order $order,
        array $items,
        string $reasonCategory,
        ?string $customerNotes = null,
        ?int $userId = null
    ): ReturnMerchandiseAuthorization {
        if (empty($items)) {
            throw new InvalidArgumentException('RMA request must include at least one item to return.');
        }

        $now = CarbonImmutable::now();
        $yearMonth = $now->format('Ym');

        return DB::transaction(function () use (
            $order,
            $items,
            $reasonCategory,
            $customerNotes,
            $userId,
            $yearMonth
        ): ReturnMerchandiseAuthorization {
            $count = ReturnMerchandiseAuthorization::whereYear('created_at', date('Y'))->count() + 1;
            $rmaNumber = sprintf('RMA-%s-%06d', $yearMonth, $count);

            /** @var ReturnMerchandiseAuthorization $rma */
            $rma = ReturnMerchandiseAuthorization::create([
                'rma_number' => $rmaNumber,
                'order_id' => $order->id,
                'user_id' => $userId ?? $order->user_id,
                'status' => 'requested',
                'reason_category' => $reasonCategory,
                'customer_notes' => $customerNotes,
                'refund_amount_bdt' => '0.0000',
                'refund_status' => 'pending',
            ]);

            // Money stays in DECIMAL(14,4) strings with bcmath (no float rounding drift)
            $totalEstimatedRefund = '0.0000';

            foreach ($items as $itemData) {
                /** @var OrderItem $orderItem */
                $orderItem = OrderItem::where('order_id', $order->id)
                    ->where('id', $itemData['order_item_id'])
                    ->firstOrFail();

                $requestedQty = (int) $itemData['quantity'];
                if ($requestedQty <= 0 || $requestedQty > (int) $orderItem->quantity) {
                    throw new InvalidArgumentException("Invalid return quantity {$requestedQty} for SKU: {$orderItem->sku}");
                }

                $unitPrice = bcadd((string) $orderItem->unit_price_bdt, '0', 4);
                $lineRefund = bcmul($unitPrice, (string) $requestedQty, 4);
                $totalEstimatedRefund = bcadd($totalEstimatedRefund, $lineRefund, 4);

                RmaItem::create([
                    'rma_id' => $rma->id,
                    'order_item_id' => $orderItem->id,
                    'product_id' => (string) $orderItem->product_id,
                    'product_variant_id' => (string) $orderItem->product_variant_id,
                    'item_name' => $orderItem->product_name,
                    'sku' => $orderItem->sku,
                    'quantity_requested' => $requestedQty,
                    'quantity_approved' => $requestedQty, // preliminary approval
                    'unit_price_bdt' => $unitPrice,
                    'line_refund_bdt' => $lineRefund,
                    'condition_note' => $itemData['condition_note'] ?? null,
                ]);
            }

            $rma->updateQuietly([
                'refund_amount_bdt' => $totalEstimatedRefund,
            ]);

            $rma->load('items');

            return $rma;
        });
    }
}

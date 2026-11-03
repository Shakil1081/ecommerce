<?php

declare(strict_types=1);

namespace App\Modules\Order\Actions;

use App\Models\User;
use App\Modules\Inventory\Actions\RestockReturnedOrderAction;
use App\Modules\Order\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ScanAndReceiveReturnAction
{
    public function __construct(
        private readonly TransitionOrderStatusAction $transitionAction,
        private readonly RestockReturnedOrderAction $restockAction,
    ) {}

    /**
     * Look up an order by barcode / tracking code, transition to returned, and atomically restock inventory.
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     already_returned?: bool,
     *     order?: array<string, mixed>
     * }
     */
    public function __invoke(string $rawBarcode, ?User $scannedBy = null, ?string $notes = null): array
    {
        $barcode = trim($rawBarcode);

        if ($barcode === '') {
            throw new InvalidArgumentException('Barcode input cannot be empty.');
        }

        // 1. Search by Order Number, Courier Tracking Code, Courier Consignment ID, or ULID
        /** @var Order|null $order */
        $order = Order::query()
            ->with(['items.variant.product', 'district', 'division'])
            ->where('order_number', $barcode)
            ->orWhere('courier_tracking_code', $barcode)
            ->orWhere('courier_consignment_id', $barcode)
            ->orWhere('ulid', $barcode)
            ->first();

        if (! $order) {
            return [
                'success' => false,
                'message' => "No order found matching barcode or tracking code [{$barcode}].",
            ];
        }

        // 2. Check if already returned
        if ($order->status === Order::STATUS_RETURNED) {
            $returnedAt = $order->statusHistories()
                ->where('to_status', Order::STATUS_RETURNED)
                ->latest()
                ->value('created_at');

            $dateFormatted = $returnedAt ? CarbonImmutable::parse($returnedAt)->format('d M Y, h:i A') : 'earlier';

            return [
                'success' => false,
                'already_returned' => true,
                'message' => "Order [{$order->order_number}] was ALREADY received as returned on {$dateFormatted}.",
                'order' => $this->formatOrderPayload($order),
            ];
        }

        // 3. Check if order can transition to returned
        // Allow transition from handed_to_courier, out_for_delivery, delivered, or processing
        if (! $order->canTransitionTo(Order::STATUS_RETURNED) && ! in_array($order->status, [Order::STATUS_PROCESSING, Order::STATUS_PICKED_AND_PACKED], true)) {
            return [
                'success' => false,
                'message' => "Order [{$order->order_number}] is currently [{$order->status}] and cannot be transitioned to returned.",
                'order' => $this->formatOrderPayload($order),
            ];
        }

        // If in processing/picked_and_packed, allow forcing to handed_to_courier first or direct return
        return DB::transaction(function () use ($order, $scannedBy, $notes, $barcode): array {
            $reason = $notes ?: "Received back from courier via Barcode Scanner [Code: {$barcode}]";

            // If it was in processing/picked_and_packed, transition through handed_to_courier first so state machine allows it
            if (in_array($order->status, [Order::STATUS_PROCESSING, Order::STATUS_PICKED_AND_PACKED], true)) {
                ($this->transitionAction)(
                    order: $order,
                    targetStatus: Order::STATUS_HANDED_TO_COURIER,
                    changedBy: $scannedBy,
                    reason: 'Fast-forwarded to dispatched prior to return scan.'
                );
            }

            ($this->transitionAction)(
                order: $order,
                targetStatus: Order::STATUS_RETURNED,
                changedBy: $scannedBy,
                reason: $reason
            );

            // Restock physical items back into warehouse
            ($this->restockAction)($order);

            $order->refresh();
            $order->loadMissing(['items.variant.product', 'district', 'division']);

            $totalItems = $order->items->sum('quantity');

            return [
                'success' => true,
                'message' => "Order [{$order->order_number}] successfully received as RETURNED. {$totalItems} physical item(s) restocked into warehouse inventory.",
                'order' => $this->formatOrderPayload($order),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOrderPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'courier_code' => $order->courier_code ? strtoupper($order->courier_code) : 'N/A',
            'courier_tracking_code' => $order->courier_tracking_code,
            'courier_consignment_id' => $order->courier_consignment_id,
            'delivery_zone' => $order->delivery_zone,
            'total_bdt' => $order->total_bdt,
            'payable_cod_amount_bdt' => $order->payable_cod_amount_bdt,
            'items_count' => $order->items->sum('quantity'),
            'items' => $order->items->map(fn ($item): array => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price_bdt' => $item->unit_price_bdt,
            ])->values()->all(),
            'scanned_at' => CarbonImmutable::now()->format('h:i:s A'),
        ];
    }
}

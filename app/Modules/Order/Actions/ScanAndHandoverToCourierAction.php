<?php

declare(strict_types=1);

namespace App\Modules\Order\Actions;

use App\Models\User;
use App\Modules\Inventory\Actions\CommitStockReservationAction;
use App\Modules\Inventory\Actions\ReserveStockForOrderAction;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Logistics\Actions\BookCourierConsignmentAction;
use App\Modules\Order\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ScanAndHandoverToCourierAction
{
    public function __construct(
        private readonly TransitionOrderStatusAction $transitionAction,
        private readonly CommitStockReservationAction $commitAction,
        private readonly ReserveStockForOrderAction $reserveAction,
        private readonly BookCourierConsignmentAction $bookCourierAction,
    ) {}

    /**
     * Look up an order by barcode / tracking code, optionally auto-book consignment,
     * transition to handed_to_courier, and commit inventory deduction.
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     already_handed_over?: bool,
     *     booking_warning?: string|null,
     *     order?: array<string, mixed>
     * }
     */
    public function __invoke(
        string $rawBarcode,
        ?string $courierCode = null,
        bool $autoBook = true,
        ?User $scannedBy = null,
        ?string $notes = null
    ): array {
        $barcode = trim($rawBarcode);

        if ($barcode === '') {
            throw new InvalidArgumentException('Barcode input cannot be empty.');
        }

        // 1. Search order by Order Number, Courier Tracking Code, Courier Consignment ID, or ULID
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

        // 2. Check if already handed over or in transit
        if (in_array($order->status, [Order::STATUS_HANDED_TO_COURIER, Order::STATUS_OUT_FOR_DELIVERY], true)) {
            $shippedAt = $order->shipped_at ?? $order->statusHistories()
                ->where('to_status', Order::STATUS_HANDED_TO_COURIER)
                ->latest()
                ->value('created_at');

            $dateFormatted = $shippedAt ? CarbonImmutable::parse($shippedAt)->format('d M Y, h:i A') : 'earlier';

            return [
                'success' => false,
                'already_handed_over' => true,
                'message' => "Order [{$order->order_number}] was ALREADY handed over to courier on {$dateFormatted}.",
                'order' => $this->formatOrderPayload($order),
            ];
        }

        // 3. Disallow handing over cancelled or returned orders
        if (in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_RETURNED], true)) {
            return [
                'success' => false,
                'message' => "Cannot handover: Order [{$order->order_number}] is {$order->status}.",
                'order' => $this->formatOrderPayload($order),
            ];
        }

        if ($order->status === Order::STATUS_DELIVERED) {
            return [
                'success' => false,
                'message' => "Order [{$order->order_number}] is already marked as DELIVERED.",
                'order' => $this->formatOrderPayload($order),
            ];
        }

        return DB::transaction(function () use ($order, $courierCode, $autoBook, $scannedBy, $notes, $barcode): array {
            $bookingWarning = null;

            // Step A: If still pending, confirm and reserve stock first
            if ($order->status === Order::STATUS_PENDING) {
                try {
                    ($this->reserveAction)($order);
                } catch (InsufficientStockException $e) {
                    return [
                        'success' => false,
                        'message' => "Cannot handover: {$e->getMessage()}",
                        'order' => $this->formatOrderPayload($order),
                    ];
                }

                ($this->transitionAction)(
                    order: $order,
                    targetStatus: Order::STATUS_CONFIRMED,
                    changedBy: $scannedBy,
                    reason: 'Auto-confirmed via Scan & Handover console.'
                );
            }

            // Step B: Auto-book online consignment with selected courier if not booked yet
            $selectedCourier = $courierCode ?: $order->courier_code;
            if ($autoBook && empty($order->courier_tracking_code) && empty($order->courier_consignment_id)) {
                $bookingResult = ($this->bookCourierAction)($order, $selectedCourier);
                if (! $bookingResult->success) {
                    $bookingWarning = 'Courier API Note: '.($bookingResult->errorMessage ?? 'Online booking skipped/failed, proceeding with manual dispatch.');
                    if ($selectedCourier) {
                        $order->update(['courier_code' => $selectedCourier]);
                    }
                } else {
                    $order->refresh();
                }
            } elseif ($selectedCourier && empty($order->courier_code)) {
                $order->update(['courier_code' => $selectedCourier]);
            }

            // Step C: Ensure state machine can reach handed_to_courier
            if ($order->status === Order::STATUS_CONFIRMED) {
                ($this->transitionAction)(
                    order: $order,
                    targetStatus: Order::STATUS_PROCESSING,
                    changedBy: $scannedBy,
                    reason: 'Fast-forwarded to processing for courier handover.'
                );
            }

            // Step D: Transition to Handed to Courier
            $reason = $notes ?: "Handed over to courier via Handheld Barcode Scanner [Code: {$barcode}]";
            ($this->transitionAction)(
                order: $order,
                targetStatus: Order::STATUS_HANDED_TO_COURIER,
                changedBy: $scannedBy,
                reason: $reason,
                metadata: [
                    'scanned_barcode' => $barcode,
                    'courier_code' => $order->courier_code,
                    'tracking_code' => $order->courier_tracking_code,
                ]
            );

            // Step E: Commit physical inventory deduction
            ($this->commitAction)($order);

            $order->refresh();
            $order->loadMissing(['items.variant.product', 'district', 'division']);

            $courierName = $order->courier_code ? strtoupper($order->courier_code) : 'Courier';
            $successMessage = "Order [{$order->order_number}] successfully handed over to {$courierName}.";
            if ($bookingWarning) {
                $successMessage .= " ({$bookingWarning})";
            }

            return [
                'success' => true,
                'message' => $successMessage,
                'booking_warning' => $bookingWarning,
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
            'district_name' => $order->district?->name_en ?? 'N/A',
            'delivery_address' => $order->delivery_address,
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
            'shipped_at' => $order->shipped_at?->format('d M Y, h:i A') ?? CarbonImmutable::now()->format('d M Y, h:i A'),
            'scanned_at' => CarbonImmutable::now()->format('h:i:s A'),
        ];
    }
}

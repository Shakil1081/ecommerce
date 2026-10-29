<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Actions;

use App\Modules\Order\Models\Order;

class BulkBookCourierConsignmentsAction
{
    public function __construct(
        protected BookCourierConsignmentAction $bookAction,
    ) {}

    /**
     * Bulk dispatch multiple orders to a courier service.
     *
     * @param  list<int>  $orderIds
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function __invoke(
        array $orderIds,
        ?string $courierCode = null,
        array $options = []
    ): array {
        $orders = Order::whereIn('id', $orderIds)->get();

        $bookedCount = 0;
        $failedCount = 0;
        $items = [];

        foreach ($orders as $order) {
            $result = ($this->bookAction)($order, $courierCode, $options);

            if ($result->success) {
                $bookedCount++;
                $items[] = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => 'booked',
                    'consignment_id' => $result->consignmentId,
                    'tracking_code' => $result->trackingCode,
                ];
            } else {
                $failedCount++;
                $items[] = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => 'failed',
                    'error' => $result->errorMessage,
                ];
            }
        }

        return [
            'total' => count($orderIds),
            'booked_count' => $bookedCount,
            'failed_count' => $failedCount,
            'items' => $items,
        ];
    }
}

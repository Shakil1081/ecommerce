<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Actions;

use App\Modules\Logistics\DTOs\CourierBookingResultDto;
use App\Modules\Logistics\Services\CourierGatewayManager;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderStatusHistory;
use InvalidArgumentException;

class BookCourierConsignmentAction
{
    public function __construct(
        protected CourierGatewayManager $manager,
    ) {}

    /**
     * Dispatch and book consignment with courier for the given order.
     *
     * @param  array<string, mixed>  $options
     */
    public function __invoke(
        Order $order,
        ?string $courierCode = null,
        array $options = []
    ): CourierBookingResultDto {
        if (in_array($order->status, [Order::STATUS_DELIVERED, Order::STATUS_RETURNED, Order::STATUS_CANCELLED], true)) {
            return CourierBookingResultDto::failed("Order cannot be dispatched from '{$order->status}' status.");
        }

        try {
            if ($courierCode !== null) {
                $adapter = $this->manager->resolve($courierCode);
            } elseif (! empty($order->courier_code)) {
                $adapter = $this->manager->resolve($order->courier_code);
            } else {
                $adapter = $this->manager->getActive();
            }

            if (! $adapter) {
                return CourierBookingResultDto::failed('No active courier service configured.');
            }

            $previousStatus = $order->status;
            $result = $adapter->bookConsignment($order, $options);

            if ($result->success) {
                if ($order->canTransitionTo(Order::STATUS_PROCESSING) || $order->status === Order::STATUS_PROCESSING) {
                    $order->update(['status' => Order::STATUS_PROCESSING]);

                    if ($previousStatus !== Order::STATUS_PROCESSING) {
                        OrderStatusHistory::create([
                            'order_id' => $order->id,
                            'from_status' => $previousStatus,
                            'to_status' => Order::STATUS_PROCESSING,
                            'notes' => "Consignment booked with {$adapter->name()} (ID: {$result->consignmentId})",
                            'created_at' => now(),
                        ]);
                    }
                }
            }

            return $result;
        } catch (InvalidArgumentException $e) {
            return CourierBookingResultDto::failed($e->getMessage());
        }
    }
}

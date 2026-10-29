<?php

declare(strict_types=1);

namespace App\Modules\Order\Actions;

use App\Models\User;
use App\Modules\Order\Events\OrderStatusChangedEvent;
use App\Modules\Order\Exceptions\IllegalOrderStateTransitionException;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderStatusHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class TransitionOrderStatusAction
{
    /**
     * Transition an order to a new lifecycle status, enforcing FSM constraints and logging audit history.
     *
     * @param  array<string, mixed>|null  $metadata
     *
     * @throws IllegalOrderStateTransitionException
     */
    public function __invoke(
        Order $order,
        string $targetStatus,
        ?User $changedBy = null,
        ?string $reason = null,
        ?array $metadata = null
    ): Order {
        if (! $order->canTransitionTo($targetStatus)) {
            throw IllegalOrderStateTransitionException::make(
                orderNumber: $order->order_number,
                fromStatus: $order->status,
                toStatus: $targetStatus
            );
        }

        return DB::transaction(function () use ($order, $targetStatus, $changedBy, $reason, $metadata): Order {
            $fromStatus = $order->status;
            $now = Carbon::now();

            $order->status = $targetStatus;

            // Set milestone timestamps and state-specific updates
            match ($targetStatus) {
                Order::STATUS_CONFIRMED => $order->confirmed_at = $now,
                Order::STATUS_HANDED_TO_COURIER => $order->shipped_at = $now,
                Order::STATUS_DELIVERED => (function () use ($order, $now): void {
                    $order->delivered_at = $now;
                    if ($order->payment_method === 'cash_on_delivery') {
                        $order->payment_status = Order::PAYMENT_PAID;
                    }
                })(),
                Order::STATUS_CANCELLED => $order->cancelled_at = $now,
                default => null,
            };

            $order->save();

            // Record immutable audit history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'changed_by_user_id' => $changedBy?->id,
                'reason' => $reason,
                'metadata' => $metadata,
                'created_at' => $now,
            ]);

            OrderStatusChangedEvent::dispatch($order, $fromStatus, $targetStatus, $changedBy);

            return $order->fresh(['items', 'statusHistories']);
        });
    }
}

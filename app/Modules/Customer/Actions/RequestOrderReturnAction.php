<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Order\Models\Order;
use App\Modules\TaxCompliance\Actions\CreateRmaRequestAction;
use App\Modules\TaxCompliance\Models\ReturnMerchandiseAuthorization;
use App\Modules\TaxCompliance\Models\RmaItem;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Customer self-service return request from My Account.
 *
 * Enforces the storefront return policy (delivered orders only, within the return window, never more
 * units than were bought minus units already in an open/accepted return) and then hands off to the
 * statutory RMA engine. Staff inspect it in Admin > Tax Compliance > RMA, which issues the Mushak 6.7.
 */
final class RequestOrderReturnAction
{
    public const RETURN_WINDOW_DAYS = 7;

    public const REASONS = ['defective', 'wrong_item', 'damaged_in_shipping', 'changed_mind'];

    /** RMA statuses that no longer hold units against the order. */
    private const RELEASED_STATUSES = ['rejected', 'cancelled'];

    public function __construct(
        private readonly CreateRmaRequestAction $createRma,
    ) {}

    /**
     * @param  array<int, array{order_item_id: int, quantity: int}>  $items
     *
     * @throws ValidationException
     */
    public function __invoke(
        User $customer,
        Order $order,
        array $items,
        string $reason,
        ?string $notes = null,
    ): ReturnMerchandiseAuthorization {
        if ($order->user_id !== $customer->id) {
            abort(404);
        }

        if (! self::isWithinReturnWindow($order)) {
            throw ValidationException::withMessages([
                'items' => 'Returns can only be requested within '.self::RETURN_WINDOW_DAYS.' days of delivery.',
            ]);
        }

        $items = array_values(array_filter($items, fn (array $item): bool => (int) $item['quantity'] > 0));
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Please choose at least one item to return.',
            ]);
        }

        $returnable = self::returnableQuantities($order);

        foreach ($items as $item) {
            $itemId = (int) $item['order_item_id'];
            $available = $returnable[$itemId] ?? 0;

            if ((int) $item['quantity'] > $available) {
                throw ValidationException::withMessages([
                    'items' => $available === 0
                        ? 'One of the selected items has already been returned or requested for return.'
                        : "You can return at most {$available} unit(s) of one of the selected items.",
                ]);
            }
        }

        return ($this->createRma)(
            order: $order,
            items: array_map(fn (array $item): array => [
                'order_item_id' => (int) $item['order_item_id'],
                'quantity' => (int) $item['quantity'],
            ], $items),
            reasonCategory: $reason,
            customerNotes: $notes,
            userId: $customer->id,
        );
    }

    public static function deliveredAt(Order $order): ?CarbonInterface
    {
        if ($order->status !== Order::STATUS_DELIVERED) {
            return null;
        }

        return $order->delivered_at ?? $order->updated_at;
    }

    public static function isWithinReturnWindow(Order $order): bool
    {
        $deliveredAt = self::deliveredAt($order);

        return $deliveredAt !== null && $deliveredAt->copy()->addDays(self::RETURN_WINDOW_DAYS)->isFuture();
    }

    /**
     * Units of each order item that can still be returned.
     *
     * @return array<int, int> order_item_id => quantity
     */
    public static function returnableQuantities(Order $order): array
    {
        $order->loadMissing('items');

        $alreadyRequested = RmaItem::query()
            ->whereHas('rma', fn ($q) => $q
                ->where('order_id', $order->id)
                ->whereNotIn('status', self::RELEASED_STATUSES))
            ->selectRaw('order_item_id, SUM(quantity_requested) as qty')
            ->groupBy('order_item_id')
            ->pluck('qty', 'order_item_id');

        $quantities = [];
        foreach ($order->items as $orderItem) {
            $quantities[(int) $orderItem->id] = max(0, (int) $orderItem->quantity - (int) ($alreadyRequested[$orderItem->id] ?? 0));
        }

        return $quantities;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Actions\CommitStockReservationAction;
use App\Modules\Inventory\Actions\ReleaseStockReservationAction;
use App\Modules\Inventory\Actions\ReserveStockForOrderAction;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Order\Actions\GenerateOrderBagTicketHtmlAction;
use App\Modules\Order\Actions\TransitionOrderStatusAction;
use App\Modules\Order\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

final class OrderAdminController extends Controller
{
    /**
     * Display a listing of orders with filters and counts.
     */
    public function index(Request $request): Response
    {
        $status = $request->input('status', 'all');
        $zone = $request->input('delivery_zone', 'all');
        $search = $request->input('search');

        $query = Order::query()->with(['items', 'district'])->latest('id');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($zone && $zone !== 'all') {
            $query->where('delivery_zone', $zone);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $orders = $query->paginate(15)->withQueryString();

        $counts = [
            'all' => Order::query()->count(),
            'pending' => Order::query()->where('status', Order::STATUS_PENDING)->count(),
            'confirmed' => Order::query()->where('status', Order::STATUS_CONFIRMED)->count(),
            'processing' => Order::query()->where('status', Order::STATUS_PROCESSING)->count(),
            'shipped' => Order::query()->where('status', Order::STATUS_HANDED_TO_COURIER)->count(),
            'delivered' => Order::query()->where('status', Order::STATUS_DELIVERED)->count(),
            'cancelled' => Order::query()->where('status', Order::STATUS_CANCELLED)->count(),
        ];

        return Inertia::render('Admin/Orders/OrderIndex', [
            'orders' => $orders,
            'filters' => [
                'status' => $status,
                'delivery_zone' => $zone,
                'search' => $search,
            ],
            'counts' => $counts,
        ]);
    }

    /**
     * Display the specified order with full audit history and items.
     */
    public function show(Order $order): Response
    {
        $order->loadMissing([
            'items.variant.product',
            'statusHistories.changedByUser',
            'division',
            'district',
            'thana',
            'user',
        ]);

        $allowedTransitions = Order::ALLOWED_TRANSITIONS[$order->status] ?? [];

        return Inertia::render('Admin/Orders/OrderDetail', [
            'order' => $order,
            'allowedTransitions' => $allowedTransitions,
        ]);
    }

    /**
     * Transition the lifecycle status of an order.
     */
    public function transitionStatus(
        Request $request,
        Order $order,
        TransitionOrderStatusAction $transitionAction,
        ReserveStockForOrderAction $reserveAction,
        CommitStockReservationAction $commitAction,
        ReleaseStockReservationAction $releaseAction
    ): RedirectResponse {
        $validated = $request->validate([
            'target_status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $targetStatus = $validated['target_status'];

        // Automatically reserve stock upon confirmation if not already reserved
        if ($targetStatus === Order::STATUS_CONFIRMED) {
            try {
                $reserveAction($order);
            } catch (InsufficientStockException $e) {
                return back()->withErrors([
                    'target_status' => $e->getMessage(),
                ]);
            }
        }

        $transitionAction(
            order: $order,
            targetStatus: $targetStatus,
            changedBy: $request->user(),
            reason: $validated['reason'] ?? null
        );

        // Inventory lifecycle integration
        if ($targetStatus === Order::STATUS_HANDED_TO_COURIER) {
            $commitAction($order);
        } elseif ($targetStatus === Order::STATUS_CANCELLED) {
            $releaseAction($order);
        }

        return back()->with('success', "Order [{$order->order_number}] transitioned to {$targetStatus} successfully.");
    }

    /**
     * Render printable Order Bag Tickets with barcodes for single or multiple orders.
     */
    public function printBagTickets(Request $request, GenerateOrderBagTicketHtmlAction $action): HttpResponse
    {
        $orderIdsInput = $request->input('order_ids');

        if (is_string($orderIdsInput)) {
            $orderIds = array_filter(array_map('intval', explode(',', $orderIdsInput)));
        } else {
            $orderIds = array_filter(array_map('intval', (array) $orderIdsInput));
        }

        if (empty($orderIds)) {
            return response('No valid order IDs provided for bag ticket printing.', 400);
        }

        $orders = Order::query()
            ->with(['items', 'district'])
            ->whereIn('id', $orderIds)
            ->latest('id')
            ->get()
            ->all();

        if (empty($orders)) {
            return response('No matching orders found.', 404);
        }

        // Increment ticket print count and timestamp for audit tracking
        foreach ($orders as $order) {
            $order->increment('ticket_print_count');
            $order->updateQuietly(['ticket_printed_at' => now()]);
            $order->ticket_print_count++;
        }

        $html = $action($orders);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * Bulk transition orders to a target lifecycle status (e.g. processing, handed_to_courier).
     */
    public function bulkTransition(
        Request $request,
        TransitionOrderStatusAction $transitionAction,
        ReserveStockForOrderAction $reserveAction,
        CommitStockReservationAction $commitAction,
        ReleaseStockReservationAction $releaseAction
    ): RedirectResponse {
        $validated = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
            'target_status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $targetStatus = $validated['target_status'];
        $reason = $validated['reason'] ?? "Bulk transitioned to {$targetStatus} from admin dashboard.";

        $orders = Order::query()
            ->whereIn('id', $validated['order_ids'])
            ->get();

        $successCount = 0;
        $failedCount = 0;

        foreach ($orders as $order) {
            if (! $order->canTransitionTo($targetStatus)) {
                $failedCount++;
                continue;
            }

            try {
                if ($targetStatus === Order::STATUS_CONFIRMED) {
                    $reserveAction($order);
                }

                $transitionAction(
                    order: $order,
                    targetStatus: $targetStatus,
                    changedBy: $request->user(),
                    reason: $reason
                );

                if ($targetStatus === Order::STATUS_HANDED_TO_COURIER) {
                    $commitAction($order);
                } elseif ($targetStatus === Order::STATUS_CANCELLED) {
                    $releaseAction($order);
                }

                $successCount++;
            } catch (InsufficientStockException $e) {
                $failedCount++;
            } catch (\Throwable $e) {
                $failedCount++;
            }
        }

        $label = str_replace('_', ' ', $targetStatus);
        $message = "{$successCount} order(s) successfully transitioned to '{$label}'.";
        if ($failedCount > 0) {
            $message .= " ({$failedCount} orders could not be transitioned).";
        }

        return back()->with('success', $message);
    }

    /**
     * Bulk confirm multiple pending orders at once.
     */
    public function bulkConfirm(
        Request $request,
        TransitionOrderStatusAction $transitionAction,
        ReserveStockForOrderAction $reserveAction
    ): RedirectResponse {
        $validated = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
        ]);

        $orders = Order::query()
            ->whereIn('id', $validated['order_ids'])
            ->where('status', Order::STATUS_PENDING)
            ->get();

        $confirmedCount = 0;
        $failedCount = 0;

        foreach ($orders as $order) {
            try {
                $reserveAction($order);
                $transitionAction(
                    order: $order,
                    targetStatus: Order::STATUS_CONFIRMED,
                    changedBy: $request->user(),
                    reason: 'Bulk confirmed from admin orders dashboard.'
                );
                $confirmedCount++;
            } catch (InsufficientStockException $e) {
                $failedCount++;
            }
        }

        $message = "{$confirmedCount} order(s) confirmed successfully.";
        if ($failedCount > 0) {
            $message .= " {$failedCount} order(s) could not be confirmed due to insufficient stock.";
        }

        return back()->with('success', $message);
    }
}

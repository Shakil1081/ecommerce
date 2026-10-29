<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Actions\CommitStockReservationAction;
use App\Modules\Inventory\Actions\ReleaseStockReservationAction;
use App\Modules\Inventory\Actions\ReserveStockForOrderAction;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Order\Actions\TransitionOrderStatusAction;
use App\Modules\Order\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
}

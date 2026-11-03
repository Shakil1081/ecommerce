<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\LogAuditTrailAction;
use App\Modules\Admin\Models\AuditLog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\CommitStockReservationAction;
use App\Modules\Inventory\Actions\ReleaseStockReservationAction;
use App\Modules\Inventory\Actions\ReserveStockForOrderAction;
use App\Modules\Inventory\Actions\RestockReturnedOrderAction;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Logistics\Actions\GenerateHandoverManifestHtmlAction;
use App\Modules\Logistics\Models\CourierGatewaySetting;
use App\Modules\Order\Actions\GenerateOrderBagTicketHtmlAction;
use App\Modules\Order\Actions\ScanAndHandoverToCourierAction;
use App\Modules\Order\Actions\ScanAndReceiveReturnAction;
use App\Modules\Order\Actions\TransitionOrderStatusAction;
use App\Modules\Order\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
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
        $status = (string) $request->input('status', 'all');
        $zone = (string) $request->input('delivery_zone', 'all');
        $search = $request->input('search');
        $courierFilter = (string) $request->input('courier', 'all');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $productId = (string) $request->input('product_id', 'all');
        $productSearch = $request->input('product_search');

        $query = Order::query()->with(['items', 'district'])->latest('id');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($zone && $zone !== 'all') {
            $query->where('delivery_zone', $zone);
        }

        if ($courierFilter && $courierFilter !== 'all') {
            if ($courierFilter === 'unbooked') {
                $query->whereNull('courier_consignment_id')->whereNull('courier_code');
            } else {
                $query->where('courier_code', $courierFilter);
            }
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($productId && $productId !== 'all') {
            $query->whereHas('items', function ($q) use ($productId): void {
                $q->where('product_id', $productId);
            });
        }

        if ($productSearch) {
            $query->whereHas('items', function ($q) use ($productSearch): void {
                $q->where('product_name', 'like', "%{$productSearch}%")
                    ->orWhere('sku', 'like', "%{$productSearch}%");
            });
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
            'returned' => Order::query()->where('status', Order::STATUS_RETURNED)->count(),
            'cancelled' => Order::query()->where('status', Order::STATUS_CANCELLED)->count(),
        ];

        $couriers = CourierGatewaySetting::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['code', 'name', 'is_sandbox', 'is_active']);

        if ($couriers->isEmpty()) {
            $couriers = CourierGatewaySetting::query()
                ->orderBy('sort_order')
                ->get(['code', 'name', 'is_sandbox', 'is_active']);
        }

        // Parcel dispatch analytics query (matching active date & product filters)
        $dispatchStatsQuery = Order::query();
        if ($dateFrom) {
            $dispatchStatsQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $dispatchStatsQuery->whereDate('created_at', '<=', $dateTo);
        }
        if ($productId && $productId !== 'all') {
            $dispatchStatsQuery->whereHas('items', fn ($q) => $q->where('product_id', $productId));
        }
        if ($productSearch) {
            $dispatchStatsQuery->whereHas('items', fn ($q) => $q->where('product_name', 'like', "%{$productSearch}%")->orWhere('sku', 'like', "%{$productSearch}%"));
        }

        $totalSentParcels = (clone $dispatchStatsQuery)
            ->where(function ($q): void {
                $q->whereNotNull('courier_consignment_id')
                    ->orWhereNotNull('courier_code')
                    ->orWhereIn('status', [
                        Order::STATUS_PROCESSING,
                        Order::STATUS_PICKED_AND_PACKED,
                        Order::STATUS_HANDED_TO_COURIER,
                        Order::STATUS_OUT_FOR_DELIVERY,
                        Order::STATUS_DELIVERED,
                        Order::STATUS_RETURNED,
                    ]);
            })
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->count();

        $courierBreakdown = [];
        foreach ($couriers as $courierSetting) {
            $code = $courierSetting->code;
            $sentCount = (clone $dispatchStatsQuery)->where('courier_code', $code)->count();
            $deliveredCount = (clone $dispatchStatsQuery)->where('courier_code', $code)->where('status', Order::STATUS_DELIVERED)->count();
            $returnedCount = (clone $dispatchStatsQuery)->where('courier_code', $code)->where('status', Order::STATUS_RETURNED)->count();
            $inTransitCount = (clone $dispatchStatsQuery)->where('courier_code', $code)->whereIn('status', [
                Order::STATUS_PROCESSING,
                Order::STATUS_PICKED_AND_PACKED,
                Order::STATUS_HANDED_TO_COURIER,
                Order::STATUS_OUT_FOR_DELIVERY,
            ])->count();

            $courierBreakdown[$code] = [
                'code' => $code,
                'name' => $courierSetting->name,
                'sent_count' => $sentCount,
                'in_transit_count' => $inTransitCount,
                'delivered_count' => $deliveredCount,
                'returned_count' => $returnedCount,
            ];
        }

        $unbookedCount = (clone $dispatchStatsQuery)
            ->whereNull('courier_consignment_id')
            ->whereNull('courier_code')
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED])
            ->count();

        $parcelSummary = [
            'total_sent' => $totalSentParcels,
            'unbooked_pending' => $unbookedCount,
            'by_courier' => $courierBreakdown,
        ];

        $products = Product::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->take(150)
            ->get();

        return Inertia::render('Admin/Orders/OrderIndex', [
            'orders' => $orders,
            'filters' => [
                'status' => $status,
                'delivery_zone' => $zone,
                'search' => $search,
                'courier' => $courierFilter,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'product_id' => $productId,
                'product_search' => $productSearch,
            ],
            'counts' => $counts,
            'couriers' => $couriers,
            'parcel_summary' => $parcelSummary,
            'products' => $products,
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

        $couriers = CourierGatewaySetting::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['code', 'name', 'is_sandbox', 'is_active']);

        if ($couriers->isEmpty()) {
            $couriers = CourierGatewaySetting::query()
                ->orderBy('sort_order')
                ->get(['code', 'name', 'is_sandbox', 'is_active']);
        }

        return Inertia::render('Admin/Orders/OrderDetail', [
            'order' => $order,
            'allowedTransitions' => $allowedTransitions,
            'couriers' => $couriers,
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
        ReleaseStockReservationAction $releaseAction,
        RestockReturnedOrderAction $restockAction
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

        $previousStatus = $order->status;

        $transitionAction(
            order: $order,
            targetStatus: $targetStatus,
            changedBy: $request->user(),
            reason: $validated['reason'] ?? null
        );

        app(LogAuditTrailAction::class)(
            event: AuditLog::EVENT_ORDER_STATUS_CHANGED,
            description: "Order [{$order->order_number}] status changed from {$previousStatus} to {$targetStatus}.".(! empty($validated['reason']) ? " Reason: {$validated['reason']}" : ''),
            auditable: $order,
            oldValues: ['status' => $previousStatus],
            newValues: ['status' => $targetStatus, 'reason' => $validated['reason'] ?? null],
            options: ['user' => $request->user()]
        );

        // Inventory lifecycle integration
        if ($targetStatus === Order::STATUS_HANDED_TO_COURIER) {
            $commitAction($order);
        } elseif ($targetStatus === Order::STATUS_CANCELLED) {
            $releaseAction($order);
        } elseif ($targetStatus === Order::STATUS_RETURNED) {
            $restockAction($order);
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
        ReleaseStockReservationAction $releaseAction,
        RestockReturnedOrderAction $restockAction
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

                $oldStatus = $order->status;
                $transitionAction(
                    order: $order,
                    targetStatus: $targetStatus,
                    changedBy: $request->user(),
                    reason: $reason
                );

                app(LogAuditTrailAction::class)(
                    event: AuditLog::EVENT_ORDER_STATUS_CHANGED,
                    description: "Bulk transitioned order [{$order->order_number}] from {$oldStatus} to {$targetStatus}.",
                    auditable: $order,
                    oldValues: ['status' => $oldStatus],
                    newValues: ['status' => $targetStatus],
                    options: ['user' => $request->user()]
                );

                if ($targetStatus === Order::STATUS_HANDED_TO_COURIER) {
                    $commitAction($order);
                } elseif ($targetStatus === Order::STATUS_CANCELLED) {
                    $releaseAction($order);
                } elseif ($targetStatus === Order::STATUS_RETURNED) {
                    $restockAction($order);
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
                app(LogAuditTrailAction::class)(
                    event: AuditLog::EVENT_ORDER_STATUS_CHANGED,
                    description: "Bulk confirmed pending order [{$order->order_number}] and reserved warehouse inventory.",
                    auditable: $order,
                    oldValues: ['status' => Order::STATUS_PENDING],
                    newValues: ['status' => Order::STATUS_CONFIRMED],
                    options: ['user' => $request->user()]
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

    /**
     * Display the barcode scanner return console for receiving returned courier parcels.
     */
    public function scanReturnsView(Request $request): Response
    {
        $today = Carbon::today();

        $todayReturnedCount = Order::query()
            ->where('status', Order::STATUS_RETURNED)
            ->whereDate('updated_at', $today)
            ->count();

        $totalReturnedCount = Order::query()
            ->where('status', Order::STATUS_RETURNED)
            ->count();

        $recentReturns = Order::query()
            ->with(['items', 'district'])
            ->where('status', Order::STATUS_RETURNED)
            ->latest('updated_at')
            ->limit(15)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'courier_code' => $order->courier_code ? strtoupper($order->courier_code) : 'N/A',
                'courier_tracking_code' => $order->courier_tracking_code,
                'courier_consignment_id' => $order->courier_consignment_id,
                'total_bdt' => $order->total_bdt,
                'items_count' => $order->items->sum('quantity'),
                'returned_at' => $order->updated_at?->format('d M Y, h:i A') ?? 'N/A',
            ]);

        return Inertia::render('Admin/Orders/ScanReturnsConsole', [
            'stats' => [
                'today_returned_count' => $todayReturnedCount,
                'total_returned_count' => $totalReturnedCount,
            ],
            'recentReturns' => $recentReturns,
        ]);
    }

    /**
     * Process a scanned barcode / tracking code, transition order to returned, and restock warehouse inventory.
     */
    public function processBarcodeScanReturn(
        Request $request,
        ScanAndReceiveReturnAction $action
    ): JsonResponse {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $action(
            rawBarcode: $validated['barcode'],
            scannedBy: $request->user(),
            notes: $validated['notes'] ?? null,
        );

        return response()->json($result);
    }

    /**
     * Display the barcode scanner console for handing over parcels to courier.
     */
    public function scanHandoverView(Request $request): Response
    {
        $today = Carbon::today();

        $todayHandedOverCount = Order::query()
            ->where('status', Order::STATUS_HANDED_TO_COURIER)
            ->where(function ($q) use ($today) {
                $q->whereDate('shipped_at', $today)
                    ->orWhereDate('updated_at', $today);
            })
            ->count();

        $totalWithCourierCount = Order::query()
            ->whereIn('status', [Order::STATUS_HANDED_TO_COURIER, Order::STATUS_OUT_FOR_DELIVERY])
            ->count();

        $pendingDispatchCount = Order::query()
            ->whereIn('status', [Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING, Order::STATUS_PICKED_AND_PACKED])
            ->count();

        $availableCouriers = CourierGatewaySetting::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['code', 'name', 'is_sandbox', 'is_active'])
            ->map(fn (CourierGatewaySetting $c): array => [
                'code' => $c->code,
                'name' => $c->name,
                'is_sandbox' => (bool) $c->is_sandbox,
                'is_active' => (bool) $c->is_active,
            ])
            ->values()
            ->all();

        $recentHandovers = Order::query()
            ->with(['items', 'district'])
            ->where('status', Order::STATUS_HANDED_TO_COURIER)
            ->latest('shipped_at')
            ->limit(15)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'courier_code' => $order->courier_code ? strtoupper($order->courier_code) : 'N/A',
                'courier_tracking_code' => $order->courier_tracking_code,
                'courier_consignment_id' => $order->courier_consignment_id,
                'delivery_zone' => $order->delivery_zone,
                'district_name' => $order->district?->name_en ?? 'N/A',
                'total_bdt' => $order->total_bdt,
                'payable_cod_amount_bdt' => $order->payable_cod_amount_bdt,
                'items_count' => $order->items->sum('quantity'),
                'shipped_at' => $order->shipped_at?->format('d M Y, h:i A') ?? $order->updated_at?->format('d M Y, h:i A') ?? 'N/A',
            ]);

        return Inertia::render('Admin/Orders/ScanHandoverConsole', [
            'stats' => [
                'today_handed_over_count' => $todayHandedOverCount,
                'total_with_courier_count' => $totalWithCourierCount,
                'pending_dispatch_count' => $pendingDispatchCount,
            ],
            'availableCouriers' => $availableCouriers,
            'recentHandovers' => $recentHandovers,
        ]);
    }

    /**
     * Process a scanned barcode / tracking code, transition order to handed_to_courier, and commit stock.
     */
    public function processBarcodeScanHandover(
        Request $request,
        ScanAndHandoverToCourierAction $action
    ): JsonResponse {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:255'],
            'courier_code' => ['nullable', 'string', 'max:50'],
            'auto_book' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $action(
            rawBarcode: $validated['barcode'],
            courierCode: $validated['courier_code'] ?? null,
            autoBook: (bool) ($validated['auto_book'] ?? true),
            scannedBy: $request->user(),
            notes: $validated['notes'] ?? null,
        );

        return response()->json($result);
    }

    /**
     * Render printable Courier Handover Manifest / Pickup Challan HTML.
     */
    public function printHandoverManifest(
        Request $request,
        GenerateHandoverManifestHtmlAction $action
    ): HttpResponse {
        $rawOrderIds = $request->input('order_ids', []);
        if (is_string($rawOrderIds)) {
            $orderIds = array_filter(array_map('intval', explode(',', $rawOrderIds)));
        } else {
            $orderIds = array_map('intval', (array) $rawOrderIds);
        }

        $courierCode = $request->input('courier_code') ? (string) $request->input('courier_code') : null;

        $orders = Order::query()
            ->with(['items', 'district'])
            ->whereIn('id', $orderIds)
            ->latest('id')
            ->get()
            ->all();

        $html = $action($orders, $courierCode);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}

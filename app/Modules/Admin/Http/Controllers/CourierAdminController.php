<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Logistics\Actions\ReconcileCodPaymentAction;
use App\Modules\Logistics\Actions\TestCourierConnectionAction;
use App\Modules\Logistics\Models\CourierDisbursement;
use App\Modules\Logistics\Models\CourierGatewaySetting;
use App\Modules\Logistics\Models\ShippingRate;
use App\Modules\Logistics\Models\ShippingZone;
use App\Modules\Order\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourierAdminController extends Controller
{
    /**
     * Display courier gateway configuration dashboard.
     */
    public function index(): Response
    {
        $settings = CourierGatewaySetting::ordered()->get()->map(function (CourierGatewaySetting $setting) {
            return [
                'id' => $setting->id,
                'ulid' => $setting->ulid,
                'code' => $setting->code,
                'name' => $setting->name,
                'is_active' => $setting->is_active,
                'is_sandbox' => $setting->is_sandbox,
                'base_url' => $setting->base_url,
                'webhook_secret' => $setting->webhook_secret,
                'supported_features' => $setting->supported_features ?? [],
                'sort_order' => $setting->sort_order,
                'last_tested_at' => $setting->last_tested_at?->format('Y-m-d H:i:s'),
                'last_test_status' => $setting->last_test_status,
                'last_test_message' => $setting->last_test_message,
                'is_configured' => $setting->isConfigured(),
                'credentials' => $setting->getMaskedCredentials(),
                'webhook_url' => url("/webhooks/courier/{$setting->code}"),
            ];
        });

        return Inertia::render('Admin/Settings/CourierSettings', [
            'couriers' => $settings,
        ]);
    }

    /**
     * Update courier gateway configuration and credentials.
     */
    public function update(Request $request, CourierGatewaySetting $courierGatewaySetting): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'is_sandbox' => ['required', 'boolean'],
            'base_url' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'credentials' => ['nullable', 'array'],
        ]);

        $incomingCreds = $validated['credentials'] ?? [];
        $existingCreds = $courierGatewaySetting->credentials ?? [];

        // Retain existing credentials if masked bullets are sent
        $mergedCreds = [];
        foreach ($incomingCreds as $key => $val) {
            if (is_string($val) && str_contains($val, '••••')) {
                $mergedCreds[$key] = $existingCreds[$key] ?? '';
            } else {
                $mergedCreds[$key] = $val;
            }
        }

        $courierGatewaySetting->update([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'],
            'is_sandbox' => $validated['is_sandbox'],
            'base_url' => $validated['base_url'] ?? null,
            'webhook_secret' => $validated['webhook_secret'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'credentials' => $mergedCreds,
        ]);

        return redirect()->back()->with('success', "{$courierGatewaySetting->name} settings saved successfully.");
    }

    /**
     * Pre-flight courier API handshake verification endpoint.
     */
    public function testConnection(Request $request, TestCourierConnectionAction $action): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'credentials' => ['nullable', 'array'],
            'is_sandbox' => ['nullable', 'boolean'],
            'base_url' => ['nullable', 'string'],
        ]);

        $code = $validated['code'];
        $setting = CourierGatewaySetting::where('code', $code)->first();

        $incomingCreds = $validated['credentials'] ?? [];
        $existingCreds = $setting?->credentials ?? [];

        $effectiveCreds = [];
        foreach ($incomingCreds as $key => $val) {
            if (is_string($val) && str_contains($val, '••••')) {
                $effectiveCreds[$key] = $existingCreds[$key] ?? '';
            } else {
                $effectiveCreds[$key] = $val;
            }
        }

        $sandbox = array_key_exists('is_sandbox', $validated)
            ? (bool) $validated['is_sandbox']
            : ($setting?->is_sandbox ?? true);

        $baseUrl = array_key_exists('base_url', $validated)
            ? (! empty($validated['base_url']) ? (string) $validated['base_url'] : '')
            : ($setting?->base_url ?? null);

        $result = $action(
            courier: $setting ?? $code,
            credentials: ! empty($effectiveCreds) ? $effectiveCreds : null,
            sandbox: $sandbox,
            baseUrl: $baseUrl,
        );

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'latency_ms' => $result->latencyMs,
            'details' => $result->details,
        ]);
    }

    /**
     * Display dynamic shipping zones and rate management page.
     */
    public function zones(): Response
    {
        $zones = ShippingZone::with(['rates' => fn ($q) => $q->whereNull('courier_code')])
            ->ordered()
            ->get()
            ->map(function (ShippingZone $zone) {
                $rate = $zone->rates->first();

                return [
                    'id' => $zone->id,
                    'code' => $zone->code,
                    'name' => $zone->name,
                    'description' => $zone->description,
                    'is_active' => $zone->is_active,
                    'estimated_days_min' => $zone->estimated_days_min,
                    'estimated_days_max' => $zone->estimated_days_max,
                    'rate' => [
                        'base_rate_bdt' => $rate ? (string) $rate->base_rate_bdt : '70.0000',
                        'base_weight_kg' => $rate ? (string) $rate->base_weight_kg : '1.000',
                        'extra_rate_per_kg_bdt' => $rate ? (string) $rate->extra_rate_per_kg_bdt : '20.0000',
                        'free_shipping_threshold_bdt' => $rate ? (string) $rate->free_shipping_threshold_bdt : null,
                    ],
                ];
            });

        return Inertia::render('Admin/Logistics/ShippingZones', [
            'zones' => $zones,
        ]);
    }

    /**
     * Update dynamic zone pricing and weight tiers.
     */
    public function updateZoneRate(Request $request, ShippingZone $zone): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'estimated_days_min' => ['required', 'integer', 'min:1'],
            'estimated_days_max' => ['required', 'integer', 'min:1'],
            'base_rate_bdt' => ['required', 'numeric', 'min:0'],
            'base_weight_kg' => ['required', 'numeric', 'min:0.1'],
            'extra_rate_per_kg_bdt' => ['required', 'numeric', 'min:0'],
            'free_shipping_threshold_bdt' => ['nullable', 'numeric', 'min:0'],
        ]);

        $zone->update([
            'name' => $validated['name'],
            'estimated_days_min' => $validated['estimated_days_min'],
            'estimated_days_max' => $validated['estimated_days_max'],
        ]);

        ShippingRate::updateOrCreate(
            [
                'zone_id' => $zone->id,
                'courier_code' => null,
            ],
            [
                'base_rate_bdt' => $validated['base_rate_bdt'],
                'base_weight_kg' => $validated['base_weight_kg'],
                'extra_rate_per_kg_bdt' => $validated['extra_rate_per_kg_bdt'],
                'free_shipping_threshold_bdt' => $validated['free_shipping_threshold_bdt'] ?? null,
                'is_active' => true,
            ]
        );

        return redirect()->back()->with('success', "Zone {$zone->name} updated successfully.");
    }

    /**
     * Display COD cash reconciliation dashboard.
     */
    public function codReconciliation(): Response
    {
        $metrics = [
            'total_collected_bdt' => (string) (CourierDisbursement::sum('total_collected_bdt') ?: '0.0000'),
            'total_fees_bdt' => (string) (CourierDisbursement::sum('total_courier_fee_bdt') ?: '0.0000'),
            'net_disbursed_bdt' => (string) (CourierDisbursement::sum('net_disbursed_bdt') ?: '0.0000'),
            'discrepancy_count' => CourierDisbursement::discrepancy()->count(),
            'reconciled_count' => CourierDisbursement::reconciled()->count(),
        ];

        $disbursements = CourierDisbursement::with('items')
            ->latest('disbursement_date')
            ->paginate(15);

        return Inertia::render('Admin/Logistics/CodReconciliation', [
            'metrics' => $metrics,
            'disbursements' => $disbursements,
        ]);
    }

    /**
     * Store new courier disbursement statement and reconcile.
     */
    public function storeDisbursement(Request $request, ReconcileCodPaymentAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'courier_code' => ['required', 'string'],
            'disbursement_date' => ['required', 'date'],
            'reference_invoice' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.consignment_id' => ['required', 'string'],
            'items.*.order_number' => ['nullable', 'string'],
            'items.*.collected_amount_bdt' => ['required', 'numeric', 'min:0'],
            'items.*.courier_fee_bdt' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $action(
            courierCode: $validated['courier_code'],
            disbursementDate: $validated['disbursement_date'],
            referenceInvoice: $validated['reference_invoice'] ?? null,
            itemsData: $validated['items'],
            notes: $validated['notes'] ?? null,
        );

        return redirect()->back()->with('success', 'Courier disbursement recorded and reconciled successfully.');
    }

    /**
     * Display comprehensive Courier Delivery & COD Settlement Command Center.
     */
    public function deliveryOverview(Request $request): Response
    {
        $statusFilter = (string) $request->input('status', 'all');
        $courierFilter = (string) $request->input('courier', 'all');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');

        $allOrders = Order::query()->with(['items', 'district'])->get();

        // 1. Core KPIs
        $totalBooked = $allOrders->filter(fn (Order $o): bool => ! empty($o->courier_consignment_id) || ! empty($o->courier_tracking_code))->count();
        $totalDispatched = $allOrders->filter(fn (Order $o): bool => in_array($o->status, [Order::STATUS_HANDED_TO_COURIER, Order::STATUS_OUT_FOR_DELIVERY, Order::STATUS_DELIVERED], true))->count();
        $deliveredCount = $allOrders->where('status', Order::STATUS_DELIVERED)->count();
        $returnedCount = $allOrders->where('status', Order::STATUS_RETURNED)->count();
        $inTransitCount = $allOrders->filter(fn (Order $o): bool => in_array($o->status, [Order::STATUS_HANDED_TO_COURIER, Order::STATUS_OUT_FOR_DELIVERY], true))->count();
        $pendingQueueCount = $allOrders->filter(fn (Order $o): bool => in_array($o->status, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING, Order::STATUS_PICKED_AND_PACKED], true))->count();

        $resolvedTotal = $deliveredCount + $returnedCount;
        $deliverySuccessRate = $resolvedTotal > 0 ? round(($deliveredCount / $resolvedTotal) * 100, 1) : 0.0;
        $returnRate = $resolvedTotal > 0 ? round(($returnedCount / $resolvedTotal) * 100, 1) : 0.0;

        $totalOrderAmountBdt = (float) $allOrders->sum('total_bdt');
        $activeOrders = $allOrders->filter(fn (Order $o): bool => ! in_array($o->status, [Order::STATUS_CANCELLED, Order::STATUS_RETURNED], true));
        $activeOrderAmountBdt = (float) $activeOrders->sum('total_bdt');

        $totalCourierChargeBdt = (float) $allOrders->sum('shipping_fee_bdt');
        $estimatedCourierChargeBdt = $totalBooked * 70.0; // fallback standard rate

        $codCollectedBdt = (float) $allOrders->where('status', Order::STATUS_DELIVERED)->sum('payable_cod_amount_bdt');
        $codPendingInTransitBdt = (float) $allOrders->filter(fn (Order $o): bool => in_array($o->status, [Order::STATUS_HANDED_TO_COURIER, Order::STATUS_OUT_FOR_DELIVERY], true))->sum('payable_cod_amount_bdt');
        $codPendingQueueBdt = (float) $allOrders->filter(fn (Order $o): bool => in_array($o->status, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING, Order::STATUS_PICKED_AND_PACKED], true))->sum('payable_cod_amount_bdt');
        $totalCodPendingBdt = (float) $activeOrders->sum('payable_cod_amount_bdt');
        $parcelsPendingSettlement = $activeOrders->where('payment_status', Order::PAYMENT_PENDING)->count();

        $metrics = [
            'total_booked' => $totalBooked,
            'total_dispatched' => $totalDispatched,
            'delivered_count' => $deliveredCount,
            'delivery_success_rate' => $deliverySuccessRate,
            'returned_count' => $returnedCount,
            'return_rate' => $returnRate,
            'in_transit_count' => $inTransitCount,
            'pending_queue_count' => $pendingQueueCount,
            'total_order_amount_bdt' => $totalOrderAmountBdt,
            'active_order_amount_bdt' => $activeOrderAmountBdt,
            'total_courier_charge_bdt' => $totalCourierChargeBdt > 0 ? $totalCourierChargeBdt : $estimatedCourierChargeBdt,
            'cod_collected_bdt' => $codCollectedBdt,
            'cod_pending_in_transit_bdt' => $codPendingInTransitBdt,
            'cod_pending_queue_bdt' => $codPendingQueueBdt,
            'total_cod_pending_bdt' => $totalCodPendingBdt,
            'parcels_pending_settlement' => $parcelsPendingSettlement,
        ];

        // 2. Breakdown per Courier Partner
        $courierSettings = CourierGatewaySetting::ordered()->get();
        $courierBreakdown = [];
        foreach ($courierSettings as $setting) {
            $code = $setting->code;
            $cOrders = $allOrders->filter(fn (Order $o): bool => strtolower((string) $o->courier_code) === strtolower($code));
            $cDelivered = $cOrders->where('status', Order::STATUS_DELIVERED)->count();
            $cReturned = $cOrders->where('status', Order::STATUS_RETURNED)->count();
            $cInTransit = $cOrders->filter(fn (Order $o): bool => in_array($o->status, [Order::STATUS_HANDED_TO_COURIER, Order::STATUS_OUT_FOR_DELIVERY], true))->count();
            $cResolved = $cDelivered + $cReturned;
            $cSuccessRate = $cResolved > 0 ? round(($cDelivered / $cResolved) * 100, 1) : 0.0;

            $courierBreakdown[$code] = [
                'code' => $code,
                'name' => $setting->name,
                'is_active' => (bool) $setting->is_active,
                'is_sandbox' => (bool) $setting->is_sandbox,
                'booked_count' => $cOrders->count(),
                'in_transit_count' => $cInTransit,
                'delivered_count' => $cDelivered,
                'returned_count' => $cReturned,
                'success_rate' => $cSuccessRate,
                'cod_collectible_bdt' => (float) $cOrders->sum('payable_cod_amount_bdt'),
                'cod_collected_bdt' => (float) $cOrders->where('status', Order::STATUS_DELIVERED)->sum('payable_cod_amount_bdt'),
            ];
        }

        // 3. Zone Breakdown
        $zoneBreakdown = [
            'inside_dhaka' => [
                'name' => 'Inside Dhaka',
                'count' => $allOrders->where('delivery_zone', 'inside_dhaka')->count(),
                'total_cod' => (float) $allOrders->where('delivery_zone', 'inside_dhaka')->sum('payable_cod_amount_bdt'),
            ],
            'dhaka_suburbs' => [
                'name' => 'Dhaka Suburbs',
                'count' => $allOrders->where('delivery_zone', 'dhaka_suburbs')->count(),
                'total_cod' => (float) $allOrders->where('delivery_zone', 'dhaka_suburbs')->sum('payable_cod_amount_bdt'),
            ],
            'outside_dhaka' => [
                'name' => 'Outside Dhaka',
                'count' => $allOrders->where('delivery_zone', 'outside_dhaka')->count(),
                'total_cod' => (float) $allOrders->where('delivery_zone', 'outside_dhaka')->sum('payable_cod_amount_bdt'),
            ],
        ];

        // 4. Filterable List Query
        $query = Order::query()->with(['items', 'district'])->latest('id');

        if ($statusFilter && $statusFilter !== 'all') {
            if ($statusFilter === 'in_transit') {
                $query->whereIn('status', [Order::STATUS_HANDED_TO_COURIER, Order::STATUS_OUT_FOR_DELIVERY]);
            } elseif ($statusFilter === 'pending_queue') {
                $query->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING, Order::STATUS_PICKED_AND_PACKED]);
            } elseif ($statusFilter === 'unbooked') {
                $query->whereNull('courier_consignment_id')->whereNull('courier_tracking_code');
            } else {
                $query->where('status', $statusFilter);
            }
        }

        if ($courierFilter && $courierFilter !== 'all') {
            $query->where('courier_code', $courierFilter);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('courier_tracking_code', 'like', "%{$search}%")
                    ->orWhere('courier_consignment_id', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $parcels = $query->paginate(20)->through(fn (Order $order): array => [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'courier_code' => $order->courier_code ? strtoupper($order->courier_code) : 'N/A',
            'courier_tracking_code' => $order->courier_tracking_code,
            'courier_consignment_id' => $order->courier_consignment_id,
            'delivery_zone' => $order->delivery_zone,
            'district_name' => $order->district?->name_en ?? 'N/A',
            'delivery_address' => $order->delivery_address,
            'total_bdt' => $order->total_bdt,
            'shipping_fee_bdt' => $order->shipping_fee_bdt,
            'payable_cod_amount_bdt' => $order->payable_cod_amount_bdt,
            'items_count' => $order->items->sum('quantity'),
            'created_at' => $order->created_at->format('d M Y, h:i A'),
            'shipped_at' => $order->shipped_at?->format('d M Y, h:i A') ?? 'N/A',
            'delivered_at' => $order->delivered_at?->format('d M Y, h:i A') ?? 'N/A',
        ]);

        return Inertia::render('Admin/Logistics/DeliveryOverview', [
            'metrics' => $metrics,
            'courierBreakdown' => $courierBreakdown,
            'zoneBreakdown' => $zoneBreakdown,
            'parcels' => $parcels,
            'filters' => [
                'status' => $statusFilter,
                'courier' => $courierFilter,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'search' => $search,
            ],
            'availableCouriers' => $courierSettings->map(fn (CourierGatewaySetting $s): array => [
                'code' => $s->code,
                'name' => $s->name,
            ])->values()->all(),
        ]);
    }

    /**
     * Export delivery overview & settlement statement to CSV.
     */
    public function exportDeliveryCsv(Request $request): StreamedResponse
    {
        $statusFilter = (string) $request->input('status', 'all');
        $courierFilter = (string) $request->input('courier', 'all');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');

        $query = Order::query()->with(['items', 'district'])->latest('id');

        if ($statusFilter && $statusFilter !== 'all') {
            if ($statusFilter === 'in_transit') {
                $query->whereIn('status', [Order::STATUS_HANDED_TO_COURIER, Order::STATUS_OUT_FOR_DELIVERY]);
            } elseif ($statusFilter === 'pending_queue') {
                $query->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING, Order::STATUS_PICKED_AND_PACKED]);
            } else {
                $query->where('status', $statusFilter);
            }
        }

        if ($courierFilter && $courierFilter !== 'all') {
            $query->where('courier_code', $courierFilter);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('courier_tracking_code', 'like', "%{$search}%")
                    ->orWhere('courier_consignment_id', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $orders = $query->get();
        $filename = 'courier_delivery_settlement_'.date('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($orders): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'SL',
                'Order Number',
                'Courier',
                'Tracking Code',
                'Consignment ID',
                'Customer Name',
                'Customer Phone',
                'District',
                'Zone',
                'Order Status',
                'Payment Status',
                'Total BDT',
                'Shipping Fee BDT',
                'COD Amount BDT',
                'Created At',
                'Dispatched At',
                'Delivered At',
            ]);

            foreach ($orders as $index => $order) {
                fputcsv($handle, [
                    $index + 1,
                    $order->order_number,
                    $order->courier_code ? strtoupper($order->courier_code) : 'N/A',
                    $order->courier_tracking_code ?? 'N/A',
                    $order->courier_consignment_id ?? 'N/A',
                    $order->customer_name,
                    $order->customer_phone,
                    $order->district?->name_en ?? 'N/A',
                    $order->delivery_zone,
                    $order->status,
                    $order->payment_status,
                    $order->total_bdt,
                    $order->shipping_fee_bdt,
                    $order->payable_cod_amount_bdt,
                    $order->created_at->format('Y-m-d H:i:s'),
                    $order->shipped_at?->format('Y-m-d H:i:s') ?? 'N/A',
                    $order->delivered_at?->format('Y-m-d H:i:s') ?? 'N/A',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

        $result = $action(
            courier: $setting ?? $code,
            credentials: ! empty($effectiveCreds) ? $effectiveCreds : null,
            sandbox: (bool) ($validated['is_sandbox'] ?? true),
            baseUrl: $validated['base_url'] ?? null,
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
}

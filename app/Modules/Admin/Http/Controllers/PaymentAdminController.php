<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payment\Actions\TestGatewayConnectionAction;
use App\Modules\Payment\Models\PaymentGatewaySetting;
use App\Modules\Payment\Services\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentAdminController extends Controller
{
    /**
     * Display list of payment gateways and their configuration state.
     */
    public function index(PaymentGatewayManager $manager): Response
    {
        // Seed initial default gateway records if table is fresh
        $defaultGateways = [
            PaymentGatewaySetting::CODE_BKASH => ['name' => 'bKash Tokenized Checkout', 'sort_order' => 1, 'is_active' => false],
            PaymentGatewaySetting::CODE_NAGAD => ['name' => 'Nagad Digital Payment', 'sort_order' => 2, 'is_active' => false],
            PaymentGatewaySetting::CODE_SSLCOMMERZ => ['name' => 'SSLCommerz Hosted Checkout', 'sort_order' => 3, 'is_active' => false],
            PaymentGatewaySetting::CODE_COD => ['name' => 'Cash on Delivery (COD)', 'sort_order' => 4, 'is_active' => true],
            PaymentGatewaySetting::CODE_BANK_TRANSFER => ['name' => 'Manual Bank Transfer', 'sort_order' => 5, 'is_active' => false],
        ];

        foreach ($defaultGateways as $code => $defaults) {
            PaymentGatewaySetting::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $defaults['name'],
                    'sort_order' => $defaults['sort_order'],
                    'is_active' => $defaults['is_active'],
                    'is_sandbox' => true,
                    'surcharge_percentage' => 0.00,
                ]
            );
        }

        $settings = PaymentGatewaySetting::query()->ordered()->get();

        $gateways = $settings->map(function (PaymentGatewaySetting $s) {
            $webhooks = match ($s->code) {
                PaymentGatewaySetting::CODE_BKASH => [
                    'callback_url' => route('payment.bkash.callback'),
                ],
                PaymentGatewaySetting::CODE_NAGAD => [
                    'callback_url' => route('payment.nagad.callback'),
                ],
                PaymentGatewaySetting::CODE_SSLCOMMERZ => [
                    'success_url' => route('payment.sslcommerz.success'),
                    'fail_url' => route('payment.sslcommerz.fail'),
                    'cancel_url' => route('payment.sslcommerz.cancel'),
                    'ipn_url' => route('payment.sslcommerz.ipn'),
                ],
                default => [],
            };

            return [
                'id' => $s->id,
                'ulid' => $s->ulid,
                'code' => $s->code,
                'name' => $s->name,
                'is_active' => $s->is_active,
                'is_sandbox' => $s->is_sandbox,
                'surcharge_percentage' => (float) $s->surcharge_percentage,
                'min_order_amount' => $s->min_order_amount ? (float) $s->min_order_amount : null,
                'max_order_amount' => $s->max_order_amount ? (float) $s->max_order_amount : null,
                'sort_order' => $s->sort_order,
                'instructions' => $s->instructions,
                'is_configured' => $s->isConfigured(),
                'masked_credentials' => $s->getMaskedCredentials(),
                'last_tested_at' => $s->last_tested_at?->diffForHumans(),
                'last_test_status' => $s->last_test_status,
                'last_test_message' => $s->last_test_message,
                'webhook_urls' => $webhooks,
            ];
        });

        return Inertia::render('Admin/Settings/PaymentGatewaySettings', [
            'gateways' => $gateways,
        ]);
    }

    /**
     * Save/update payment gateway credentials and preferences.
     */
    public function save(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'is_sandbox' => ['required', 'boolean'],
            'credentials' => ['nullable', 'array'],
            'surcharge_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_order_amount' => ['nullable', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        $setting = PaymentGatewaySetting::firstOrNew(['code' => $validated['code']]);

        // Merge incoming credentials with existing decrypted ones, avoiding overwrite from masked placeholder
        $existingCredentials = is_array($setting->credentials) ? $setting->credentials : [];
        $incomingCredentials = $validated['credentials'] ?? [];
        $finalCredentials = $existingCredentials;

        foreach ($incomingCredentials as $key => $val) {
            if (is_string($val)) {
                // If the user didn't modify a masked secret (contains bullets), preserve existing
                if (str_contains($val, '••••')) {
                    continue;
                }
                $finalCredentials[$key] = trim($val);
            }
        }

        $setting->fill([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'],
            'is_sandbox' => $validated['is_sandbox'],
            'credentials' => $finalCredentials,
            'surcharge_percentage' => $validated['surcharge_percentage'] ?? 0.00,
            'min_order_amount' => $validated['min_order_amount'] ?? null,
            'max_order_amount' => $validated['max_order_amount'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'instructions' => $validated['instructions'] ?? null,
        ]);

        $setting->save();

        return redirect()->back()->with('success', "{$setting->name} settings saved successfully.");
    }

    /**
     * Perform pre-flight API handshake test with the payment gateway.
     */
    public function testConnection(Request $request, TestGatewayConnectionAction $testAction): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'credentials' => ['nullable', 'array'],
            'is_sandbox' => ['nullable', 'boolean'],
        ]);

        $code = (string) $validated['code'];
        $sandbox = (bool) ($validated['is_sandbox'] ?? true);

        // Merge submitted credentials with existing if partial/masked
        $setting = PaymentGatewaySetting::where('code', $code)->first();
        $existingCredentials = is_array($setting?->credentials) ? $setting->credentials : [];
        $incomingCredentials = $validated['credentials'] ?? [];
        $finalCredentials = $existingCredentials;

        foreach ($incomingCredentials as $key => $val) {
            if (is_string($val) && ! str_contains($val, '••••') && $val !== '') {
                $finalCredentials[$key] = trim($val);
            }
        }

        $result = $testAction($code, $finalCredentials, $sandbox);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'latency_ms' => $result->latencyMs,
            'details' => $result->details,
        ]);
    }
}

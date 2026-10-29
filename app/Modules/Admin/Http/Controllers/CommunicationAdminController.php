<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Models\Cart;
use App\Modules\Communication\Actions\ConfigureDynamicMailerAction;
use App\Modules\Communication\Actions\SendTestEmailAction;
use App\Modules\Communication\Actions\TestVoiceCallAction;
use App\Modules\Communication\Models\MailSetting;
use App\Modules\Communication\Models\SmsGatewaySetting;
use App\Modules\Communication\Models\VoiceCallingSetting;
use App\Modules\Communication\Models\VoiceCallLog;
use App\Modules\Communication\Services\SmsGatewayManager;
use App\Modules\Marketing\Actions\ProcessCartRecoverySequencesAction;
use App\Modules\Marketing\Models\CartRecoveryLog;
use App\Modules\Order\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommunicationAdminController extends Controller
{
    /**
     * Display dynamic SMTP mail settings back-office view.
     */
    public function mailSettings(): Response
    {
        $setting = MailSetting::firstOrCreate(
            [],
            [
                'driver' => 'smtp',
                'host' => 'smtp.mailtrap.io',
                'port' => 2525,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
                'from_address' => 'noreply@ecommerce.test',
                'from_name' => 'E-Commerce Store',
                'is_active' => false,
            ]
        );

        return Inertia::render('Admin/Communication/MailSettings', [
            'mailSetting' => [
                'id' => $setting->id,
                'ulid' => $setting->ulid,
                'driver' => $setting->driver,
                'host' => $setting->host,
                'port' => $setting->port,
                'encryption' => $setting->encryption,
                'username' => $setting->username,
                'has_password' => ! empty($setting->password),
                'masked_password' => $setting->getMaskedPassword(),
                'from_address' => $setting->from_address,
                'from_name' => $setting->from_name,
                'is_active' => $setting->is_active,
                'is_configured' => $setting->isConfigured(),
                'last_tested_at' => $setting->last_tested_at?->format('d M Y, h:i A'),
                'last_test_status' => $setting->last_test_status,
                'last_test_message' => $setting->last_test_message,
            ],
        ]);
    }

    /**
     * Update dynamic SMTP mail settings.
     */
    public function updateMailSettings(Request $request, ConfigureDynamicMailerAction $configureAction): RedirectResponse
    {
        $validated = $request->validate([
            'driver' => ['required', 'string', 'max:50'],
            'host' => ['required', 'string', 'max:190'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:190'],
            'from_name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);

        $setting = MailSetting::first();
        if (! $setting) {
            $setting = new MailSetting;
        }

        $setting->driver = $validated['driver'];
        $setting->host = $validated['host'];
        $setting->port = (int) $validated['port'];
        $setting->encryption = $validated['encryption'] ?? 'tls';
        $setting->username = $validated['username'] ?? '';

        if (! empty($validated['password'])) {
            $setting->password = $validated['password'];
        }

        $setting->from_address = $validated['from_address'];
        $setting->from_name = $validated['from_name'];
        $setting->is_active = (bool) $validated['is_active'];
        $setting->save();

        if ($setting->is_active) {
            $configureAction($setting);
        }

        return redirect()->back()->with('success', 'Dynamic SMTP mail settings updated successfully.');
    }

    /**
     * Test SMTP connection by sending a verification email.
     */
    public function testMailSettings(Request $request, SendTestEmailAction $testAction): JsonResponse
    {
        $validated = $request->validate([
            'recipient_email' => ['required', 'email'],
        ]);

        $result = $testAction($validated['recipient_email']);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'latency_ms' => $result->latencyMs,
        ], $result->success ? 200 : 422);
    }

    /**
     * Display SMS gateway settings back-office view.
     */
    public function smsSettings(): Response
    {
        $defaults = [
            SmsGatewaySetting::CODE_GREENWEB => [
                'name' => 'Greenweb SMS Gateway',
                'sort_order' => 1,
                'credentials' => ['token' => ''],
            ],
            SmsGatewaySetting::CODE_REVE => [
                'name' => 'Reve Systems SMS',
                'sort_order' => 2,
                'credentials' => ['api_key' => '', 'secret_key' => ''],
            ],
            SmsGatewaySetting::CODE_TWILIO => [
                'name' => 'Twilio SMS Global',
                'sort_order' => 3,
                'credentials' => ['account_sid' => '', 'auth_token' => '', 'from_number' => ''],
            ],
        ];

        foreach ($defaults as $code => $data) {
            SmsGatewaySetting::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $data['name'],
                    'sort_order' => $data['sort_order'],
                    'is_active' => false,
                    'is_sandbox' => true,
                    'credentials' => $data['credentials'],
                ]
            );
        }

        $gateways = SmsGatewaySetting::query()->ordered()->get()->map(function (SmsGatewaySetting $s) {
            return [
                'id' => $s->id,
                'ulid' => $s->ulid,
                'code' => $s->code,
                'name' => $s->name,
                'is_active' => $s->is_active,
                'is_sandbox' => $s->is_sandbox,
                'sender_id' => $s->sender_id,
                'balance_bdt' => $s->balance_bdt,
                'balance_updated_at' => $s->balance_updated_at?->format('d M Y, h:i A'),
                'last_tested_at' => $s->last_tested_at?->format('d M Y, h:i A'),
                'last_test_status' => $s->last_test_status,
                'last_test_message' => $s->last_test_message,
                'is_configured' => $s->isConfigured(),
                'masked_credentials' => $s->getMaskedCredentials(),
            ];
        });

        return Inertia::render('Admin/Communication/SmsSettings', [
            'gateways' => $gateways,
        ]);
    }

    /**
     * Update an SMS gateway's credentials and status.
     */
    public function updateSmsSetting(Request $request, SmsGatewaySetting $setting): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'is_sandbox' => ['required', 'boolean'],
            'sender_id' => ['nullable', 'string', 'max:50'],
            'credentials' => ['required', 'array'],
        ]);

        $existingCreds = $setting->credentials ?? [];
        $newCreds = $validated['credentials'];

        // Preserve credentials if user left masked dots
        foreach ($existingCreds as $k => $val) {
            if (isset($newCreds[$k]) && (str_contains((string) $newCreds[$k], '••••') || empty($newCreds[$k]))) {
                $newCreds[$k] = $val;
            }
        }

        $setting->update([
            'is_active' => $validated['is_active'],
            'is_sandbox' => $validated['is_sandbox'],
            'sender_id' => $validated['sender_id'] ?? null,
            'credentials' => $newCreds,
        ]);

        return redirect()->back()->with('success', "{$setting->name} settings updated successfully.");
    }

    /**
     * Pre-flight handshake test for an SMS gateway.
     */
    public function testSmsConnection(Request $request, SmsGatewayManager $smsManager): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'phone' => ['required', 'string'],
        ]);

        try {
            $driver = $smsManager->driver($validated['code']);
            $result = $driver->testConnection();

            return response()->json([
                'success' => $result->success,
                'message' => $result->message,
                'latency_ms' => $result->latencyMs,
            ], $result->success ? 200 : 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Query live balance for an SMS gateway.
     */
    public function checkSmsBalance(Request $request, SmsGatewayManager $smsManager): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        try {
            $driver = $smsManager->driver($validated['code']);
            $result = $driver->checkBalance();

            $setting = SmsGatewaySetting::where('code', $validated['code'])->first();
            if ($setting && $result->success) {
                $setting->update([
                    'balance_bdt' => $result->balanceBdt,
                    'balance_updated_at' => now(),
                ]);
            }

            return response()->json([
                'success' => $result->success,
                'balance' => $result->balanceBdt,
                'currency' => 'BDT',
                'message' => $result->message,
            ], $result->success ? 200 : 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display AI Voice Calling settings and verification call logs.
     */
    public function voiceSettings(): Response
    {
        $setting = VoiceCallingSetting::firstOrCreate(
            [],
            [
                'provider' => VoiceCallingSetting::PROVIDER_TWILIO,
                'is_active' => false,
                'is_sandbox' => true,
                'credentials' => [
                    'account_sid' => '',
                    'auth_token' => '',
                    'from_number' => '',
                ],
                'caller_id' => '+8801700000000',
                'language' => 'bn-BD',
                'dtmf_enabled' => true,
                'speech_enabled' => false,
                'retry_attempts' => 2,
                'script_template_bn' => 'আসসালামু আলাইকুম। আপনার অর্ডারের মোট মূল্য {total_amount} টাকা। নিশ্চিত করতে ১ চাপুন, বাতিল করতে ২ চাপুন।',
                'script_template_en' => 'Hello. Your order total is {total_amount} BDT. Press 1 to confirm, or 2 to cancel.',
            ]
        );

        $logs = VoiceCallLog::with(['order:id,order_number,customer_name,total_bdt'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Admin/Communication/VoiceCallingSettings', [
            'setting' => [
                'id' => $setting->id,
                'ulid' => $setting->ulid,
                'provider' => $setting->provider,
                'is_active' => $setting->is_active,
                'is_sandbox' => $setting->is_sandbox,
                'caller_id' => $setting->caller_id,
                'language' => $setting->language,
                'dtmf_enabled' => $setting->dtmf_enabled,
                'speech_enabled' => $setting->speech_enabled,
                'retry_attempts' => $setting->retry_attempts,
                'script_template_bn' => $setting->script_template_bn,
                'script_template_en' => $setting->script_template_en,
                'masked_credentials' => $setting->getMaskedCredentials(),
                'last_tested_at' => $setting->last_tested_at?->format('d M Y, h:i A'),
                'last_test_status' => $setting->last_test_status,
                'last_test_message' => $setting->last_test_message,
            ],
            'logs' => $logs,
        ]);
    }

    /**
     * Update Voice Calling settings.
     */
    public function updateVoiceSetting(Request $request, VoiceCallingSetting $setting): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:twilio,retell,vapi'],
            'is_active' => ['required', 'boolean'],
            'is_sandbox' => ['required', 'boolean'],
            'caller_id' => ['nullable', 'string', 'max:30'],
            'language' => ['required', 'string', 'max:20'],
            'dtmf_enabled' => ['required', 'boolean'],
            'speech_enabled' => ['required', 'boolean'],
            'retry_attempts' => ['required', 'integer', 'between:1,5'],
            'script_template_bn' => ['nullable', 'string'],
            'script_template_en' => ['nullable', 'string'],
            'credentials' => ['required', 'array'],
        ]);

        $existingCreds = $setting->credentials ?? [];
        $newCreds = $validated['credentials'];

        foreach ($existingCreds as $k => $val) {
            if (isset($newCreds[$k]) && (str_contains((string) $newCreds[$k], '••••') || empty($newCreds[$k]))) {
                $newCreds[$k] = $val;
            }
        }

        $setting->update([
            'provider' => $validated['provider'],
            'is_active' => $validated['is_active'],
            'is_sandbox' => $validated['is_sandbox'],
            'caller_id' => $validated['caller_id'] ?? null,
            'language' => $validated['language'],
            'dtmf_enabled' => $validated['dtmf_enabled'],
            'speech_enabled' => $validated['speech_enabled'],
            'retry_attempts' => $validated['retry_attempts'],
            'script_template_bn' => $validated['script_template_bn'] ?? null,
            'script_template_en' => $validated['script_template_en'] ?? null,
            'credentials' => $newCreds,
        ]);

        return redirect()->back()->with('success', 'Voice calling verification settings updated successfully.');
    }

    /**
     * Trigger pre-flight test voice call.
     */
    public function testVoiceCall(Request $request, TestVoiceCallAction $testAction): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $result = $testAction($validated['phone']);

        return response()->json([
            'success' => $result->success,
            'call_sid' => $result->details['call_sid'] ?? null,
            'message' => $result->message,
        ], $result->success ? 200 : 422);
    }

    /**
     * Display Abandoned Cart Recovery management and campaign analytics.
     */
    public function cartRecoveryIndex(): Response
    {
        $totalAbandoned = Cart::query()->where('created_at', '<', now()->subHours(1))->count();
        $totalDispatches = CartRecoveryLog::count();
        $totalRecovered = CartRecoveryLog::where('status', CartRecoveryLog::STATUS_RECOVERED)->count();
        $recoveryRate = $totalDispatches > 0 ? round(($totalRecovered / $totalDispatches) * 100, 1) : 0.0;

        $recoveredOrderIds = CartRecoveryLog::whereNotNull('recovered_order_id')->pluck('recovered_order_id')->toArray();
        $recoveredRevenue = Order::whereIn('id', $recoveredOrderIds)->sum('total_bdt');

        $stats = [
            'total_abandoned_carts' => $totalAbandoned,
            'total_dispatches' => $totalDispatches,
            'total_recovered_carts' => $totalRecovered,
            'recovery_rate_percent' => $recoveryRate,
            'recovered_revenue_bdt' => number_format((float) $recoveredRevenue, 2, '.', ''),
        ];

        $logs = CartRecoveryLog::with(['cart'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Admin/Marketing/CartRecovery', [
            'stats' => $stats,
            'logs' => $logs,
        ]);
    }

    /**
     * Trigger manual execution of abandoned cart recovery sequences.
     */
    public function triggerManualRecovery(ProcessCartRecoverySequencesAction $recoveryAction): RedirectResponse
    {
        $result = $recoveryAction();
        $dispatched = $result['total_dispatched'] ?? 0;

        return redirect()->back()->with('success', "Abandoned cart recovery completed. {$dispatched} message(s) dispatched.");
    }
}

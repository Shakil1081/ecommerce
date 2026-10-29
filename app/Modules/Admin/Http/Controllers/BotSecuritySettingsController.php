<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\BotSecuritySetting;
use App\Modules\Common\Services\BotCaptchaVerificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BotSecuritySettingsController extends Controller
{
    /**
     * Display the Bot Security settings view.
     */
    public function index(): Response
    {
        /** @var BotSecuritySetting|null $setting */
        $setting = BotSecuritySetting::query()->first();

        return Inertia::render('Admin/Settings/BotSecuritySettings', [
            'setting' => $setting ? [
                'id' => $setting->id,
                'provider' => $setting->provider,
                'site_key' => $setting->site_key,
                'masked_secret_key' => $setting->maskedSecretKey(),
                'has_secret_key' => ! empty($setting->secret_key),
                'is_active' => $setting->is_active,
                'score_threshold' => (float) $setting->score_threshold,
                'pages_enabled' => $setting->pages_enabled ?? [],
                'last_tested_at' => $setting->last_tested_at?->toIso8601String(),
                'last_test_status' => $setting->last_test_status,
            ] : [
                'id' => null,
                'provider' => BotSecuritySetting::PROVIDER_RECAPTCHA_V3,
                'site_key' => '',
                'masked_secret_key' => '',
                'has_secret_key' => false,
                'is_active' => false,
                'score_threshold' => 0.50,
                'pages_enabled' => [BotSecuritySetting::PAGE_CHECKOUT, BotSecuritySetting::PAGE_LOGIN, BotSecuritySetting::PAGE_REGISTER],
                'last_tested_at' => null,
                'last_test_status' => null,
            ],
            'availablePages' => BotSecuritySetting::ALL_PAGES,
            'availableProviders' => [
                BotSecuritySetting::PROVIDER_RECAPTCHA_V2,
                BotSecuritySetting::PROVIDER_RECAPTCHA_V3,
                BotSecuritySetting::PROVIDER_TURNSTILE,
            ],
        ]);
    }

    /**
     * Persist or update the bot security setting.
     */
    public function save(Request $request): RedirectResponse
    {
        /** @var BotSecuritySetting|null $existing */
        $existing = BotSecuritySetting::query()->first();

        $rules = [
            'provider' => ['required', 'string', 'in:recaptcha_v2,recaptcha_v3,turnstile'],
            'site_key' => ['required', 'string', 'max:255'],
            'secret_key' => [$existing ? 'nullable' : 'required', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'score_threshold' => ['required', 'numeric', 'min:0.0', 'max:1.0'],
            'pages_enabled' => ['required', 'array'],
            'pages_enabled.*' => ['string', 'in:checkout,login,register,contact,reviews'],
        ];

        $validated = $request->validate($rules);

        $data = [
            'provider' => $validated['provider'],
            'site_key' => $validated['site_key'],
            'is_active' => (bool) $validated['is_active'],
            'score_threshold' => $validated['score_threshold'],
            'pages_enabled' => $validated['pages_enabled'],
        ];

        if (! empty($validated['secret_key'])) {
            $data['secret_key'] = $validated['secret_key'];
        }

        if ($existing) {
            $existing->update($data);
        } else {
            BotSecuritySetting::create($data);
        }

        return back()->with('success', 'Bot security settings updated successfully.');
    }

    /**
     * Pre-flight connection test against provider endpoint.
     */
    public function testConnection(Request $request, BotCaptchaVerificationService $service): JsonResponse
    {
        /** @var BotSecuritySetting|null $existing */
        $existing = BotSecuritySetting::query()->first();

        $provider = (string) $request->input('provider', $existing?->provider ?? 'recaptcha_v3');
        $siteKey = (string) $request->input('site_key', $existing?->site_key ?? '');
        $secretKey = (string) ($request->input('secret_key') ?: ($existing?->secret_key ?? ''));

        $result = $service->testConnection($provider, $siteKey, $secretKey);

        if ($existing) {
            $existing->update([
                'last_tested_at' => Carbon::now(),
                'last_test_status' => $result['success'] ? 'success' : 'failed',
            ]);
        }

        return response()->json($result);
    }
}

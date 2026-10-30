<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Actions\TestAiConnectionAction;
use App\Modules\AI\Models\AiModelSetting;
use App\Modules\AI\Models\AiUsageLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiAdminController extends Controller
{
    /**
     * Display AI Model Settings and Token Usage Dashboard.
     */
    public function index(): Response
    {
        $settings = AiModelSetting::orderBy('sort_order')->get()->map(function ($s) {
            $data = $s->toArray();
            $data['masked_credentials'] = $s->masked_credentials;
            unset($data['credentials']);

            return $data;
        });

        $usageLogs = AiUsageLog::with('user')
            ->orderByDesc('created_at')
            ->take(20)
            ->get();

        $stats = [
            'total_requests' => AiUsageLog::count(),
            'total_tokens' => (int) AiUsageLog::sum('total_tokens'),
            'total_cost_usd' => (string) AiUsageLog::sum('estimated_cost_usd'),
            'total_cost_bdt' => (string) AiUsageLog::sum('estimated_cost_bdt'),
            'active_providers_count' => AiModelSetting::where('is_active', true)->count(),
        ];

        return Inertia::render('Admin/AI/AiSettings', [
            'settings' => $settings,
            'usageLogs' => $usageLogs,
            'stats' => $stats,
        ]);
    }

    /**
     * Update AI provider settings.
     */
    public function update(Request $request, AiModelSetting $setting): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'default_model' => ['required', 'string', 'max:64'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['required', 'integer', 'min:16', 'max:16384'],
            'assigned_features' => ['nullable', 'array'],
            'assigned_features.*' => ['string'],
            'credentials' => ['nullable', 'array'],
        ]);

        $setting->is_active = (bool) $validated['is_active'];
        $setting->default_model = $validated['default_model'];
        $setting->temperature = (float) $validated['temperature'];
        $setting->max_tokens = (int) $validated['max_tokens'];
        $setting->assigned_features = $validated['assigned_features'] ?? [];

        if (! empty($validated['credentials'])) {
            $currentCreds = $setting->credentials ?? [];
            foreach ($validated['credentials'] as $key => $val) {
                if (is_string($val) && str_starts_with($val, '••••••••')) {
                    continue;
                }
                $currentCreds[$key] = $val;
            }
            $setting->credentials = $currentCreds;
        }

        $setting->save();

        return redirect()->back()->with('success', "AI Settings for {$setting->name} updated successfully.");
    }

    /**
     * Test connection to an AI provider.
     */
    public function test(Request $request, TestAiConnectionAction $testAction): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['nullable', 'string'],
            'setting_id' => ['nullable', 'integer', 'exists:ai_model_settings,id'],
            'credentials' => ['nullable', 'array'],
            'model' => ['nullable', 'string'],
        ]);

        if (! empty($validated['setting_id'])) {
            $setting = AiModelSetting::findOrFail($validated['setting_id']);
        } else {
            $provider = $validated['provider'] ?? 'gemini';
            $setting = AiModelSetting::where('provider', $provider)->firstOrFail();
        }

        if (! empty($validated['credentials'])) {
            $creds = $setting->credentials ?? [];
            foreach ($validated['credentials'] as $k => $v) {
                if (is_string($v) && ! str_starts_with($v, '••••••••')) {
                    $creds[$k] = $v;
                }
            }
            $setting->credentials = $creds;
        }

        if (! empty($validated['model'])) {
            $setting->default_model = $validated['model'];
        }

        $result = $testAction($setting);

        return response()->json([
            'success' => $result->success,
            'provider' => $result->provider,
            'model' => $result->model,
            'latency_ms' => $result->latencyMs,
            'message' => $result->message,
            'response_sample' => $result->responseSample,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\AI\Services;

use App\Modules\AI\Actions\RecordAiUsageAction;
use App\Modules\AI\Contracts\AiProviderContract;
use App\Modules\AI\DTOs\AiConnectionTestResultDto;
use App\Modules\AI\DTOs\AiPromptRequestDto;
use App\Modules\AI\DTOs\AiResponseDto;
use App\Modules\AI\Models\AiModelSetting;
use App\Modules\AI\Services\Providers\ClaudeProviderAdapter;
use App\Modules\AI\Services\Providers\GeminiProviderAdapter;
use App\Modules\AI\Services\Providers\OllamaProviderAdapter;
use App\Modules\AI\Services\Providers\OpenAiProviderAdapter;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class AiManager
{
    /** @var array<string, AiProviderContract> */
    protected array $adapters = [];

    public function __construct(
        protected RecordAiUsageAction $recordUsageAction
    ) {
        $this->registerAdapter(new GeminiProviderAdapter);
        $this->registerAdapter(new OpenAiProviderAdapter);
        $this->registerAdapter(new ClaudeProviderAdapter);
        $this->registerAdapter(new OllamaProviderAdapter);
    }

    public function registerAdapter(AiProviderContract $adapter): void
    {
        $this->adapters[$adapter->getProviderCode()] = $adapter;
    }

    public function getAdapter(string $provider): AiProviderContract
    {
        if (! isset($this->adapters[$provider])) {
            throw new InvalidArgumentException("Unsupported AI provider adapter [{$provider}].");
        }

        return $this->adapters[$provider];
    }

    /**
     * Get active setting assigned to handle a specific operational feature.
     */
    public function getActiveSettingForFeature(string $feature): ?AiModelSetting
    {
        $settings = AiModelSetting::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($settings as $setting) {
            if ($setting->handlesFeature($feature)) {
                return $setting;
            }
        }

        // Fallback: return the first active setting if none explicitly assigned
        return $settings->first();
    }

    /**
     * Get active setting by provider code.
     */
    public function getSetting(string $provider): ?AiModelSetting
    {
        return AiModelSetting::where('provider', $provider)->first();
    }

    /**
     * Send a prompt request to the assigned or requested AI provider.
     */
    public function prompt(AiPromptRequestDto $request, ?AiModelSetting $settingOverride = null): AiResponseDto
    {
        $setting = $settingOverride ?? $this->getActiveSettingForFeature($request->feature);

        if (! $setting || ! $setting->is_active) {
            throw new RuntimeException("No active AI provider configured for feature [{$request->feature}].");
        }

        $adapter = $this->getAdapter($setting->provider);

        try {
            $response = $adapter->generateText($request, $setting);

            ($this->recordUsageAction)([
                'provider' => $response->provider,
                'model' => $response->model,
                'feature' => $request->feature,
                'prompt_tokens' => $response->promptTokens,
                'completion_tokens' => $response->completionTokens,
                'total_tokens' => $response->totalTokens,
                'latency_ms' => $response->latencyMs,
                'status' => 'success',
                'created_by_user_id' => $request->userId,
            ]);

            return $response;
        } catch (Throwable $e) {
            ($this->recordUsageAction)([
                'provider' => $setting->provider,
                'model' => $setting->default_model,
                'feature' => $request->feature,
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'latency_ms' => 0,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'created_by_user_id' => $request->userId,
            ]);

            throw $e;
        }
    }

    /**
     * Test connection for an AI setting.
     */
    public function testConnection(AiModelSetting $setting): AiConnectionTestResultDto
    {
        $adapter = $this->getAdapter($setting->provider);

        return $adapter->testConnection($setting);
    }
}

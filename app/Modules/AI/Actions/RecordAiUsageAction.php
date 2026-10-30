<?php

declare(strict_types=1);

namespace App\Modules\AI\Actions;

use App\Modules\AI\Models\AiModelSetting;
use App\Modules\AI\Models\AiUsageLog;

class RecordAiUsageAction
{
    private const EXCHANGE_RATE_USD_TO_BDT = 120.00;

    /**
     * Record an AI prompt completion event with estimated costs in USD and BDT.
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data): AiUsageLog
    {
        $provider = $data['provider'] ?? 'unknown';
        $model = $data['model'] ?? 'unknown';
        $feature = $data['feature'] ?? 'general';
        $promptTokens = (int) ($data['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($data['completion_tokens'] ?? 0);
        $totalTokens = (int) ($data['total_tokens'] ?? ($promptTokens + $completionTokens));
        $latencyMs = (int) ($data['latency_ms'] ?? 0);
        $status = $data['status'] ?? 'success';
        $errorMessage = $data['error_message'] ?? null;
        $userId = $data['created_by_user_id'] ?? null;

        $costUsd = $this->calculateEstimatedCostUsd($provider, $model, $promptTokens, $completionTokens);
        $costBdt = $costUsd * self::EXCHANGE_RATE_USD_TO_BDT;

        return AiUsageLog::create([
            'provider' => $provider,
            'model' => $model,
            'feature' => $feature,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'estimated_cost_usd' => number_format($costUsd, 6, '.', ''),
            'estimated_cost_bdt' => number_format($costBdt, 4, '.', ''),
            'latency_ms' => $latencyMs,
            'status' => $status,
            'error_message' => $errorMessage,
            'created_by_user_id' => $userId,
        ]);
    }

    /**
     * Compute approximate cost in USD based on standard blended provider token pricing.
     */
    private function calculateEstimatedCostUsd(string $provider, string $model, int $promptTokens, int $completionTokens): float
    {
        return match ($provider) {
            AiModelSetting::PROVIDER_GEMINI => ($promptTokens * 0.000000075) + ($completionTokens * 0.0000003),
            AiModelSetting::PROVIDER_OPENAI => ($promptTokens * 0.00000015) + ($completionTokens * 0.0000006),
            AiModelSetting::PROVIDER_CLAUDE => ($promptTokens * 0.000003) + ($completionTokens * 0.000015),
            AiModelSetting::PROVIDER_OLLAMA => 0.0,
            default => ($promptTokens * 0.0000001) + ($completionTokens * 0.0000004),
        };
    }
}


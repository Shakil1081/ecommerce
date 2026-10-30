<?php

declare(strict_types=1);

namespace App\Modules\AI\Services\Providers;

use App\Modules\AI\Contracts\AiProviderContract;
use App\Modules\AI\DTOs\AiConnectionTestResultDto;
use App\Modules\AI\DTOs\AiPromptRequestDto;
use App\Modules\AI\DTOs\AiResponseDto;
use App\Modules\AI\Models\AiModelSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ClaudeProviderAdapter implements AiProviderContract
{
    public function getProviderCode(): string
    {
        return AiModelSetting::PROVIDER_CLAUDE;
    }

    public function generateText(AiPromptRequestDto $request, AiModelSetting $setting): AiResponseDto
    {
        $credentials = $setting->credentials ?? [];
        $apiKey = $credentials['api_key'] ?? '';
        $endpoint = $credentials['endpoint'] ?? 'https://api.anthropic.com/v1/messages';

        if (empty($apiKey)) {
            throw new RuntimeException('Anthropic Claude API key is not configured.');
        }

        $model = $request->model ?? $setting->default_model ?: 'claude-3-5-sonnet-20241022';

        $payload = [
            'model' => $model,
            'max_tokens' => $request->maxTokens ?? $setting->max_tokens ?? 2048,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $request->prompt,
                ],
            ],
        ];

        if (! empty($request->systemPrompt)) {
            $payload['system'] = $request->systemPrompt;
        }

        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }

        $startTime = microtime(true);

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout(30)
            ->post($endpoint, $payload);

        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

        if (! $response->successful()) {
            $errorMessage = $response->json('error.message') ?? $response->body();
            throw new RuntimeException("Anthropic API error ({$response->status()}): {$errorMessage}");
        }

        $data = $response->json();
        $content = $data['content'][0]['text'] ?? '';
        $promptTokens = (int) ($data['usage']['input_tokens'] ?? 0);
        $completionTokens = (int) ($data['usage']['output_tokens'] ?? 0);
        $totalTokens = $promptTokens + $completionTokens;

        return new AiResponseDto(
            provider: $this->getProviderCode(),
            model: $model,
            content: trim($content),
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $totalTokens,
            latencyMs: $latencyMs,
            raw: $data
        );
    }

    public function testConnection(AiModelSetting $setting): AiConnectionTestResultDto
    {
        $startTime = microtime(true);
        $model = $setting->default_model ?: 'claude-3-5-sonnet-20241022';

        try {
            $testRequest = new AiPromptRequestDto(
                prompt: 'Respond with exactly "AI Connection Verified."',
                feature: 'test_connection',
                temperature: 0.1,
                maxTokens: 32,
                model: $model
            );

            $result = $this->generateText($testRequest, $setting);
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            return new AiConnectionTestResultDto(
                success: true,
                provider: $this->getProviderCode(),
                model: $model,
                latencyMs: $latencyMs,
                message: "Successfully connected to Anthropic Claude ({$model}).",
                responseSample: $result->content
            );
        } catch (Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            return new AiConnectionTestResultDto(
                success: false,
                provider: $this->getProviderCode(),
                model: $model,
                latencyMs: $latencyMs,
                message: $e->getMessage()
            );
        }
    }
}

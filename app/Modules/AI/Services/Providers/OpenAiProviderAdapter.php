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

class OpenAiProviderAdapter implements AiProviderContract
{
    public function getProviderCode(): string
    {
        return AiModelSetting::PROVIDER_OPENAI;
    }

    public function generateText(AiPromptRequestDto $request, AiModelSetting $setting): AiResponseDto
    {
        $credentials = $setting->credentials ?? [];
        $apiKey = $credentials['api_key'] ?? '';
        $endpoint = $credentials['endpoint'] ?? 'https://api.openai.com/v1/chat/completions';

        if (empty($apiKey)) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $model = $request->model ?? $setting->default_model ?: 'gpt-4o-mini';

        $messages = [];
        if (! empty($request->systemPrompt)) {
            $messages[] = [
                'role' => 'system',
                'content' => $request->systemPrompt,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $request->prompt,
        ];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $request->temperature ?? $setting->temperature ?? 0.7,
            'max_tokens' => $request->maxTokens ?? $setting->max_tokens ?? 2048,
        ];

        $startTime = microtime(true);

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post($endpoint, $payload);

        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

        if (! $response->successful()) {
            $errorMessage = $response->json('error.message') ?? $response->body();
            throw new RuntimeException("OpenAI API error ({$response->status()}): {$errorMessage}");
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';
        $promptTokens = (int) ($data['usage']['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($data['usage']['completion_tokens'] ?? 0);
        $totalTokens = (int) ($data['usage']['total_tokens'] ?? ($promptTokens + $completionTokens));

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
        $model = $setting->default_model ?: 'gpt-4o-mini';

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
                message: "Successfully connected to OpenAI ({$model}).",
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


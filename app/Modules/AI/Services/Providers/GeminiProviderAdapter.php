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

class GeminiProviderAdapter implements AiProviderContract
{
    public function getProviderCode(): string
    {
        return AiModelSetting::PROVIDER_GEMINI;
    }

    public function generateText(AiPromptRequestDto $request, AiModelSetting $setting): AiResponseDto
    {
        $credentials = $setting->credentials ?? [];
        $apiKey = $credentials['api_key'] ?? '';

        if (empty($apiKey)) {
            throw new RuntimeException('Gemini API key is not configured.');
        }

        $model = $request->model ?? $setting->default_model ?: 'gemini-1.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $request->prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $request->temperature ?? $setting->temperature ?? 0.7,
                'maxOutputTokens' => $request->maxTokens ?? $setting->max_tokens ?? 2048,
            ],
        ];

        if (! empty($request->systemPrompt)) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $request->systemPrompt],
                ],
            ];
        }

        $startTime = microtime(true);

        $response = Http::timeout(30)->post($url, $payload);
        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

        if (! $response->successful()) {
            $errorMessage = $response->json('error.message') ?? $response->body();
            throw new RuntimeException("Gemini API error ({$response->status()}): {$errorMessage}");
        }

        $data = $response->json();
        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $promptTokens = (int) ($data['usageMetadata']['promptTokenCount'] ?? 0);
        $completionTokens = (int) ($data['usageMetadata']['candidatesTokenCount'] ?? 0);
        $totalTokens = (int) ($data['usageMetadata']['totalTokenCount'] ?? ($promptTokens + $completionTokens));

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
        $model = $setting->default_model ?: 'gemini-1.5-flash';

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
                message: "Successfully connected to Google Gemini ({$model}).",
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

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

class OllamaProviderAdapter implements AiProviderContract
{
    public function getProviderCode(): string
    {
        return AiModelSetting::PROVIDER_OLLAMA;
    }

    public function generateText(AiPromptRequestDto $request, AiModelSetting $setting): AiResponseDto
    {
        $credentials = $setting->credentials ?? [];
        $endpoint = $credentials['endpoint'] ?? 'http://localhost:11434';
        $endpoint = rtrim($endpoint, '/').'/api/generate';

        $model = $request->model ?? $setting->default_model ?: 'llama3.2';

        $payload = [
            'model' => $model,
            'prompt' => $request->prompt,
            'stream' => false,
            'options' => [
                'temperature' => $request->temperature ?? $setting->temperature ?? 0.7,
                'num_predict' => $request->maxTokens ?? $setting->max_tokens ?? 2048,
            ],
        ];

        if (! empty($request->systemPrompt)) {
            $payload['system'] = $request->systemPrompt;
        }

        $startTime = microtime(true);

        $response = Http::timeout(60)->post($endpoint, $payload);
        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

        if (! $response->successful()) {
            throw new RuntimeException("Ollama API error ({$response->status()}): {$response->body()}");
        }

        $data = $response->json();
        $content = $data['response'] ?? '';
        $promptTokens = (int) ($data['prompt_eval_count'] ?? 0);
        $completionTokens = (int) ($data['eval_count'] ?? 0);
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
        $model = $setting->default_model ?: 'llama3.2';

        try {
            $testRequest = new AiPromptRequestDto(
                prompt: 'Respond with "AI Connection Verified."',
                feature: 'test_connection',
                temperature: 0.1,
                maxTokens: 16,
                model: $model
            );

            $result = $this->generateText($testRequest, $setting);
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            return new AiConnectionTestResultDto(
                success: true,
                provider: $this->getProviderCode(),
                model: $model,
                latencyMs: $latencyMs,
                message: "Successfully connected to Ollama Local LLM ({$model}).",
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

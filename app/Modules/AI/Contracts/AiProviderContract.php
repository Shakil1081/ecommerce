<?php

declare(strict_types=1);

namespace App\Modules\AI\Contracts;

use App\Modules\AI\DTOs\AiConnectionTestResultDto;
use App\Modules\AI\DTOs\AiPromptRequestDto;
use App\Modules\AI\DTOs\AiResponseDto;
use App\Modules\AI\Models\AiModelSetting;

interface AiProviderContract
{
    /**
     * Get unique provider code (gemini, openai, claude, ollama).
     */
    public function getProviderCode(): string;

    /**
     * Generate text completion using the specified AI provider.
     */
    public function generateText(AiPromptRequestDto $request, AiModelSetting $setting): AiResponseDto;

    /**
     * Run a pre-flight test connection verifying API credentials and endpoint responsiveness.
     */
    public function testConnection(AiModelSetting $setting): AiConnectionTestResultDto;
}


<?php

declare(strict_types=1);

namespace App\Modules\AI\DTOs;

final readonly class AiPromptRequestDto
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $prompt,
        public ?string $systemPrompt = null,
        public string $feature = 'general',
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public ?string $model = null,
        public array $metadata = [],
        public ?int $userId = null,
    ) {}
}

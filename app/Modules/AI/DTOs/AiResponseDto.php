<?php

declare(strict_types=1);

namespace App\Modules\AI\DTOs;

final readonly class AiResponseDto
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $provider,
        public string $model,
        public string $content,
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public int $totalTokens = 0,
        public int $latencyMs = 0,
        public array $raw = [],
    ) {}
}

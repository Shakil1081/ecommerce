<?php

declare(strict_types=1);

namespace App\Modules\AI\DTOs;

final readonly class AiConnectionTestResultDto
{
    public function __construct(
        public bool $success,
        public string $provider,
        public string $model,
        public int $latencyMs,
        public string $message,
        public ?string $responseSample = null,
    ) {}
}

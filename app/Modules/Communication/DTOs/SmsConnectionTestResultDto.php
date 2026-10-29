<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTOs;

readonly class SmsConnectionTestResultDto
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public bool $success,
        public string $message,
        public ?int $latencyMs = null,
        public ?float $balanceBdt = null,
        public array $details = [],
    ) {}
}

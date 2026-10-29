<?php

declare(strict_types=1);

namespace App\Modules\Logistics\DTOs;

readonly class CourierConnectionTestResultDto
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public bool $success,
        public string $message,
        public ?float $latencyMs = null,
        public array $details = [],
    ) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public static function success(string $message, ?float $latencyMs = null, array $details = []): self
    {
        return new self(
            success: true,
            message: $message,
            latencyMs: $latencyMs,
            details: $details,
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function failed(string $message, ?float $latencyMs = null, array $details = []): self
    {
        return new self(
            success: false,
            message: $message,
            latencyMs: $latencyMs,
            details: $details,
        );
    }
}

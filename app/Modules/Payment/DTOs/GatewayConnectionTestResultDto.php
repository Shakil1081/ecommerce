<?php

declare(strict_types=1);

namespace App\Modules\Payment\DTOs;

final readonly class GatewayConnectionTestResultDto
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public bool $success,
        public string $gatewayCode,
        public string $message,
        public ?float $latencyMs = null,
        public array $details = [],
    ) {}

    public static function successful(
        string $gatewayCode,
        string $message = 'Connection handshake successful',
        ?float $latencyMs = null,
        array $details = [],
    ): self {
        return new self(
            success: true,
            gatewayCode: $gatewayCode,
            message: $message,
            latencyMs: $latencyMs,
            details: $details,
        );
    }

    public static function failed(
        string $gatewayCode,
        string $message,
        ?float $latencyMs = null,
        array $details = [],
    ): self {
        return new self(
            success: false,
            gatewayCode: $gatewayCode,
            message: $message,
            latencyMs: $latencyMs,
            details: $details,
        );
    }
}

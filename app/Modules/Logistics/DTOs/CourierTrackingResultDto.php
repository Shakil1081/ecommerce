<?php

declare(strict_types=1);

namespace App\Modules\Logistics\DTOs;

readonly class CourierTrackingResultDto
{
    /**
     * @param  array<int, array<string, mixed>>  $events
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public bool $success,
        public string $status,
        public ?string $statusDetails = null,
        public ?string $currentLocation = null,
        public ?string $deliveredAt = null,
        public array $events = [],
        public array $rawPayload = [],
        public ?string $errorMessage = null,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @param  array<string, mixed>  $rawPayload
     */
    public static function success(
        string $status,
        ?string $statusDetails = null,
        ?string $currentLocation = null,
        ?string $deliveredAt = null,
        array $events = [],
        array $rawPayload = []
    ): self {
        return new self(
            success: true,
            status: $status,
            statusDetails: $statusDetails,
            currentLocation: $currentLocation,
            deliveredAt: $deliveredAt,
            events: $events,
            rawPayload: $rawPayload,
        );
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public static function failed(string $errorMessage, array $rawPayload = []): self
    {
        return new self(
            success: false,
            status: 'unknown',
            rawPayload: $rawPayload,
            errorMessage: $errorMessage,
        );
    }
}

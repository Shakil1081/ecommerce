<?php

declare(strict_types=1);

namespace App\Modules\Logistics\DTOs;

readonly class CourierBookingResultDto
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public bool $success,
        public ?string $consignmentId = null,
        public ?string $trackingCode = null,
        public ?string $status = null,
        public ?string $errorMessage = null,
        public ?string $deliveryFeeBdt = null,
        public array $rawPayload = [],
    ) {}

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public static function success(
        string $consignmentId,
        ?string $trackingCode = null,
        string $status = 'booked',
        ?string $deliveryFeeBdt = null,
        array $rawPayload = []
    ): self {
        return new self(
            success: true,
            consignmentId: $consignmentId,
            trackingCode: $trackingCode,
            status: $status,
            deliveryFeeBdt: $deliveryFeeBdt,
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
            errorMessage: $errorMessage,
            rawPayload: $rawPayload,
        );
    }
}

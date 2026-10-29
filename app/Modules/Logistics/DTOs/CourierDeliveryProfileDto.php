<?php

declare(strict_types=1);

namespace App\Modules\Logistics\DTOs;

final readonly class CourierDeliveryProfileDto
{
    public const TIER_LOW = 'low';

    public const TIER_MEDIUM = 'medium';

    public const TIER_HIGH = 'high';

    public const TIER_UNKNOWN = 'unknown';

    /**
     * @param  array<string, mixed>|null  $rawPayload
     */
    public function __construct(
        public string $courier,
        public string $phone,
        public int $totalParcels,
        public int $deliveredCount,
        public int $cancelledCount,
        public float $successRatePercent,
        public float $returnRatePercent,
        public string $riskTier,
        public bool $isHighRisk,
        public ?array $rawPayload = null
    ) {}

    /**
     * Create an unknown / empty profile when no history is found or when an API is unreachable.
     *
     * @param  array<string, mixed>|null  $rawPayload
     */
    public static function unknown(string $courier, string $phone, ?array $rawPayload = null): self
    {
        return new self(
            courier: $courier,
            phone: $phone,
            totalParcels: 0,
            deliveredCount: 0,
            cancelledCount: 0,
            successRatePercent: 100.0,
            returnRatePercent: 0.0,
            riskTier: self::TIER_UNKNOWN,
            isHighRisk: false,
            rawPayload: $rawPayload
        );
    }

    /**
     * Determine risk tier based on parcel volume and return rate percentage.
     */
    public static function calculateRiskTier(int $totalParcels, float $returnRatePercent): string
    {
        if ($totalParcels === 0) {
            return self::TIER_UNKNOWN;
        }

        if ($returnRatePercent > 40.0) {
            return self::TIER_HIGH;
        }

        if ($returnRatePercent >= 20.0) {
            return self::TIER_MEDIUM;
        }

        return self::TIER_LOW;
    }
}

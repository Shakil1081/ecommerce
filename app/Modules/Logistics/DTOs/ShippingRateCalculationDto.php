<?php

declare(strict_types=1);

namespace App\Modules\Logistics\DTOs;

readonly class ShippingRateCalculationDto
{
    public function __construct(
        public string $zoneCode,
        public string $zoneName,
        public string $baseRateBdt,
        public string $extraRateBdt,
        public string $totalShippingFeeBdt,
        public bool $isFreeShipping,
        public int $estimatedDaysMin,
        public int $estimatedDaysMax,
        public string $estimatedDeliveryText,
        public float $weightKg,
        public ?string $courierCode = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'zone_code' => $this->zoneCode,
            'zone_name' => $this->zoneName,
            'base_rate_bdt' => $this->baseRateBdt,
            'extra_rate_bdt' => $this->extraRateBdt,
            'total_shipping_fee_bdt' => $this->totalShippingFeeBdt,
            'is_free_shipping' => $this->isFreeShipping,
            'estimated_days_min' => $this->estimatedDaysMin,
            'estimated_days_max' => $this->estimatedDaysMax,
            'estimated_delivery_text' => $this->estimatedDeliveryText,
            'weight_kg' => $this->weightKg,
            'courier_code' => $this->courierCode,
        ];
    }
}

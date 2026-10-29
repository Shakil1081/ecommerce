<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Actions;

use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Logistics\DTOs\ShippingRateCalculationDto;
use App\Modules\Logistics\Models\ShippingZone;

class CalculateShippingRateAction
{
    /**
     * Compute dynamic zone-based delivery fee for Bangladesh destination.
     */
    public function __invoke(
        ?BangladeshDistrict $district = null,
        float $weightKg = 1.0,
        float $cartSubtotalBdt = 0.0,
        ?string $courierCode = null,
    ): ShippingRateCalculationDto {
        $weightKg = max(0.1, $weightKg);
        $cartSubtotalBdt = max(0.0, $cartSubtotalBdt);

        $zone = ShippingZone::resolveForDistrict($district);

        // Fallback default zone definitions if database table is empty
        $defaultConfig = ShippingZone::DEFAULT_ZONES[$zone?->code ?? ShippingZone::CODE_OUTSIDE_DHAKA]
            ?? ShippingZone::DEFAULT_ZONES[ShippingZone::CODE_OUTSIDE_DHAKA];

        $zoneCode = $zone ? $zone->code : ShippingZone::CODE_OUTSIDE_DHAKA;
        $zoneName = $zone ? $zone->name : $defaultConfig['name'];
        $daysMin = $zone ? $zone->estimated_days_min : $defaultConfig['days_min'];
        $daysMax = $zone ? $zone->estimated_days_max : $defaultConfig['days_max'];

        // Resolve rate record
        $rate = null;
        if ($zone) {
            if ($courierCode !== null) {
                $rate = $zone->rates()
                    ->where('is_active', true)
                    ->where('courier_code', $courierCode)
                    ->first();
            }

            if (! $rate) {
                $rate = $zone->rates()
                    ->where('is_active', true)
                    ->whereNull('courier_code')
                    ->first();
            }
        }

        $baseRateBdt = $rate ? (string) $rate->base_rate_bdt : $defaultConfig['default_rate'];
        $baseWeightKg = $rate ? (float) $rate->base_weight_kg : 1.0;
        $extraPerKgBdt = $rate ? (string) $rate->extra_rate_per_kg_bdt : $defaultConfig['extra_rate'];
        $freeShippingThreshold = $rate?->free_shipping_threshold_bdt;

        // Check if order qualifies for free delivery
        $isFreeShipping = false;
        if ($freeShippingThreshold !== null && bccomp((string) $cartSubtotalBdt, (string) $freeShippingThreshold, 4) >= 0) {
            $isFreeShipping = true;
            $extraRateBdt = '0.0000';
            $totalFeeBdt = '0.0000';
        } else {
            // Weight tier calculation
            if ($weightKg > $baseWeightKg) {
                $extraKg = (int) ceil($weightKg - $baseWeightKg);
                $extraRateBdt = bcmul((string) $extraKg, $extraPerKgBdt, 4);
                $totalFeeBdt = bcadd($baseRateBdt, $extraRateBdt, 4);
            } else {
                $extraRateBdt = '0.0000';
                $totalFeeBdt = $baseRateBdt;
            }
        }

        $deliveryText = "{$daysMin}-{$daysMax} days (".($zoneCode === ShippingZone::CODE_INSIDE_DHAKA ? 'Inside Dhaka' : ($zoneCode === ShippingZone::CODE_DHAKA_SUBURBS ? 'Suburbs' : 'Nationwide')).')';

        return new ShippingRateCalculationDto(
            zoneCode: $zoneCode,
            zoneName: $zoneName,
            baseRateBdt: $baseRateBdt,
            extraRateBdt: $extraRateBdt,
            totalShippingFeeBdt: $totalFeeBdt,
            isFreeShipping: $isFreeShipping,
            estimatedDaysMin: $daysMin,
            estimatedDaysMax: $daysMax,
            estimatedDeliveryText: $deliveryText,
            weightKg: $weightKg,
            courierCode: $courierCode,
        );
    }
}

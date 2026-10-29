<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Services;

use App\Modules\Customer\Rules\BangladeshPhoneNumberRule;
use App\Modules\Logistics\Contracts\CourierDeliveryIntelligenceContract;
use App\Modules\Logistics\DTOs\CourierDeliveryProfileDto;
use Illuminate\Support\Facades\Cache;

final class AggregatedCourierIntelligenceService
{
    /**
     * @var array<CourierDeliveryIntelligenceContract>
     */
    private array $adapters;

    /**
     * @param  array<CourierDeliveryIntelligenceContract>|null  $adapters
     */
    public function __construct(
        SteadfastDeliveryIntelligenceService $steadfast,
        PathaoDeliveryIntelligenceService $pathao,
        ?array $adapters = null
    ) {
        $this->adapters = $adapters ?? [$steadfast, $pathao];
    }

    /**
     * Check delivery intelligence across all configured couriers with 24-hour caching.
     */
    public function getAggregatedProfile(string $phone, bool $skipCache = false, int $cacheTtlHours = 24): CourierDeliveryProfileDto
    {
        $localPhone = BangladeshPhoneNumberRule::toLocalFormat($phone);
        $cacheKey = "courier_fraud_intel_{$localPhone}";

        if (! $skipCache && Cache::has($cacheKey)) {
            /** @var CourierDeliveryProfileDto $cached */
            $cached = Cache::get($cacheKey);

            return $cached;
        }

        $totalParcels = 0;
        $totalDelivered = 0;
        $totalCancelled = 0;
        $breakdown = [];

        foreach ($this->adapters as $adapter) {
            $profile = $adapter->checkCustomerDeliveryHistory($localPhone);
            $breakdown[$adapter->getCourierCode()] = [
                'total_parcels' => $profile->totalParcels,
                'delivered' => $profile->deliveredCount,
                'cancelled' => $profile->cancelledCount,
                'success_rate' => $profile->successRatePercent,
                'return_rate' => $profile->returnRatePercent,
                'risk_tier' => $profile->riskTier,
                'is_high_risk' => $profile->isHighRisk,
            ];

            $totalParcels += $profile->totalParcels;
            $totalDelivered += $profile->deliveredCount;
            $totalCancelled += $profile->cancelledCount;
        }

        $successRate = $totalParcels > 0 ? round(($totalDelivered / $totalParcels) * 100, 2) : 100.0;
        $returnRate = $totalParcels > 0 ? round(($totalCancelled / $totalParcels) * 100, 2) : 0.0;
        $riskTier = CourierDeliveryProfileDto::calculateRiskTier($totalParcels, $returnRate);
        $isHighRisk = $returnRate > 40.0 && ($totalParcels >= 3 || $totalCancelled >= 2);

        $aggregated = new CourierDeliveryProfileDto(
            courier: 'aggregated',
            phone: $localPhone,
            totalParcels: $totalParcels,
            deliveredCount: $totalDelivered,
            cancelledCount: $totalCancelled,
            successRatePercent: $successRate,
            returnRatePercent: $returnRate,
            riskTier: $riskTier,
            isHighRisk: $isHighRisk,
            rawPayload: [
                'courier_breakdown' => $breakdown,
            ]
        );

        if (! $skipCache) {
            Cache::put($cacheKey, $aggregated, now()->addHours($cacheTtlHours));
        }

        return $aggregated;
    }
}

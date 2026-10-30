<?php

declare(strict_types=1);

namespace App\Modules\Admin\DTOs;

final readonly class TradingPnLSummaryDto
{
    /**
     * @param  array<int, array<string, mixed>>  $categoryBreakdown
     * @param  array<int, array<string, mixed>>  $topPerformingSkus
     * @param  array<int, array<string, mixed>>  $dailyTrend
     */
    public function __construct(
        public string $period,
        public string $startDate,
        public string $endDate,
        public string $grossSalesTurnoverBdt,
        public string $totalDiscountsBdt,
        public string $netSalesRevenueBdt,
        public string $vatCollectedBdt,
        public string $totalCogsBdt,
        public string $grossTradingProfitBdt,
        public float $grossMarginPercent,
        public string $shippingFeesCollectedBdt,
        public string $courierCostBdt,
        public string $shippingVarianceBdt,
        public string $gatewayFeesBdt,
        public string $returnLossesBdt,
        public string $netOperatingProfitBdt,
        public float $netMarginPercent,
        public int $totalOrdersCount,
        public int $deliveredOrdersCount,
        public int $cancelledOrdersCount,
        public string $averageOrderValueBdt,
        public array $categoryBreakdown = [],
        public array $topPerformingSkus = [],
        public array $dailyTrend = [],
    ) {}
}

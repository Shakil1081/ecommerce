<?php

declare(strict_types=1);

namespace App\Modules\Admin\Actions;

use App\Modules\Admin\DTOs\TradingPnLSummaryDto;
use App\Modules\Logistics\Models\CourierConsignment;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\TaxCompliance\Models\Mushak63Invoice;
use App\Modules\TaxCompliance\Models\ReturnMerchandiseAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CalculateTradingPnLAction
{
    /**
     * Calculate comprehensive Trading Profit & Loss (P&L) metrics for the enterprise.
     *
     * @param  array<string, mixed>  $options
     */
    public function __invoke(?string $startDate = null, ?string $endDate = null, array $options = []): TradingPnLSummaryDto
    {
        $period = $options['period'] ?? 'this_month';
        [$start, $end] = $this->resolveDateRange($period, $startDate, $endDate);

        // 1. Orders within the window (excluding cancelled)
        $ordersQuery = Order::whereBetween('created_at', [$start, $end]);

        $validOrdersQuery = (clone $ordersQuery)->where('status', '!=', 'cancelled');

        $grossSalesTurnover = (float) $validOrdersQuery->sum('subtotal_bdt');
        $totalDiscounts = (float) $validOrdersQuery->sum('discount_amount_bdt');
        $shippingFeesCollected = (float) $validOrdersQuery->sum('shipping_fee_bdt');
        $netSalesRevenue = max(0.0, $grossSalesTurnover - $totalDiscounts);

        // 2. Output VAT collected from Mushak 6.3 invoices
        $vatCollected = (float) Mushak63Invoice::whereBetween('issue_date', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->sum('vat_total_bdt');

        // 3. True Landed Cost of Goods Sold (COGS in BDT)
        $validOrderIds = (clone $validOrdersQuery)->pluck('id');

        $totalCogs = (float) (OrderItem::whereIn('order_id', $validOrderIds)
            ->selectRaw('SUM(quantity * unit_cogs_bdt) as cogs')
            ->value('cogs') ?? 0.0);

        $grossTradingProfit = $netSalesRevenue - $totalCogs;
        $grossMarginPercent = $netSalesRevenue > 0 ? round(($grossTradingProfit / $netSalesRevenue) * 100, 2) : 0.0;

        // 4. Logistics Performance (Courier billing vs shipping collected)
        $courierCost = (float) CourierConsignment::whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', ['cancelled', 'failed'])
            ->sum('delivery_fee_bdt');

        $shippingVariance = $shippingFeesCollected - $courierCost;

        // 5. Payment Gateway processing fees
        $gatewayFees = (float) PaymentTransaction::whereBetween('created_at', [$start, $end])
            ->where('status', 'successful')
            ->sum('gateway_fee_bdt');

        // 6. RMA write-off / scrap losses
        $returnLosses = (float) ReturnMerchandiseAuthorization::whereBetween('created_at', [$start, $end])
            ->whereIn('inspection_outcome', ['scrap', 'quarantine_damaged'])
            ->sum('refund_amount_bdt');

        // 7. Net Operating Margin
        $netOperatingProfit = $grossTradingProfit + $shippingVariance - $gatewayFees - $returnLosses;
        $netMarginPercent = $netSalesRevenue > 0 ? round(($netOperatingProfit / $netSalesRevenue) * 100, 2) : 0.0;

        // 8. Order KPIs
        $totalOrdersCount = (clone $ordersQuery)->count();
        $deliveredOrdersCount = (clone $ordersQuery)->where('status', 'delivered')->count();
        $cancelledOrdersCount = (clone $ordersQuery)->where('status', 'cancelled')->count();
        $averageOrderValue = $totalOrdersCount > 0 ? round(($grossSalesTurnover + $shippingFeesCollected) / $totalOrdersCount, 2) : 0.0;

        // 9. Category Breakdown
        $categoryBreakdown = $this->calculateCategoryBreakdown($validOrderIds);

        // 10. Top Performing SKUs
        $topPerformingSkus = $this->calculateTopPerformingSkus($validOrderIds);

        // 11. Daily Trend
        $dailyTrend = $this->calculateDailyTrend($start, $end);

        return new TradingPnLSummaryDto(
            period: $period,
            startDate: $start->format('Y-m-d H:i:s'),
            endDate: $end->format('Y-m-d H:i:s'),
            grossSalesTurnoverBdt: number_format($grossSalesTurnover, 4, '.', ''),
            totalDiscountsBdt: number_format($totalDiscounts, 4, '.', ''),
            netSalesRevenueBdt: number_format($netSalesRevenue, 4, '.', ''),
            vatCollectedBdt: number_format($vatCollected, 4, '.', ''),
            totalCogsBdt: number_format($totalCogs, 4, '.', ''),
            grossTradingProfitBdt: number_format($grossTradingProfit, 4, '.', ''),
            grossMarginPercent: $grossMarginPercent,
            shippingFeesCollectedBdt: number_format($shippingFeesCollected, 4, '.', ''),
            courierCostBdt: number_format($courierCost, 4, '.', ''),
            shippingVarianceBdt: number_format($shippingVariance, 4, '.', ''),
            gatewayFeesBdt: number_format($gatewayFees, 4, '.', ''),
            returnLossesBdt: number_format($returnLosses, 4, '.', ''),
            netOperatingProfitBdt: number_format($netOperatingProfit, 4, '.', ''),
            netMarginPercent: $netMarginPercent,
            totalOrdersCount: $totalOrdersCount,
            deliveredOrdersCount: $deliveredOrdersCount,
            cancelledOrdersCount: $cancelledOrdersCount,
            averageOrderValueBdt: number_format($averageOrderValue, 4, '.', ''),
            categoryBreakdown: $categoryBreakdown,
            topPerformingSkus: $topPerformingSkus,
            dailyTrend: $dailyTrend
        );
    }

    /**
     * Resolve start and end timestamps based on pre-set period or custom dates.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveDateRange(string $period, ?string $start, ?string $end): array
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'this_week' => [$now->startOfWeek(), $now->endOfWeek()],
            'this_month' => [$now->startOfMonth(), $now->endOfMonth()],
            'this_quarter' => [$now->startOfQuarter(), $now->endOfQuarter()],
            'custom' => [
                $start ? CarbonImmutable::parse($start)->startOfDay() : $now->startOfMonth(),
                $end ? CarbonImmutable::parse($end)->endOfDay() : $now->endOfMonth(),
            ],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };
    }

    /**
     * Compute category profitability breakdown.
     *
     * @param  Collection<int, int>  $validOrderIds
     * @return array<int, array<string, mixed>>
     */
    private function calculateCategoryBreakdown($validOrderIds): array
    {
        if ($validOrderIds->isEmpty()) {
            return [];
        }

        $items = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->whereIn('order_items.order_id', $validOrderIds)
            ->selectRaw('
                COALESCE(categories.name, "Uncategorized") as category_name,
                SUM(order_items.quantity) as units_sold,
                SUM(order_items.subtotal_bdt) as revenue,
                SUM(order_items.quantity * order_items.unit_cogs_bdt) as cogs
            ')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('revenue')
            ->get();

        return $items->map(function ($row) {
            $rev = (float) $row->revenue;
            $cogs = (float) $row->cogs;
            $profit = $rev - $cogs;
            $margin = $rev > 0 ? round(($profit / $rev) * 100, 2) : 0.0;

            return [
                'category_name' => $row->category_name,
                'units_sold' => (int) $row->units_sold,
                'revenue_bdt' => number_format($rev, 2, '.', ''),
                'cogs_bdt' => number_format($cogs, 2, '.', ''),
                'gross_profit_bdt' => number_format($profit, 2, '.', ''),
                'margin_percent' => $margin,
            ];
        })->all();
    }

    /**
     * Compute top performing SKUs by gross profit.
     *
     * @param  Collection<int, int>  $validOrderIds
     * @return array<int, array<string, mixed>>
     */
    private function calculateTopPerformingSkus($validOrderIds): array
    {
        if ($validOrderIds->isEmpty()) {
            return [];
        }

        $items = DB::table('order_items')
            ->whereIn('order_id', $validOrderIds)
            ->selectRaw('
                sku,
                product_name,
                SUM(quantity) as units_sold,
                SUM(subtotal_bdt) as revenue,
                SUM(quantity * unit_cogs_bdt) as cogs
            ')
            ->groupBy('sku', 'product_name')
            ->get();

        return $items->map(function ($row) {
            $rev = (float) $row->revenue;
            $cogs = (float) $row->cogs;
            $profit = $rev - $cogs;
            $margin = $rev > 0 ? round(($profit / $rev) * 100, 2) : 0.0;

            return [
                'sku' => $row->sku,
                'product_name' => $row->product_name,
                'units_sold' => (int) $row->units_sold,
                'revenue_bdt' => number_format($rev, 2, '.', ''),
                'cogs_bdt' => number_format($cogs, 2, '.', ''),
                'gross_profit_bdt' => number_format($profit, 2, '.', ''),
                'margin_percent' => $margin,
            ];
        })
            ->sortByDesc(fn ($item) => (float) $item['gross_profit_bdt'])
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * Compute daily revenue & COGS trend across the date range.
     *
     * @return array<int, array<string, mixed>>
     */
    private function calculateDailyTrend(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = DB::table('orders')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('
                DATE(orders.created_at) as order_date,
                SUM(order_items.subtotal_bdt) as daily_revenue,
                SUM(order_items.quantity * order_items.unit_cogs_bdt) as daily_cogs
            ')
            ->groupBy(DB::raw('DATE(orders.created_at)'))
            ->orderBy('order_date')
            ->get();

        return $rows->map(function ($r) {
            $rev = (float) $r->daily_revenue;
            $cogs = (float) $r->daily_cogs;

            return [
                'date' => (string) $r->order_date,
                'revenue_bdt' => number_format($rev, 2, '.', ''),
                'cogs_bdt' => number_format($cogs, 2, '.', ''),
                'profit_bdt' => number_format($rev - $cogs, 2, '.', ''),
            ];
        })->all();
    }
}

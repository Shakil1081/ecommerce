<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Actions;

use App\Modules\Procurement\Models\BillOfEntry;
use App\Modules\Procurement\Models\LocalPurchaseOrder;
use App\Modules\TaxCompliance\DTOs\Mushak91SummaryDto;
use App\Modules\TaxCompliance\Models\Mushak63Invoice;
use App\Modules\TaxCompliance\Models\Mushak63Item;
use App\Modules\TaxCompliance\Models\Mushak91Return;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GenerateMushak91ReturnAction
{
    /**
     * Compile or recalculate the monthly NBR Mushak 9.1 VAT Return for a given tax period.
     *
     * @param  array<string, mixed>  $options
     */
    public function __invoke(string $taxPeriod, array $options = []): Mushak91Return
    {
        $startDate = CarbonImmutable::createFromFormat('Y-m', $taxPeriod)->startOfMonth();
        $endDate = $startDate->endOfMonth();

        $returnType = $options['return_type'] ?? 'original';

        /** @var Mushak91Return|null $existing */
        $existing = Mushak91Return::where('tax_period', $taxPeriod)
            ->where('return_type', $returnType)
            ->first();

        if ($existing && $existing->isSubmitted() && empty($options['force'])) {
            throw new InvalidArgumentException("Mushak 9.1 return for tax period {$taxPeriod} ({$returnType}) has already been submitted and cannot be recalculated.");
        }

        $taxpayerName = $options['taxpayer_name'] ?? config('tax.seller_name', 'ElectroCommerce BD Ltd.');
        $taxpayerBin = $options['taxpayer_bin'] ?? config('tax.seller_bin', '001234567-0101');
        $taxpayerAddress = $options['taxpayer_address'] ?? config('tax.seller_address', 'Plot 45, Gulshan Commercial Area, Dhaka-1212, Bangladesh');
        $economicActivity = $options['economic_activity'] ?? 'Retail & Wholesale Commercial Trading';
        $taxCircle = $options['tax_circle'] ?? config('tax.tax_circle', 'Circle-15 (Gulshan)');
        $taxCommissionerate = $options['tax_commissionerate'] ?? config('tax.tax_commissionerate', 'Customs, Excise & VAT Commissionerate, Dhaka (North)');

        // 1. Compile Part 3: Outward Supplies & Output VAT (from Mushak 6.3 invoices)
        $invoicesQuery = Mushak63Invoice::where('tax_period', $taxPeriod)
            ->where('status', '!=', 'cancelled');

        $salesCount = (int) $invoicesQuery->count();

        // Standard 15% rated items
        $standardItems = Mushak63Item::whereHas('invoice', function ($q) use ($taxPeriod) {
            $q->where('tax_period', $taxPeriod)->where('status', '!=', 'cancelled');
        })->where('vat_rate_percent', '>=', 15.00)
            ->where('is_vat_exempt', false);

        $salesStandardValue = (float) $standardItems->sum('subtotal_exclusive_bdt');
        $salesStandardVat = (float) $standardItems->sum('vat_amount_bdt');

        // Retail trade 5% rated items
        $retailItems = Mushak63Item::whereHas('invoice', function ($q) use ($taxPeriod) {
            $q->where('tax_period', $taxPeriod)->where('status', '!=', 'cancelled');
        })->where('vat_rate_percent', '<', 15.00)
            ->where('vat_rate_percent', '>', 0.00)
            ->where('is_vat_exempt', false);

        $salesRetailValue = (float) $retailItems->sum('subtotal_exclusive_bdt');
        $salesRetailVat = (float) $retailItems->sum('vat_amount_bdt');

        // Exempt items
        $exemptItems = Mushak63Item::whereHas('invoice', function ($q) use ($taxPeriod) {
            $q->where('tax_period', $taxPeriod)->where('status', '!=', 'cancelled');
        })->where('is_vat_exempt', true);

        $salesExemptValue = (float) $exemptItems->sum('subtotal_exclusive_bdt');

        $totalOutputVat = $salesStandardVat + $salesRetailVat;

        // 2. Compile Part 4: Inward Supplies & Rebatable Input Tax Credit
        // A) Local procurement with verified supplier Mushak 6.3
        $localPosQuery = LocalPurchaseOrder::whereBetween('order_date', [$startDate, $endDate])
            ->whereNotNull('mushak_6_3_invoice_number');

        $localPurchaseCount = (int) $localPosQuery->count();
        $localPurchaseValue = (float) $localPosQuery->sum('subtotal_bdt');
        $localPurchaseVat = (float) $localPosQuery->sum('vat_amount_bdt');

        // B) Commercial Imports with Bill of Entry (BOE)
        $boesQuery = BillOfEntry::whereBetween('boe_date', [$startDate, $endDate]);
        $importCount = (int) $boesQuery->count();
        $importAssessmentValue = (float) $boesQuery->sum('customs_assessment_value_bdt');
        $importVatRebatable = (float) $boesQuery->sum('vat_import');
        // Under Section 31 of VAT Act 2012, Advance Tax (AT - 5%) paid at import port is fully rebatable against output VAT
        $importAdvanceTaxAt = (float) $boesQuery->sum('advance_tax_at');

        $totalInputTaxCredit = $localPurchaseVat + $importVatRebatable + $importAdvanceTaxAt;

        // 3. Compile Part 5: Adjustments
        $decreasingReturns = (float) ($options['decreasing_adjustments_returns_bdt'] ?? 0.00);
        $decreasingOther = (float) ($options['decreasing_adjustments_other_bdt'] ?? 0.00);
        $totalDecreasingAdjustments = $decreasingReturns + $decreasingOther;

        $increasingVds = (float) ($options['increasing_adjustments_vds_bdt'] ?? $localPosQuery->sum('vds_amount_bdt'));
        $increasingReturns = (float) ($options['increasing_adjustments_returns_bdt'] ?? 0.00);
        $totalIncreasingAdjustments = $increasingVds + $increasingReturns;

        // 4. Compile Part 6: Net Tax Payable or Closing Balance Credit
        $netTaxMath = $totalOutputVat - $totalInputTaxCredit - $totalDecreasingAdjustments + $totalIncreasingAdjustments;

        if ($netTaxMath > 0.00) {
            $netVatPayable = round($netTaxMath, 4);
            $closingBalanceCredit = 0.00;
        } else {
            $netVatPayable = 0.00;
            $closingBalanceCredit = round(abs($netTaxMath), 4);
        }

        $summaryMetadata = [
            'sales_invoices_count' => $salesCount,
            'local_purchases_count' => $localPurchaseCount,
            'imports_count' => $importCount,
            'compiled_at' => CarbonImmutable::now()->toIso8601String(),
        ];

        return DB::transaction(function () use (
            $taxPeriod,
            $returnType,
            $taxpayerName,
            $taxpayerBin,
            $taxpayerAddress,
            $economicActivity,
            $taxCircle,
            $taxCommissionerate,
            $salesStandardValue,
            $salesStandardVat,
            $salesRetailValue,
            $salesRetailVat,
            $salesExemptValue,
            $totalOutputVat,
            $localPurchaseValue,
            $localPurchaseVat,
            $importAssessmentValue,
            $importVatRebatable,
            $importAdvanceTaxAt,
            $totalInputTaxCredit,
            $decreasingReturns,
            $decreasingOther,
            $totalDecreasingAdjustments,
            $increasingVds,
            $increasingReturns,
            $totalIncreasingAdjustments,
            $netVatPayable,
            $closingBalanceCredit,
            $summaryMetadata,
            $options
        ): Mushak91Return {
            return Mushak91Return::updateOrCreate(
                [
                    'tax_period' => $taxPeriod,
                    'return_type' => $returnType,
                ],
                [
                    'status' => 'finalized',
                    'taxpayer_name' => $taxpayerName,
                    'taxpayer_bin' => $taxpayerBin,
                    'taxpayer_address' => $taxpayerAddress,
                    'economic_activity' => $economicActivity,
                    'tax_circle' => $taxCircle,
                    'tax_commissionerate' => $taxCommissionerate,
                    'sales_standard_rated_value_bdt' => number_format($salesStandardValue, 4, '.', ''),
                    'sales_standard_rated_vat_bdt' => number_format($salesStandardVat, 4, '.', ''),
                    'sales_retail_trade_value_bdt' => number_format($salesRetailValue, 4, '.', ''),
                    'sales_retail_trade_vat_bdt' => number_format($salesRetailVat, 4, '.', ''),
                    'sales_exempt_value_bdt' => number_format($salesExemptValue, 4, '.', ''),
                    'total_output_vat_bdt' => number_format($totalOutputVat, 4, '.', ''),
                    'local_purchase_value_bdt' => number_format($localPurchaseValue, 4, '.', ''),
                    'local_purchase_vat_bdt' => number_format($localPurchaseVat, 4, '.', ''),
                    'import_assessment_value_bdt' => number_format($importAssessmentValue, 4, '.', ''),
                    'import_vat_rebatable_bdt' => number_format($importVatRebatable, 4, '.', ''),
                    'import_advance_tax_at_bdt' => number_format($importAdvanceTaxAt, 4, '.', ''),
                    'total_input_tax_credit_bdt' => number_format($totalInputTaxCredit, 4, '.', ''),
                    'decreasing_adjustments_returns_bdt' => number_format($decreasingReturns, 4, '.', ''),
                    'decreasing_adjustments_other_bdt' => number_format($decreasingOther, 4, '.', ''),
                    'total_decreasing_adjustments_bdt' => number_format($totalDecreasingAdjustments, 4, '.', ''),
                    'increasing_adjustments_vds_bdt' => number_format($increasingVds, 4, '.', ''),
                    'increasing_adjustments_returns_bdt' => number_format($increasingReturns, 4, '.', ''),
                    'total_increasing_adjustments_bdt' => number_format($totalIncreasingAdjustments, 4, '.', ''),
                    'net_vat_payable_bdt' => number_format($netVatPayable, 4, '.', ''),
                    'closing_balance_credit_bdt' => number_format($closingBalanceCredit, 4, '.', ''),
                    'summary_metadata' => $summaryMetadata,
                    'created_by_user_id' => $options['created_by_user_id'] ?? null,
                ]
            );
        });
    }

    /**
     * Get summary DTO without persisting.
     */
    public function getSummary(string $taxPeriod): Mushak91SummaryDto
    {
        $return = $this->__invoke($taxPeriod);

        $metadata = $return->summary_metadata ?? [];

        return new Mushak91SummaryDto(
            taxPeriod: $return->tax_period,
            totalOutputVatBdt: $return->total_output_vat_bdt,
            totalInputTaxCreditBdt: $return->total_input_tax_credit_bdt,
            totalDecreasingAdjustmentsBdt: $return->total_decreasing_adjustments_bdt,
            totalIncreasingAdjustmentsBdt: $return->total_increasing_adjustments_bdt,
            netVatPayableBdt: $return->net_vat_payable_bdt,
            closingBalanceCreditBdt: $return->closing_balance_credit_bdt,
            salesCount: (int) ($metadata['sales_invoices_count'] ?? 0),
            importCount: (int) ($metadata['imports_count'] ?? 0),
            localPurchaseCount: (int) ($metadata['local_purchases_count'] ?? 0),
            status: $return->status,
        );
    }
}

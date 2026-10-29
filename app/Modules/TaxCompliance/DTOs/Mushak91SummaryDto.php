<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\DTOs;

final readonly class Mushak91SummaryDto
{
    public function __construct(
        public string $taxPeriod,
        public string $totalOutputVatBdt,
        public string $totalInputTaxCreditBdt,
        public string $totalDecreasingAdjustmentsBdt,
        public string $totalIncreasingAdjustmentsBdt,
        public string $netVatPayableBdt,
        public string $closingBalanceCreditBdt,
        public int $salesCount,
        public int $importCount,
        public int $localPurchaseCount,
        public string $status,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tax_period' => $this->taxPeriod,
            'total_output_vat_bdt' => $this->totalOutputVatBdt,
            'total_input_tax_credit_bdt' => $this->totalInputTaxCreditBdt,
            'total_decreasing_adjustments_bdt' => $this->totalDecreasingAdjustmentsBdt,
            'total_increasing_adjustments_bdt' => $this->totalIncreasingAdjustmentsBdt,
            'net_vat_payable_bdt' => $this->netVatPayableBdt,
            'closing_balance_credit_bdt' => $this->closingBalanceCreditBdt,
            'sales_count' => $this->salesCount,
            'import_count' => $this->importCount,
            'local_purchase_count' => $this->localPurchaseCount,
            'status' => $this->status,
        ];
    }
}

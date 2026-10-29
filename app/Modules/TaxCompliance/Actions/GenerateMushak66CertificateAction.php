<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Actions;

use App\Modules\Procurement\Actions\VerifySupplierBinAction;
use App\Modules\Procurement\Models\LocalPurchaseOrder;
use App\Modules\TaxCompliance\DTOs\Mushak66GenerationResultDto;
use App\Modules\TaxCompliance\Models\Mushak66Certificate;
use App\Modules\TaxCompliance\Services\MushakSequenceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GenerateMushak66CertificateAction
{
    public function __construct(
        protected MushakSequenceService $sequenceService,
        protected VerifySupplierBinAction $verifyBinAction,
    ) {}

    /**
     * Generate an official statutory NBR Mushak 6.6 Certificate (উৎসে মূসক কর্তন সনদপত্র) for domestic VDS.
     *
     * @param  array<string, mixed>  $options
     */
    public function __invoke(LocalPurchaseOrder $po, array $options = []): Mushak66GenerationResultDto
    {
        $po->loadMissing('supplier');

        if (! $po->supplier) {
            throw new InvalidArgumentException('Purchase order does not have an associated supplier.');
        }

        // 1. Check for existing certificate (idempotent)
        /** @var Mushak66Certificate|null $existing */
        $existing = Mushak66Certificate::where('local_purchase_order_id', $po->id)->first();
        if ($existing) {
            return new Mushak66GenerationResultDto(
                certificate: $existing,
                certificateNumber: $existing->certificate_number,
                supplierName: $existing->supplier_name,
                supplierBin: $existing->supplier_bin,
                vdsDeductedAmountBdt: $existing->vds_deducted_amount_bdt,
                netPaidToSupplierBdt: $existing->net_paid_to_supplier_bdt,
                isExisting: true,
            );
        }

        // 2. Validate supplier BIN
        $binCheck = ($this->verifyBinAction)($po->supplier->bin_number);
        if (! $binCheck['is_valid']) {
            throw new InvalidArgumentException(
                'Cannot issue Mushak 6.6 Certificate: Supplier does not possess a valid 9 or 13-digit NBR BIN.'
            );
        }

        // 3. Validate supplier invoice number
        if (empty($po->mushak_6_3_invoice_number)) {
            throw new InvalidArgumentException(
                'Cannot issue Mushak 6.6 Certificate: Supplier Mushak 6.3 Tax Invoice number is required.'
            );
        }

        // 4. Validate VDS amount
        $vdsAmount = (float) $po->vds_amount_bdt;
        if ($vdsAmount <= 0.00) {
            throw new InvalidArgumentException(
                'Cannot issue Mushak 6.6 Certificate: VDS deduction amount must be greater than zero.'
            );
        }

        $now = CarbonImmutable::now();
        $issueDate = isset($options['issue_date']) ? CarbonImmutable::parse($options['issue_date']) : $now;
        $taxPeriod = $options['tax_period'] ?? $issueDate->format('Y-m');

        $withholdingEntityName = $options['withholding_entity_name'] ?? config('tax.seller_name', 'ElectroCommerce BD Ltd.');
        $withholdingEntityBin = $options['withholding_entity_bin'] ?? config('tax.seller_bin', '001234567-0101');
        $withholdingEntityAddress = $options['withholding_entity_address'] ?? config('tax.seller_address', 'Plot 45, Gulshan Commercial Area, Dhaka-1212, Bangladesh');
        $taxCircle = $options['tax_circle'] ?? config('tax.tax_circle', 'Circle-15 (Gulshan)');
        $taxCommissionerate = $options['tax_commissionerate'] ?? config('tax.tax_commissionerate', 'Customs, Excise & VAT Commissionerate, Dhaka (North)');

        $supplierInvoiceDate = $po->mushak_6_3_invoice_date ?? $po->order_date;
        $baseTaxableValue = (float) $po->subtotal_bdt;
        $vatRatePercent = (float) ($options['vat_rate_percent'] ?? 15.00);
        $vatAmount = (float) $po->vat_amount_bdt;
        $vdsRatePercent = (float) ($po->vds_rate_percent ?? 5.00);
        $netPaidToSupplier = (float) $po->total_payable_to_supplier_bdt;

        return DB::transaction(function () use (
            $po,
            $issueDate,
            $taxPeriod,
            $withholdingEntityName,
            $withholdingEntityBin,
            $withholdingEntityAddress,
            $taxCircle,
            $taxCommissionerate,
            $binCheck,
            $supplierInvoiceDate,
            $baseTaxableValue,
            $vatRatePercent,
            $vatAmount,
            $vdsRatePercent,
            $vdsAmount,
            $netPaidToSupplier,
            $options
        ): Mushak66GenerationResultDto {
            // 5. Generate statutory sequential certificate number under atomic lock
            $certNumber = $this->sequenceService->generateNextSequence('6.6', $issueDate);

            // 6. Compute SHA-256 historical integrity fingerprint
            $hashPayload = [
                'certificate_number' => $certNumber,
                'po_id' => $po->id,
                'po_number' => $po->po_number,
                'withholding_bin' => $withholdingEntityBin,
                'supplier_bin' => $binCheck['normalized_bin'],
                'supplier_invoice_number' => $po->mushak_6_3_invoice_number,
                'base_taxable_value_bdt' => number_format($baseTaxableValue, 4, '.', ''),
                'vds_deducted_amount_bdt' => number_format($vdsAmount, 4, '.', ''),
            ];

            $integrityHash = hash('sha256', json_encode($hashPayload, JSON_THROW_ON_ERROR));

            /** @var Mushak66Certificate $certificate */
            $certificate = Mushak66Certificate::create([
                'certificate_number' => $certNumber,
                'tax_period' => $taxPeriod,
                'issue_date' => $issueDate,
                'withholding_entity_name' => $withholdingEntityName,
                'withholding_entity_bin' => $withholdingEntityBin,
                'withholding_entity_address' => $withholdingEntityAddress,
                'tax_circle' => $taxCircle,
                'tax_commissionerate' => $taxCommissionerate,
                'supplier_id' => $po->supplier_id,
                'supplier_name' => $po->supplier->name,
                'supplier_bin' => (string) $binCheck['normalized_bin'],
                'supplier_address' => $po->supplier->address ?? 'Dhaka, Bangladesh',
                'local_purchase_order_id' => $po->id,
                'supplier_invoice_number' => (string) $po->mushak_6_3_invoice_number,
                'supplier_invoice_date' => $supplierInvoiceDate,
                'base_taxable_value_bdt' => number_format($baseTaxableValue, 4, '.', ''),
                'vat_rate_percent' => number_format($vatRatePercent, 2, '.', ''),
                'vat_amount_bdt' => number_format($vatAmount, 4, '.', ''),
                'vds_rate_percent' => number_format($vdsRatePercent, 2, '.', ''),
                'vds_deducted_amount_bdt' => number_format($vdsAmount, 4, '.', ''),
                'net_paid_to_supplier_bdt' => number_format($netPaidToSupplier, 4, '.', ''),
                'treasury_challan_number' => $options['treasury_challan_number'] ?? null,
                'treasury_challan_date' => isset($options['treasury_challan_date']) ? CarbonImmutable::parse($options['treasury_challan_date']) : null,
                'treasury_bank_name' => $options['treasury_bank_name'] ?? 'Sonali Bank PLC',
                'treasury_branch_name' => $options['treasury_branch_name'] ?? 'Local Office Branch, Dhaka',
                'treasury_account_code' => $options['treasury_account_code'] ?? '1/1133/0010/0311',
                'status' => ! empty($options['treasury_challan_number']) ? 'deposited' : 'issued',
                'integrity_hash' => $integrityHash,
                'notes' => $options['notes'] ?? null,
                'created_by_user_id' => $options['created_by_user_id'] ?? null,
            ]);

            // 7. Update parent PO reference
            $po->updateQuietly([
                'mushak_6_6_certificate_number' => $certNumber,
                'mushak_6_6_issue_date' => $issueDate->toDateString(),
            ]);

            return new Mushak66GenerationResultDto(
                certificate: $certificate,
                certificateNumber: $certNumber,
                supplierName: $certificate->supplier_name,
                supplierBin: $certificate->supplier_bin,
                vdsDeductedAmountBdt: $certificate->vds_deducted_amount_bdt,
                netPaidToSupplierBdt: $certificate->net_paid_to_supplier_bdt,
                isExisting: false,
            );
        });
    }
}

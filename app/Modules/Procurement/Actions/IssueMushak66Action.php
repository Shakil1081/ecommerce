<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Actions;

use App\Modules\Procurement\Models\LocalPurchaseOrder;
use Carbon\Carbon;
use InvalidArgumentException;

final class IssueMushak66Action
{
    public function __construct(
        private readonly VerifySupplierBinAction $verifyBinAction
    ) {}

    /**
     * Issues a statutory NBR Mushak 6.6 Certificate (উৎসে মূসক কর্তন সনদপত্র) for VDS.
     *
     * @return array{
     *     certificate_number: string,
     *     issue_date: string,
     *     supplier_name: string,
     *     supplier_bin: string,
     *     mushak_6_3_invoice: ?string,
     *     subtotal_bdt: string,
     *     vat_amount_bdt: string,
     *     vds_amount_bdt: string
     * }
     */
    public function __invoke(LocalPurchaseOrder $po, ?string $issueDate = null): array
    {
        $po->loadMissing('supplier');

        if (! $po->supplier) {
            throw new InvalidArgumentException('Purchase order does not have an associated supplier.');
        }

        // Validate supplier BIN
        $binCheck = ($this->verifyBinAction)($po->supplier->bin_number);
        if (! $binCheck['is_valid']) {
            throw new InvalidArgumentException(
                'Cannot issue Mushak 6.6 Certificate: Supplier does not possess a valid 9 or 13-digit NBR BIN.'
            );
        }

        if (bccomp((string) $po->vds_amount_bdt, '0.0000', 4) <= 0) {
            throw new InvalidArgumentException(
                'Cannot issue Mushak 6.6 Certificate: VDS deduction amount must be greater than zero.'
            );
        }

        $date = $issueDate ? Carbon::parse($issueDate) : Carbon::today();
        $year = $date->format('Y');

        // Generate sequential Mushak 6.6 Certificate Number
        $countThisYear = LocalPurchaseOrder::query()
            ->whereNotNull('mushak_6_6_certificate_number')
            ->whereYear('mushak_6_6_issue_date', $year)
            ->count() + 1;

        $certNumber = sprintf('VDS-%s-%05d', $year, $countThisYear);

        $po->update([
            'mushak_6_6_certificate_number' => $certNumber,
            'mushak_6_6_issue_date' => $date->toDateString(),
        ]);

        return [
            'certificate_number' => $certNumber,
            'issue_date' => $date->toDateString(),
            'supplier_name' => $po->supplier->name,
            'supplier_bin' => (string) $binCheck['normalized_bin'],
            'mushak_6_3_invoice' => $po->mushak_6_3_invoice_number,
            'subtotal_bdt' => (string) $po->subtotal_bdt,
            'vat_amount_bdt' => (string) $po->vat_amount_bdt,
            'vds_amount_bdt' => (string) $po->vds_amount_bdt,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Actions;

use App\Modules\TaxCompliance\Models\Mushak91Return;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SubmitMushak91ReturnAction
{
    /**
     * Officially submit a finalized Mushak 9.1 VAT Return to the NBR system.
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Mushak91Return $return, array $data = []): Mushak91Return
    {
        if ($return->isSubmitted()) {
            throw new InvalidArgumentException("Mushak 9.1 return {$return->tax_period} has already been submitted.");
        }

        $now = CarbonImmutable::now();
        $refSuffix = strtoupper(Str::random(6));
        $defaultReference = sprintf(
            'NBR-9.1-%s-%s',
            str_replace('-', '', $return->tax_period),
            $refSuffix
        );

        $submissionRef = $data['submission_reference'] ?? $defaultReference;
        $challanNumber = $data['treasury_challan_number'] ?? null;
        $challanDate = isset($data['treasury_challan_date']) ? CarbonImmutable::parse($data['treasury_challan_date']) : null;
        $depositAmount = isset($data['treasury_deposit_amount_bdt'])
            ? number_format((float) $data['treasury_deposit_amount_bdt'], 4, '.', '')
            : $return->net_vat_payable_bdt;

        $return->update([
            'status' => 'submitted',
            'submitted_at' => $now,
            'submission_reference' => $submissionRef,
            'treasury_challan_number' => $challanNumber,
            'treasury_challan_date' => $challanDate,
            'treasury_deposit_amount_bdt' => $depositAmount,
        ]);

        return $return->fresh();
    }
}

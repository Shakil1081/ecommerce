<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Services;

use App\Modules\TaxCompliance\Models\MushakSequence;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class MushakSequenceService
{
    /**
     * Generate an atomic, non-gap statutory sequential number for NBR documents.
     * Formats: M6.3-202610-000001, M6.5-202610-000001, M6.6-202610-000001, etc.
     */
    public function generateNextSequence(string $documentType, ?CarbonInterface $date = null): string
    {
        $resolvedDate = $date ?? CarbonImmutable::now();
        $yearMonth = $resolvedDate->format('Ym');

        return DB::transaction(function () use ($documentType, $yearMonth): string {
            // Ensure record exists safely first
            MushakSequence::firstOrCreate(
                [
                    'document_type' => $documentType,
                    'year_month' => $yearMonth,
                ],
                [
                    'last_sequence' => 0,
                ]
            );

            /** @var MushakSequence $sequence */
            $sequence = MushakSequence::where('document_type', $documentType)
                ->where('year_month', $yearMonth)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->last_sequence++;
            $sequence->save();

            $padded = str_pad((string) $sequence->last_sequence, 6, '0', STR_PAD_LEFT);

            return sprintf('M%s-%s-%s', $documentType, $yearMonth, $padded);
        });
    }
}

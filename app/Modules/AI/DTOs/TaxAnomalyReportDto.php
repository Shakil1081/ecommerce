<?php

declare(strict_types=1);

namespace App\Modules\AI\DTOs;

final readonly class TaxAnomalyReportDto
{
    /**
     * @param  array<int, string>  $issues
     */
    public function __construct(
        public string $taxPeriod,
        public bool $anomalyDetected,
        public int $anomalyScore,
        public string $outwardVatBdt,
        public string $creditNotesVatBdt,
        public float $creditNoteRatioPercent,
        public array $issues,
        public string $aiSummary,
    ) {}
}

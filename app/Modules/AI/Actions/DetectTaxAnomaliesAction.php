<?php

declare(strict_types=1);

namespace App\Modules\AI\Actions;

use App\Modules\AI\DTOs\AiPromptRequestDto;
use App\Modules\AI\DTOs\TaxAnomalyReportDto;
use App\Modules\AI\Models\AiModelSetting;
use App\Modules\AI\Services\AiManager;
use App\Modules\TaxCompliance\Models\Mushak63Invoice;
use App\Modules\TaxCompliance\Models\Mushak67CreditNote;

class DetectTaxAnomaliesAction
{
    public function __construct(
        protected AiManager $aiManager
    ) {}

    /**
     * Inspect monthly tax and VAT metrics to detect return anomalies and tax compliance risks.
     *
     * @param  array<string, mixed>  $options
     */
    public function __invoke(string $taxPeriod, array $options = []): TaxAnomalyReportDto
    {
        $outwardVatTotal = (float) Mushak63Invoice::where('tax_period', $taxPeriod)
            ->where('status', '!=', 'cancelled')
            ->sum('vat_total_bdt');

        $outwardInvoicesCount = Mushak63Invoice::where('tax_period', $taxPeriod)
            ->where('status', '!=', 'cancelled')
            ->count();

        $creditNotesVatTotal = (float) Mushak67CreditNote::where('tax_period', $taxPeriod)->sum('returned_vat_amount_bdt');
        $creditNotesCount = Mushak67CreditNote::where('tax_period', $taxPeriod)->count();

        $ratio = $outwardVatTotal > 0 ? round(($creditNotesVatTotal / $outwardVatTotal) * 100, 2) : 0.0;

        $issues = [];
        $anomalyScore = 0;

        if ($ratio > 10.0) {
            $issues[] = "High sales return ratio ({$ratio}%): Mushak 6.7 Credit Notes exceed 10% of outward VAT.";
            $anomalyScore += 45;
        }

        if ($creditNotesCount > 20 && $outwardInvoicesCount < 100) {
            $issues[] = "High return frequency: {$creditNotesCount} credit notes issued across {$outwardInvoicesCount} sales invoices.";
            $anomalyScore += 30;
        }

        if ($outwardVatTotal === 0.0 && $creditNotesVatTotal > 0.0) {
            $issues[] = 'Decreasing adjustments exist in a tax period with zero outward sales invoices.';
            $anomalyScore += 50;
        }

        $anomalyDetected = $anomalyScore >= 40 || count($issues) > 0;

        $issuesText = empty($issues) ? '- None identified' : implode("\n- ", $issues);

        $prompt = <<<PROMPT
Tax Period: {$taxPeriod}
Outward Sales Invoices Count: {$outwardInvoicesCount}
Total Outward Sales VAT: {$outwardVatTotal} BDT
Mushak 6.7 Credit Notes Count: {$creditNotesCount}
Credit Notes VAT Reduction: {$creditNotesVatTotal} BDT
Return Ratio: {$ratio}%
Flagged Issues:
{$issuesText}

Provide a 2-3 sentence executive VAT compliance analysis regarding audit vulnerability under the Bangladesh VAT Act 2012.
PROMPT;

        $request = new AiPromptRequestDto(
            prompt: $prompt,
            systemPrompt: 'You are a Senior VAT Auditor and Tax Compliance Advisor specializing in Bangladesh NBR regulations.',
            feature: AiModelSetting::FEATURE_TAX_ANOMALY,
            temperature: 0.2,
            maxTokens: 512,
            userId: $options['user_id'] ?? null
        );

        $response = $this->aiManager->prompt($request);

        return new TaxAnomalyReportDto(
            taxPeriod: $taxPeriod,
            anomalyDetected: $anomalyDetected,
            anomalyScore: min(100, $anomalyScore),
            outwardVatBdt: number_format($outwardVatTotal, 4, '.', ''),
            creditNotesVatBdt: number_format($creditNotesVatTotal, 4, '.', ''),
            creditNoteRatioPercent: $ratio,
            issues: $issues,
            aiSummary: trim($response->content)
        );
    }
}

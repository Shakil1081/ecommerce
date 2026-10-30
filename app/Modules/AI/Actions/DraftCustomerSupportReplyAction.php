<?php

declare(strict_types=1);

namespace App\Modules\AI\Actions;

use App\Modules\AI\DTOs\AiPromptRequestDto;
use App\Modules\AI\DTOs\SupportDraftDto;
use App\Modules\AI\Models\AiModelSetting;
use App\Modules\AI\Services\AiManager;
use App\Modules\TaxCompliance\Models\ReturnMerchandiseAuthorization;

class DraftCustomerSupportReplyAction
{
    public function __construct(
        protected AiManager $aiManager
    ) {}

    /**
     * Draft a customer dispute, return, or warranty support response using AI.
     *
     * @param  array<string, mixed>  $options
     */
    public function __invoke(ReturnMerchandiseAuthorization $rma, array $options = []): SupportDraftDto
    {
        $rma->loadMissing(['order', 'items']);

        $customerName = $rma->order?->customer_name ?? 'Valued Customer';
        $orderNumber = $rma->order?->order_number ?? 'N/A';
        $reason = $rma->reason_category ?? 'General Return';
        $customerNotes = $rma->customer_notes ?? 'No additional details.';
        $status = $rma->status;
        $inspectionOutcome = $rma->inspection_outcome ?? 'Pending inspection';
        $refundAmount = $rma->refund_amount_bdt;

        $prompt = <<<PROMPT
Customer Name: {$customerName}
Order Number: {$orderNumber}
RMA Number: {$rma->rma_number}
Return Category: {$reason}
Customer Notes: {$customerNotes}
Current Status: {$status}
Inspection Outcome: {$inspectionOutcome}
Refund Amount: {$refundAmount} BDT

Draft a professional and empathetic customer support message addressing this RMA.
If approved/refunded, explain statutory refund processing via original payment method/MFS within 3-5 business days.
If rejected, politely explain why per warranty guidelines.

Format your output as:
Subject: <email/sms subject line>
Action: <recommended internal action for agent>
Message:
<message body>
PROMPT;

        $request = new AiPromptRequestDto(
            prompt: $prompt,
            systemPrompt: 'You are an empathetic, highly professional Head of Customer Experience for a premier Bangladesh e-commerce platform. Your tone is respectful, reassuring, and clear.',
            feature: AiModelSetting::FEATURE_CUSTOMER_SUPPORT,
            temperature: 0.4,
            maxTokens: 1024,
            userId: $options['user_id'] ?? null
        );

        $response = $this->aiManager->prompt($request);
        $content = $response->content;

        $subject = 'Regarding your Return Request '.$rma->rma_number;
        $suggestedAction = 'Review RMA status and process refund/replacement.';
        $body = $content;

        if (preg_match('/Subject:\s*(.+)/i', $content, $matches)) {
            $subject = trim($matches[1]);
        }
        if (preg_match('/Action:\s*(.+)/i', $content, $matches)) {
            $suggestedAction = trim($matches[1]);
        }
        if (preg_match('/Message:\s*(.+)/is', $content, $matches)) {
            $body = trim($matches[1]);
        }

        return new SupportDraftDto(
            subject: $subject,
            body: $body,
            suggestedAction: $suggestedAction,
            requiresHumanReview: true,
            tone: 'professional_empathetic'
        );
    }
}

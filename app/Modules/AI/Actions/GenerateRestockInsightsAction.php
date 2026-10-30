<?php

declare(strict_types=1);

namespace App\Modules\AI\Actions;

use App\Modules\AI\DTOs\AiPromptRequestDto;
use App\Modules\AI\DTOs\RestockInsightDto;
use App\Modules\AI\Models\AiModelSetting;
use App\Modules\AI\Services\AiManager;

class GenerateRestockInsightsAction
{
    public function __construct(
        protected AiManager $aiManager
    ) {}

    /**
     * Generate smart restocking insights and reorder suggestions using velocity metrics and AI rationale.
     *
     * @param  array<string, mixed>  $context
     */
    public function __invoke(string $sku, array $context = []): RestockInsightDto
    {
        $productName = (string) ($context['product_name'] ?? 'SKU '.$sku);
        $currentStock = max(0, (int) ($context['current_stock'] ?? 0));
        $unitsSold30Days = max(0, (int) ($context['units_sold_30_days'] ?? 0));
        $leadTimeDays = max(1, (int) ($context['lead_time_days'] ?? 14));
        $safetyStockDays = max(1, (int) ($context['safety_stock_days'] ?? 7));

        $dailyVelocity = round($unitsSold30Days / 30.0, 2);
        $daysRemaining = $dailyVelocity > 0 ? (int) floor($currentStock / $dailyVelocity) : 999;

        // Base formula: required stock = (lead_time + safety_stock) * velocity
        $requiredBuffer = (int) ceil(($leadTimeDays + $safetyStockDays) * $dailyVelocity);
        $suggestedReorderQuantity = max(0, $requiredBuffer - $currentStock);

        $urgencyLevel = 'low';
        if ($daysRemaining <= 3 || ($currentStock === 0 && $unitsSold30Days > 0)) {
            $urgencyLevel = 'critical';
        } elseif ($daysRemaining <= $leadTimeDays) {
            $urgencyLevel = 'high';
        } elseif ($daysRemaining <= ($leadTimeDays + $safetyStockDays)) {
            $urgencyLevel = 'medium';
        }

        $prompt = <<<PROMPT
Product: {$productName} (SKU: {$sku})
Current Warehouse Stock: {$currentStock} units
30-Day Sales Velocity: {$unitsSold30Days} units ({$dailyVelocity} units/day)
Days of Inventory Remaining: {$daysRemaining} days
Supplier Lead Time: {$leadTimeDays} days
Safety Buffer Required: {$safetyStockDays} days
Calculated Reorder Target: {$suggestedReorderQuantity} units

Provide an executive inventory replenishment rationale (2-3 sentences) detailing why and when the procurement team should issue a Purchase Order.
PROMPT;

        $request = new AiPromptRequestDto(
            prompt: $prompt,
            systemPrompt: 'You are a Chief Supply Chain and Inventory Planning Officer for a large trading enterprise. Provide clear, direct, and actionable procurement advice.',
            feature: AiModelSetting::FEATURE_SMART_RESTOCKING,
            temperature: 0.3,
            maxTokens: 512,
            userId: $context['user_id'] ?? null
        );

        $response = $this->aiManager->prompt($request);

        return new RestockInsightDto(
            sku: $sku,
            productName: $productName,
            currentStock: $currentStock,
            dailyVelocity: $dailyVelocity,
            daysRemaining: $daysRemaining,
            suggestedReorderQuantity: $suggestedReorderQuantity,
            urgencyLevel: $urgencyLevel,
            aiRationale: trim($response->content)
        );
    }
}

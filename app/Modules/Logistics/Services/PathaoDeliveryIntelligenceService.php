<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Services;

use App\Modules\Customer\Rules\BangladeshPhoneNumberRule;
use App\Modules\Logistics\Contracts\CourierDeliveryIntelligenceContract;
use App\Modules\Logistics\DTOs\CourierDeliveryProfileDto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PathaoDeliveryIntelligenceService implements CourierDeliveryIntelligenceContract
{
    private string $baseUrl;

    private ?string $token;

    public function __construct(
        ?string $baseUrl = null,
        ?string $token = null
    ) {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.pathao.base_url', 'https://courier.pathao.com'), '/');
        $this->token = $token ?? config('services.pathao.token');
    }

    public function getCourierCode(): string
    {
        return 'pathao';
    }

    public function checkCustomerDeliveryHistory(string $phone): CourierDeliveryProfileDto
    {
        $localPhone = BangladeshPhoneNumberRule::toLocalFormat($phone);

        if (empty($this->token)) {
            return CourierDeliveryProfileDto::unknown($this->getCourierCode(), $localPhone);
        }

        try {
            $response = Http::timeout(5)
                ->withToken($this->token)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/aladdin/api/v1/user/success-rate", [
                    'phone' => $localPhone,
                ]);

            if (! $response->successful()) {
                Log::warning('Pathao delivery intelligence API returned non-200', [
                    'phone' => $localPhone,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return CourierDeliveryProfileDto::unknown($this->getCourierCode(), $localPhone, $response->json());
            }

            /** @var array<string, mixed> $json */
            $json = $response->json() ?? [];

            /** @var array<string, mixed> $data */
            $data = $json['data'] ?? $json;

            $total = (int) ($data['total_orders'] ?? $data['total_parcels'] ?? $data['total'] ?? 0);
            $delivered = (int) ($data['successful_orders'] ?? $data['delivered'] ?? $data['delivered_orders'] ?? 0);
            $cancelled = (int) ($data['returned_orders'] ?? $data['cancelled'] ?? $data['cancelled_orders'] ?? 0);

            if ($delivered + $cancelled > $total && $total === 0) {
                $total = $delivered + $cancelled;
            }

            $successRate = $total > 0 ? round(($delivered / $total) * 100, 2) : 100.0;
            $returnRate = $total > 0 ? round(($cancelled / $total) * 100, 2) : 0.0;
            $riskTier = CourierDeliveryProfileDto::calculateRiskTier($total, $returnRate);
            $isHighRisk = $returnRate > 40.0 && ($total >= 3 || $cancelled >= 2);

            return new CourierDeliveryProfileDto(
                courier: $this->getCourierCode(),
                phone: $localPhone,
                totalParcels: $total,
                deliveredCount: $delivered,
                cancelledCount: $cancelled,
                successRatePercent: $successRate,
                returnRatePercent: $returnRate,
                riskTier: $riskTier,
                isHighRisk: $isHighRisk,
                rawPayload: $json
            );
        } catch (Throwable $e) {
            Log::warning('Failed communicating with Pathao delivery intelligence API', [
                'phone' => $localPhone,
                'error' => $e->getMessage(),
            ]);

            return CourierDeliveryProfileDto::unknown($this->getCourierCode(), $localPhone, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

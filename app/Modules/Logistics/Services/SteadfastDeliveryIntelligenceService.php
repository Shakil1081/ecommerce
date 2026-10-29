<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Services;

use App\Modules\Customer\Rules\BangladeshPhoneNumberRule;
use App\Modules\Logistics\Contracts\CourierDeliveryIntelligenceContract;
use App\Modules\Logistics\DTOs\CourierDeliveryProfileDto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SteadfastDeliveryIntelligenceService implements CourierDeliveryIntelligenceContract
{
    private string $baseUrl;

    private ?string $apiKey;

    private ?string $secretKey;

    public function __construct(
        ?string $baseUrl = null,
        ?string $apiKey = null,
        ?string $secretKey = null
    ) {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.steadfast.base_url', 'https://ssl.steadfast.com.bd'), '/');
        $this->apiKey = $apiKey ?? config('services.steadfast.api_key');
        $this->secretKey = $secretKey ?? config('services.steadfast.secret_key');
    }

    public function getCourierCode(): string
    {
        return 'steadfast';
    }

    public function checkCustomerDeliveryHistory(string $phone): CourierDeliveryProfileDto
    {
        $localPhone = BangladeshPhoneNumberRule::toLocalFormat($phone);

        if (empty($this->apiKey) || empty($this->secretKey)) {
            return CourierDeliveryProfileDto::unknown($this->getCourierCode(), $localPhone);
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'Api-Key' => $this->apiKey,
                    'Secret-Key' => $this->secretKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/api/v1/fraud-check/{$localPhone}");

            if (! $response->successful()) {
                Log::warning('Steadfast delivery intelligence API returned non-200', [
                    'phone' => $localPhone,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return CourierDeliveryProfileDto::unknown($this->getCourierCode(), $localPhone, $response->json());
            }

            /** @var array<string, mixed> $json */
            $json = $response->json() ?? [];

            /** @var array<string, mixed> $data */
            $data = $json['delivery_status'] ?? $json['data'] ?? $json;

            $total = (int) ($data['total_parcels'] ?? $data['total'] ?? 0);
            $delivered = (int) ($data['delivered'] ?? $data['delivered_parcels'] ?? 0);
            $cancelled = (int) ($data['cancelled'] ?? $data['cancelled_parcels'] ?? 0);

            // Reconcile if total > 0 but delivered + cancelled exceeds total
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
            Log::warning('Failed communicating with Steadfast delivery intelligence API', [
                'phone' => $localPhone,
                'error' => $e->getMessage(),
            ]);

            return CourierDeliveryProfileDto::unknown($this->getCourierCode(), $localPhone, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Adapters;

use App\Modules\Logistics\Contracts\CourierAdapterContract;
use App\Modules\Logistics\DTOs\CourierBookingResultDto;
use App\Modules\Logistics\DTOs\CourierConnectionTestResultDto;
use App\Modules\Logistics\DTOs\CourierTrackingResultDto;
use App\Modules\Logistics\Models\CourierConsignment;
use App\Modules\Logistics\Models\CourierGatewaySetting;
use App\Modules\Order\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SteadfastCourierAdapter implements CourierAdapterContract
{
    public const DEFAULT_BASE_URL = 'https://portal.packzy.com/api/v1';

    public function code(): string
    {
        return CourierGatewaySetting::CODE_STEADFAST;
    }

    public function name(): string
    {
        return 'Steadfast Courier';
    }

    public function getSetting(): ?CourierGatewaySetting
    {
        return CourierGatewaySetting::where('code', $this->code())->first();
    }

    protected function resolveBaseUrl(?CourierGatewaySetting $setting = null): string
    {
        $setting ??= $this->getSetting();
        $url = $setting?->base_url ?: self::DEFAULT_BASE_URL;

        return rtrim($url, '/');
    }

    /**
     * @return array<string, string>
     */
    protected function buildHeaders(?CourierGatewaySetting $setting = null): array
    {
        $setting ??= $this->getSetting();
        $apiKey = (string) ($setting?->getCredential('api_key') ?? '');
        $secretKey = (string) ($setting?->getCredential('secret_key') ?? '');

        return [
            'Api-Key' => $apiKey,
            'Secret-Key' => $secretKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public function bookConsignment(Order $order, array $options = []): CourierBookingResultDto
    {
        $setting = $this->getSetting();
        if (! $setting || ! $setting->isConfigured()) {
            return CourierBookingResultDto::failed('Steadfast courier is not configured or missing credentials.');
        }

        $baseUrl = $this->resolveBaseUrl($setting);
        $headers = $this->buildHeaders($setting);

        $payload = [
            'invoice' => (string) $order->order_number,
            'recipient_name' => (string) $order->customer_name,
            'recipient_phone' => (string) $order->customer_phone,
            'recipient_address' => (string) $order->delivery_address,
            'cod_amount' => (float) $order->payable_cod_amount_bdt,
            'note' => (string) ($options['note'] ?? $order->customer_notes ?? 'Handle with care'),
        ];

        try {
            $response = Http::timeout(15)
                ->withHeaders($headers)
                ->post("{$baseUrl}/create_order", $payload);

            $data = $response->json();

            if ($response->successful() && isset($data['status']) && (int) $data['status'] === 200 && isset($data['consignment'])) {
                $consignmentData = $data['consignment'];
                $consignmentId = (string) ($consignmentData['consignment_id'] ?? '');
                $trackingCode = (string) ($consignmentData['tracking_code'] ?? $consignmentId);

                // Create or update consignment record
                CourierConsignment::updateOrCreate(
                    [
                        'order_id' => $order->id,
                        'courier_code' => $this->code(),
                    ],
                    [
                        'consignment_id' => $consignmentId,
                        'tracking_code' => $trackingCode,
                        'status' => CourierConsignment::STATUS_BOOKED,
                        'delivery_zone' => $order->delivery_zone,
                        'delivery_fee_bdt' => $order->shipping_fee_bdt,
                        'cod_amount_bdt' => $order->payable_cod_amount_bdt,
                        'recipient_name' => $order->customer_name,
                        'recipient_phone' => $order->customer_phone,
                        'recipient_address' => $order->delivery_address,
                        'courier_notes' => $payload['note'],
                        'booking_response_payload' => $data,
                        'dispatched_at' => now(),
                    ]
                );

                // Update order courier tracking info
                $order->update([
                    'courier_code' => $this->code(),
                    'courier_consignment_id' => $consignmentId,
                    'courier_tracking_code' => $trackingCode,
                    'status' => Order::STATUS_PROCESSING,
                ]);

                return CourierBookingResultDto::success(
                    consignmentId: $consignmentId,
                    trackingCode: $trackingCode,
                    status: CourierConsignment::STATUS_BOOKED,
                    deliveryFeeBdt: (string) $order->shipping_fee_bdt,
                    rawPayload: $data,
                );
            }

            $errorMessage = $data['message'] ?? $data['errors'] ?? 'Steadfast order booking rejected.';
            if (is_array($errorMessage)) {
                $errorMessage = json_encode($errorMessage);
            }

            return CourierBookingResultDto::failed(
                errorMessage: (string) $errorMessage,
                rawPayload: is_array($data) ? $data : ['body' => $response->body()],
            );
        } catch (Throwable $e) {
            Log::error('Steadfast bookConsignment failed', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return CourierBookingResultDto::failed(
                errorMessage: 'Exception during Steadfast consignment booking: '.$e->getMessage(),
            );
        }
    }

    public function trackConsignment(string $trackingCodeOrId): CourierTrackingResultDto
    {
        $setting = $this->getSetting();
        if (! $setting || ! $setting->isConfigured()) {
            return CourierTrackingResultDto::failed('Steadfast courier is not configured or missing credentials.');
        }

        $baseUrl = $this->resolveBaseUrl($setting);
        $headers = $this->buildHeaders($setting);

        try {
            $endpoint = is_numeric($trackingCodeOrId)
                ? "{$baseUrl}/status_by_cid/{$trackingCodeOrId}"
                : "{$baseUrl}/status_by_trackingcode/{$trackingCodeOrId}";

            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->get($endpoint);

            $data = $response->json();

            if ($response->successful() && isset($data['status']) && (int) $data['status'] === 200) {
                $rawStatus = (string) ($data['delivery_status'] ?? 'unknown');
                $mappedStatus = $this->mapDeliveryStatus($rawStatus);

                return CourierTrackingResultDto::success(
                    status: $mappedStatus,
                    statusDetails: "Steadfast status: {$rawStatus}",
                    currentLocation: $data['current_hub'] ?? null,
                    deliveredAt: $data['delivered_at'] ?? null,
                    events: $data['timeline'] ?? [],
                    rawPayload: $data,
                );
            }

            return CourierTrackingResultDto::failed(
                errorMessage: (string) ($data['message'] ?? 'Failed to retrieve Steadfast tracking status.'),
                rawPayload: is_array($data) ? $data : ['body' => $response->body()],
            );
        } catch (Throwable $e) {
            return CourierTrackingResultDto::failed(
                errorMessage: 'Steadfast tracking request failed: '.$e->getMessage(),
            );
        }
    }

    public function testConnection(?CourierGatewaySetting $setting = null): CourierConnectionTestResultDto
    {
        $setting ??= $this->getSetting();
        if (! $setting || ! $setting->isConfigured()) {
            return CourierConnectionTestResultDto::failed('Steadfast credentials (api_key and secret_key) are missing.');
        }

        $baseUrl = $this->resolveBaseUrl($setting);
        $headers = $this->buildHeaders($setting);

        $startTime = microtime(true);

        try {
            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->get("{$baseUrl}/get_balance");

            $latency = round((microtime(true) - $startTime) * 1000, 2);
            $data = $response->json();

            if ($response->successful() && isset($data['status']) && (int) $data['status'] === 200) {
                $balance = $data['current_balance'] ?? 0;

                return CourierConnectionTestResultDto::success(
                    message: "Steadfast API connected successfully. Current balance: ৳{$balance}",
                    latencyMs: $latency,
                    details: [
                        'current_balance' => $balance,
                        'environment' => $setting->is_sandbox ? 'sandbox' : 'production',
                    ],
                );
            }

            $errorMessage = $data['message'] ?? 'Steadfast API returned error response code '.$response->status();

            return CourierConnectionTestResultDto::failed(
                message: (string) $errorMessage,
                latencyMs: $latency,
                details: is_array($data) ? $data : ['response' => $response->body()],
            );
        } catch (Throwable $e) {
            $latency = round((microtime(true) - $startTime) * 1000, 2);

            return CourierConnectionTestResultDto::failed(
                message: 'Connection failed: '.$e->getMessage(),
                latencyMs: $latency,
            );
        }
    }

    public function mapDeliveryStatus(string $steadfastStatus): string
    {
        return match (strtolower(trim($steadfastStatus))) {
            'pending', 'in_review' => CourierConsignment::STATUS_BOOKED,
            'picked_up', 'in_transit', 'hold' => CourierConsignment::STATUS_IN_TRANSIT,
            'delivered_approval_pending' => CourierConsignment::STATUS_OUT_FOR_DELIVERY,
            'delivered' => CourierConsignment::STATUS_DELIVERED,
            'partial_delivered' => CourierConsignment::STATUS_PARTIAL_DELIVERED,
            'cancelled' => CourierConsignment::STATUS_CANCELLED,
            'return', 'returned' => CourierConsignment::STATUS_RETURNED,
            default => CourierConsignment::STATUS_IN_TRANSIT,
        };
    }
}

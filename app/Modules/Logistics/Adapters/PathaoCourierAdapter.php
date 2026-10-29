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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PathaoCourierAdapter implements CourierAdapterContract
{
    public const SANDBOX_BASE_URL = 'https://courier-api-sandbox.pathao.com';

    public const PRODUCTION_BASE_URL = 'https://api-hermes.pathao.com';

    public function code(): string
    {
        return CourierGatewaySetting::CODE_PATHAO;
    }

    public function name(): string
    {
        return 'Pathao Courier';
    }

    public function getSetting(): ?CourierGatewaySetting
    {
        return CourierGatewaySetting::where('code', $this->code())->first();
    }

    public function resolveBaseUrl(?CourierGatewaySetting $setting = null): string
    {
        $setting ??= $this->getSetting();
        if ($setting && ! empty($setting->base_url)) {
            return rtrim($setting->base_url, '/');
        }

        return $setting && ! $setting->is_sandbox
            ? self::PRODUCTION_BASE_URL
            : self::SANDBOX_BASE_URL;
    }

    /**
     * Retrieve and cache OAuth access token.
     */
    public function getAccessToken(?CourierGatewaySetting $setting = null): ?string
    {
        $setting ??= $this->getSetting();
        if (! $setting || ! $setting->isConfigured()) {
            return null;
        }

        $creds = $setting->credentials;
        if (! is_array($creds)) {
            return null;
        }

        // If a static bearer token is provided
        if (! empty($creds['token'])) {
            return (string) $creds['token'];
        }

        $cacheKey = "pathao_courier_access_token_{$setting->id}";
        $cachedToken = Cache::get($cacheKey);
        if ($cachedToken && is_string($cachedToken)) {
            return $cachedToken;
        }

        $baseUrl = $this->resolveBaseUrl($setting);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$baseUrl}/aladdin/api/v1/issue-token", [
                    'client_id' => (string) ($creds['client_id'] ?? ''),
                    'client_secret' => (string) ($creds['client_secret'] ?? ''),
                    'username' => (string) ($creds['username'] ?? ''),
                    'password' => (string) ($creds['password'] ?? ''),
                    'grant_type' => 'password',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $token = (string) ($data['access_token'] ?? '');
                $expiresIn = (int) ($data['expires_in'] ?? 2592000);

                if ($token !== '') {
                    $ttl = max(60, min($expiresIn - 3600, 86400 * 25));
                    Cache::put($cacheKey, $token, $ttl);

                    return $token;
                }
            }

            Log::error('Pathao issue-token failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (Throwable $e) {
            Log::error('Pathao issue-token exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function bookConsignment(Order $order, array $options = []): CourierBookingResultDto
    {
        $setting = $this->getSetting();
        if (! $setting || ! $setting->isConfigured()) {
            return CourierBookingResultDto::failed('Pathao courier is not configured or missing credentials.');
        }

        $token = $this->getAccessToken($setting);
        if (! $token) {
            return CourierBookingResultDto::failed('Failed to obtain Pathao OAuth access token.');
        }

        $baseUrl = $this->resolveBaseUrl($setting);
        $storeId = (int) ($options['store_id'] ?? $setting->getCredential('store_id') ?? 1);

        $payload = [
            'store_id' => $storeId,
            'merchant_order_id' => (string) $order->order_number,
            'recipient_name' => (string) $order->customer_name,
            'recipient_phone' => (string) $order->customer_phone,
            'recipient_address' => (string) $order->delivery_address,
            'recipient_city' => (int) ($options['city_id'] ?? 1),
            'recipient_zone' => (int) ($options['zone_id'] ?? 1),
            'recipient_area' => (int) ($options['area_id'] ?? 1),
            'delivery_type' => (int) ($options['delivery_type'] ?? 48),
            'item_type' => (int) ($options['item_type'] ?? 2),
            'special_instruction' => (string) ($options['instruction'] ?? $order->customer_notes ?? 'Handle with care'),
            'item_quantity' => (int) ($order->items()->sum('quantity') ?: 1),
            'item_weight' => (float) ($options['weight'] ?? 0.5),
            'amount_to_collect' => (float) $order->payable_cod_amount_bdt,
        ];

        try {
            $response = Http::timeout(15)
                ->withToken($token)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$baseUrl}/aladdin/api/v1/orders/issue-order", $payload);

            $data = $response->json();

            if ($response->successful() && isset($data['data']['consignment_id'])) {
                $orderData = $data['data'];
                $consignmentId = (string) $orderData['consignment_id'];
                $deliveryFee = isset($orderData['delivery_fee']) ? (string) $orderData['delivery_fee'] : (string) $order->shipping_fee_bdt;

                CourierConsignment::updateOrCreate(
                    [
                        'order_id' => $order->id,
                        'courier_code' => $this->code(),
                    ],
                    [
                        'consignment_id' => $consignmentId,
                        'tracking_code' => $consignmentId,
                        'status' => CourierConsignment::STATUS_BOOKED,
                        'delivery_zone' => $order->delivery_zone,
                        'delivery_fee_bdt' => $deliveryFee,
                        'cod_amount_bdt' => $order->payable_cod_amount_bdt,
                        'recipient_name' => $order->customer_name,
                        'recipient_phone' => $order->customer_phone,
                        'recipient_address' => $order->delivery_address,
                        'courier_notes' => $payload['special_instruction'],
                        'booking_response_payload' => $data,
                        'dispatched_at' => now(),
                    ]
                );

                $order->update([
                    'courier_code' => $this->code(),
                    'courier_consignment_id' => $consignmentId,
                    'courier_tracking_code' => $consignmentId,
                    'status' => Order::STATUS_PROCESSING,
                ]);

                return CourierBookingResultDto::success(
                    consignmentId: $consignmentId,
                    trackingCode: $consignmentId,
                    status: CourierConsignment::STATUS_BOOKED,
                    deliveryFeeBdt: $deliveryFee,
                    rawPayload: $data,
                );
            }

            $errorMessage = $data['message'] ?? $data['errors'] ?? 'Pathao order booking rejected.';
            if (is_array($errorMessage)) {
                $errorMessage = json_encode($errorMessage);
            }

            return CourierBookingResultDto::failed(
                errorMessage: (string) $errorMessage,
                rawPayload: is_array($data) ? $data : ['body' => $response->body()],
            );
        } catch (Throwable $e) {
            Log::error('Pathao bookConsignment exception', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return CourierBookingResultDto::failed(
                errorMessage: 'Exception during Pathao consignment booking: '.$e->getMessage(),
            );
        }
    }

    public function trackConsignment(string $trackingCodeOrId): CourierTrackingResultDto
    {
        $setting = $this->getSetting();
        if (! $setting || ! $setting->isConfigured()) {
            return CourierTrackingResultDto::failed('Pathao courier is not configured or missing credentials.');
        }

        $token = $this->getAccessToken($setting);
        if (! $token) {
            return CourierTrackingResultDto::failed('Failed to obtain Pathao OAuth access token.');
        }

        $baseUrl = $this->resolveBaseUrl($setting);

        try {
            $response = Http::timeout(10)
                ->withToken($token)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("{$baseUrl}/aladdin/api/v1/orders/{$trackingCodeOrId}/info");

            $data = $response->json();

            if ($response->successful() && (isset($data['data']['order_status']) || isset($data['order_status']))) {
                $info = $data['data'] ?? $data;
                $rawStatus = (string) ($info['order_status'] ?? 'unknown');
                $mappedStatus = $this->mapDeliveryStatus($rawStatus);

                return CourierTrackingResultDto::success(
                    status: $mappedStatus,
                    statusDetails: "Pathao status: {$rawStatus}",
                    currentLocation: $info['current_hub'] ?? null,
                    deliveredAt: $info['delivered_at'] ?? null,
                    events: $info['timeline'] ?? [],
                    rawPayload: $data,
                );
            }

            return CourierTrackingResultDto::failed(
                errorMessage: (string) ($data['message'] ?? 'Failed to retrieve Pathao parcel tracking information.'),
                rawPayload: is_array($data) ? $data : ['body' => $response->body()],
            );
        } catch (Throwable $e) {
            return CourierTrackingResultDto::failed(
                errorMessage: 'Pathao tracking request failed: '.$e->getMessage(),
            );
        }
    }

    public function testConnection(?CourierGatewaySetting $setting = null): CourierConnectionTestResultDto
    {
        $setting ??= $this->getSetting();
        if (! $setting || ! $setting->isConfigured()) {
            return CourierConnectionTestResultDto::failed('Pathao credentials (client_id, client_secret, username, password) are missing.');
        }

        $baseUrl = $this->resolveBaseUrl($setting);
        $startTime = microtime(true);

        try {
            // First step: fetch token
            $token = $this->getAccessToken($setting);
            if (! $token) {
                $latency = round((microtime(true) - $startTime) * 1000, 2);

                return CourierConnectionTestResultDto::failed(
                    message: 'Authentication failed: Unable to acquire OAuth access token with provided Pathao credentials.',
                    latencyMs: $latency,
                );
            }

            // Second step: query stores endpoint to verify active merchant account
            $response = Http::timeout(10)
                ->withToken($token)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("{$baseUrl}/aladdin/api/v1/stores");

            $latency = round((microtime(true) - $startTime) * 1000, 2);
            $data = $response->json();

            if ($response->successful()) {
                $stores = $data['data']['data'] ?? $data['data'] ?? [];
                $storeCount = is_array($stores) ? count($stores) : 0;

                return CourierConnectionTestResultDto::success(
                    message: "Pathao API connected successfully. Found {$storeCount} pickup store(s).",
                    latencyMs: $latency,
                    details: [
                        'store_count' => $storeCount,
                        'environment' => $setting->is_sandbox ? 'sandbox' : 'production',
                    ],
                );
            }

            return CourierConnectionTestResultDto::failed(
                message: (string) ($data['message'] ?? 'Failed to query Pathao merchant stores.'),
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

    public function mapDeliveryStatus(string $pathaoStatus): string
    {
        return match (strtolower(trim($pathaoStatus))) {
            'pickup_requested', 'pending', 'order_created' => CourierConsignment::STATUS_BOOKED,
            'picked_up', 'in_transit', 'hub_transfer' => CourierConsignment::STATUS_IN_TRANSIT,
            'out_for_delivery' => CourierConsignment::STATUS_OUT_FOR_DELIVERY,
            'delivered' => CourierConsignment::STATUS_DELIVERED,
            'partial_delivered' => CourierConsignment::STATUS_PARTIAL_DELIVERED,
            'return', 'returned' => CourierConsignment::STATUS_RETURNED,
            'cancelled', 'payment_cancelled' => CourierConsignment::STATUS_CANCELLED,
            default => CourierConsignment::STATUS_IN_TRANSIT,
        };
    }
}

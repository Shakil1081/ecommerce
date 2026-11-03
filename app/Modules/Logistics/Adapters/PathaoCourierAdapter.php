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
            $url = rtrim($setting->base_url, '/');
            // If base_url was set to default sandbox, but sandbox toggle is OFF, auto-route to live production
            if (! $setting->is_sandbox && ($url === self::SANDBOX_BASE_URL || str_contains($url, 'sandbox') || str_contains($url, 'stageenv'))) {
                return self::PRODUCTION_BASE_URL;
            }
            // If base_url was set to production, but sandbox toggle is ON, route to sandbox
            if ($setting->is_sandbox && $url === self::PRODUCTION_BASE_URL) {
                return self::SANDBOX_BASE_URL;
            }

            return $url;
        }

        return $setting && ! $setting->is_sandbox
            ? self::PRODUCTION_BASE_URL
            : self::SANDBOX_BASE_URL;
    }

    /**
     * Determine if Pathao operates in Sandbox Simulator mode.
     * Activates when test credentials (e.g. test@pathao.com, 7N1aMJQbWm, MYer0gKbOB)
     * are supplied, allowing merchants/developers to test consignment creation, tracking,
     * and thermal label printing without live merchant approval.
     */
    public function isSimulationMode(?CourierGatewaySetting $setting = null): bool
    {
        $setting ??= $this->getSetting();
        if (! $setting) {
            return false;
        }

        $creds = $setting->credentials;
        if (! is_array($creds)) {
            return false;
        }

        $username = strtolower(trim((string) ($creds['username'] ?? '')));
        $clientId = trim((string) ($creds['client_id'] ?? ''));

        if (! empty($creds['simulator']) && (bool) $creds['simulator'] === true) {
            return true;
        }

        return $username === 'test@pathao.com'
            || str_contains($username, 'sandbox')
            || in_array($clientId, ['7N1aMJQbWm', 'MYer0gKbOB'], true);
    }

    /**
     * Retrieve OAuth access token with rich diagnostic details.
     *
     * @return array{token: ?string, error: ?string, status: ?int, latency_ms: float}
     */
    public function fetchAccessTokenDetails(?CourierGatewaySetting $setting = null, int $timeoutSeconds = 8, bool $fresh = false): array
    {
        $setting ??= $this->getSetting();
        if (! $setting || ! $setting->isConfigured()) {
            return [
                'token' => null,
                'error' => 'Pathao credentials (client_id, client_secret, username, password) are missing or incomplete.',
                'status' => null,
                'latency_ms' => 0.0,
            ];
        }

        if ($this->isSimulationMode($setting)) {
            return [
                'token' => 'sim_pathao_bearer_test_token',
                'error' => null,
                'status' => 200,
                'latency_ms' => 12.0,
            ];
        }

        $creds = $setting->credentials;
        if (! is_array($creds)) {
            return [
                'token' => null,
                'error' => 'Pathao credentials must be configured as an array.',
                'status' => null,
                'latency_ms' => 0.0,
            ];
        }

        // If a static bearer token is provided
        if (! empty($creds['token'])) {
            return [
                'token' => (string) $creds['token'],
                'error' => null,
                'status' => 200,
                'latency_ms' => 0.0,
            ];
        }

        $cacheKey = "pathao_courier_access_token_{$setting->id}";
        if (! $fresh) {
            $cachedToken = Cache::get($cacheKey);
            if ($cachedToken && is_string($cachedToken)) {
                return [
                    'token' => $cachedToken,
                    'error' => null,
                    'status' => 200,
                    'latency_ms' => 0.0,
                ];
            }
        }

        $baseUrl = $this->resolveBaseUrl($setting);
        $startTime = microtime(true);

        try {
            $response = Http::timeout($timeoutSeconds)
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

            $latency = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful()) {
                $data = $response->json();
                $token = (string) ($data['access_token'] ?? '');
                $expiresIn = (int) ($data['expires_in'] ?? 2592000);

                if ($token !== '') {
                    $ttl = max(60, min($expiresIn - 3600, 86400 * 25));
                    Cache::put($cacheKey, $token, $ttl);

                    return [
                        'token' => $token,
                        'error' => null,
                        'status' => $response->status(),
                        'latency_ms' => $latency,
                    ];
                }
            }

            $body = $response->body();
            $data = $response->json();
            $errorMsg = (string) ($data['message'] ?? $data['error'] ?? "HTTP {$response->status()} returned by Pathao.");

            Log::error('Pathao issue-token failed', [
                'status' => $response->status(),
                'body' => $body,
                'base_url' => $baseUrl,
            ]);

            return [
                'token' => null,
                'error' => $errorMsg,
                'status' => $response->status(),
                'latency_ms' => $latency,
            ];
        } catch (Throwable $e) {
            $latency = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Pathao issue-token exception', [
                'error' => $e->getMessage(),
                'base_url' => $baseUrl,
            ]);

            return [
                'token' => null,
                'error' => $e->getMessage(),
                'status' => null,
                'latency_ms' => $latency,
            ];
        }
    }

    /**
     * Retrieve and cache OAuth access token.
     */
    public function getAccessToken(?CourierGatewaySetting $setting = null): ?string
    {
        return $this->fetchAccessTokenDetails($setting)['token'] ?? null;
    }

    public function bookConsignment(Order $order, array $options = []): CourierBookingResultDto
    {
        $setting = $this->getSetting();
        if (! $setting || ! $setting->isConfigured()) {
            return CourierBookingResultDto::failed('Pathao courier is not configured or missing credentials.');
        }

        if ($this->isSimulationMode($setting)) {
            $simConsignmentId = 'PT-SIM-'.strtoupper(bin2hex(random_bytes(4)));
            $deliveryFee = (string) $order->shipping_fee_bdt;

            CourierConsignment::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'courier_code' => $this->code(),
                ],
                [
                    'consignment_id' => $simConsignmentId,
                    'tracking_code' => $simConsignmentId,
                    'status' => CourierConsignment::STATUS_BOOKED,
                    'delivery_zone' => $order->delivery_zone,
                    'delivery_fee_bdt' => $deliveryFee,
                    'cod_amount_bdt' => $order->payable_cod_amount_bdt,
                    'recipient_name' => $order->customer_name,
                    'recipient_phone' => $order->customer_phone,
                    'recipient_address' => $order->delivery_address,
                    'booking_response' => [
                        'consignment_id' => $simConsignmentId,
                        'order_status' => 'Pickup_Requested',
                        'delivery_fee' => (float) $deliveryFee,
                        'simulated' => true,
                    ],
                    'dispatched_at' => now(),
                ]
            );

            $order->update([
                'courier_code' => $this->code(),
                'courier_consignment_id' => $simConsignmentId,
                'courier_tracking_code' => $simConsignmentId,
            ]);

            return CourierBookingResultDto::success(
                consignmentId: $simConsignmentId,
                trackingCode: $simConsignmentId,
                status: CourierConsignment::STATUS_BOOKED,
                deliveryFeeBdt: $deliveryFee,
                rawPayload: [
                    'consignment_id' => $simConsignmentId,
                    'simulated' => true,
                    'merchant_order_id' => (string) $order->order_number,
                ],
            );
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

        if ($this->isSimulationMode($setting) || str_starts_with($trackingCodeOrId, 'PT-SIM')) {
            return CourierTrackingResultDto::success(
                status: CourierConsignment::STATUS_IN_TRANSIT,
                statusDetails: 'Simulated Pathao parcel is in transit to destination hub',
                currentLocation: 'Dhaka Central Sorting Hub',
                deliveredAt: null,
                events: [
                    ['time' => now()->subHours(2)->toDateTimeString(), 'status' => 'Pickup_Requested', 'note' => 'Order created in simulator'],
                    ['time' => now()->subHour()->toDateTimeString(), 'status' => 'Assigned_For_Pickup', 'note' => 'Pathao rider assigned'],
                    ['time' => now()->subMinutes(30)->toDateTimeString(), 'status' => 'Picked', 'note' => 'Parcel received at sorting hub'],
                    ['time' => now()->toDateTimeString(), 'status' => 'In_Transit', 'note' => 'On the way to destination delivery point'],
                ],
                rawPayload: ['tracking_code' => $trackingCodeOrId, 'simulated' => true],
            );
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

        if ($this->isSimulationMode($setting)) {
            $username = (string) ($setting->getCredential('username') ?: 'test@pathao.com');

            return CourierConnectionTestResultDto::success(
                message: "⚡ Pathao Sandbox Simulator connected successfully! Authorized test merchant '{$username}'. Found 2 test pickup store(s): 'Main Warehouse Dhaka' (#158848), 'Chittagong Hub' (#10293). You can test parcel booking, tracking, and thermal labels in Simulator mode without live merchant credentials.",
                latencyMs: 14.5,
                details: [
                    'mode' => 'sandbox_simulator',
                    'store_count' => 2,
                    'stores' => [
                        ['store_id' => 158848, 'store_name' => 'Main Warehouse Dhaka', 'is_active' => true],
                        ['store_id' => 10293, 'store_name' => 'Chittagong Hub', 'is_active' => true],
                    ],
                    'environment' => 'sandbox_simulator',
                ],
            );
        }

        $baseUrl = $this->resolveBaseUrl($setting);

        // Step 1: Attempt OAuth token issuance with detailed diagnostic capture (bypassing cache)
        $tokenDetails = $this->fetchAccessTokenDetails($setting, timeoutSeconds: 8, fresh: true);

        if (! $tokenDetails['token']) {
            $rawError = $tokenDetails['error'] ?? 'Unknown authentication failure';
            $latency = $tokenDetails['latency_ms'];
            $isTimeout = str_contains(strtolower($rawError), 'timed out') || str_contains(strtolower($rawError), 'timeout') || str_contains(strtolower($rawError), 'curl error 28');

            $prodUrl = self::PRODUCTION_BASE_URL;
            if ($isTimeout && $setting->is_sandbox) {
                $message = "Authentication failed: Unable to acquire OAuth access token. Connection timed out connecting to Pathao Sandbox ({$baseUrl}). Pathao's sandbox server is currently unreachable. If you have production merchant credentials, toggle OFF 'Sandbox Mode' to connect to Pathao Live ({$prodUrl}).";
            } elseif ($isTimeout) {
                $message = "Authentication failed: Unable to acquire OAuth access token. Connection timed out connecting to Pathao ({$baseUrl}). Please verify server internet connectivity or firewall egress rules.";
            } elseif ($tokenDetails['status'] === 400 && str_contains(strtolower($rawError), 'credentials were incorrect')) {
                $env = $setting->is_sandbox ? 'Sandbox' : 'Production';
                $message = "Authentication failed: Unable to acquire OAuth access token. Pathao returned 'The user credentials were incorrect' (HTTP 400) on {$env}. Please verify that your Merchant Email (Username), Password, Client ID, and Client Secret are exact matches from your Pathao Merchant Dashboard.";
            } else {
                $message = "Authentication failed: Unable to acquire OAuth access token. {$rawError} (Endpoint: {$baseUrl}/aladdin/api/v1/issue-token).";
            }

            return CourierConnectionTestResultDto::failed(
                message: $message,
                latencyMs: $latency,
                details: [
                    'endpoint' => "{$baseUrl}/aladdin/api/v1/issue-token",
                    'status' => $tokenDetails['status'],
                    'environment' => $setting->is_sandbox ? 'sandbox' : 'production',
                    'raw_error' => $rawError,
                ],
            );
        }

        $token = $tokenDetails['token'];
        $startTime = microtime(true);

        try {
            // Step 2: query stores endpoint to verify active merchant account & pickup hubs
            $response = Http::timeout(8)
                ->withToken($token)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("{$baseUrl}/aladdin/api/v1/stores");

            $latency = round((microtime(true) - $startTime) * 1000 + $tokenDetails['latency_ms'], 2);
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
                        'endpoint' => $baseUrl,
                    ],
                );
            }

            $storeError = (string) ($data['message'] ?? 'Failed to query Pathao merchant stores.');

            return CourierConnectionTestResultDto::failed(
                message: "Connected to Pathao, but failed to query stores: {$storeError}",
                latencyMs: $latency,
                details: is_array($data) ? $data : ['response' => $response->body()],
            );
        } catch (Throwable $e) {
            $latency = round((microtime(true) - $startTime) * 1000 + $tokenDetails['latency_ms'], 2);

            return CourierConnectionTestResultDto::failed(
                message: 'Querying Pathao stores failed: '.$e->getMessage(),
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

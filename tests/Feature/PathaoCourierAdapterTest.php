<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Logistics\Adapters\PathaoCourierAdapter;
use App\Modules\Logistics\Models\CourierConsignment;
use App\Modules\Logistics\Models\CourierGatewaySetting;
use App\Modules\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PathaoCourierAdapterTest extends TestCase
{
    use RefreshDatabase;

    private CourierGatewaySetting $setting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setting = CourierGatewaySetting::create([
            'code' => CourierGatewaySetting::CODE_PATHAO,
            'name' => 'Pathao Courier',
            'is_active' => true,
            'is_sandbox' => true,
            'base_url' => 'https://courier-api-sandbox.pathao.com',
            'supported_features' => ['booking', 'tracking', 'rates', 'stores', 'webhook'],
            'sort_order' => 2,
            'credentials' => [
                'client_id' => 'client_test_id_123',
                'client_secret' => 'client_test_secret_456',
                'username' => 'merchant@pathao-test.com',
                'password' => 'test_password_789',
                'store_id' => '10293',
            ],
        ]);
    }

    private function createDummyOrder(): Order
    {
        $division = BangladeshDivision::create(['name_en' => 'Dhaka', 'name_bn' => 'ঢাকা']);
        $district = BangladeshDistrict::create([
            'division_id' => $division->id,
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'zone_type' => 'inside_dhaka',
        ]);

        return Order::create([
            'order_number' => 'ORD-2026-PT-001',
            'customer_name' => 'Kazi Nazrul',
            'customer_phone' => '+8801811223344',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Road 12, Gulshan 1, Dhaka',
            'subtotal_bdt' => '3200.0000',
            'shipping_fee_bdt' => '70.0000',
            'total_bdt' => '3270.0000',
            'payable_cod_amount_bdt' => '3270.0000',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'cash_on_delivery',
            'fraud_risk_score' => 5,
            'fraud_risk_level' => 'low',
        ]);
    }

    public function test_pathao_adapter_obtains_and_caches_oauth_token(): void
    {
        Http::fake([
            'https://courier-api-sandbox.pathao.com/aladdin/api/v1/issue-token' => Http::response([
                'access_token' => 'mock_pathao_bearer_token_xyz',
                'token_type' => 'Bearer',
                'expires_in' => 86400,
            ], 200),
        ]);

        $adapter = app(PathaoCourierAdapter::class);
        $token = $adapter->getAccessToken($this->setting);

        $this->assertSame('mock_pathao_bearer_token_xyz', $token);
        $this->assertTrue(Cache::has("pathao_courier_access_token_{$this->setting->id}"));
    }

    public function test_pathao_adapter_books_consignment_successfully(): void
    {
        Http::fake([
            'https://courier-api-sandbox.pathao.com/aladdin/api/v1/issue-token' => Http::response([
                'access_token' => 'mock_pathao_bearer_token_xyz',
                'expires_in' => 86400,
            ], 200),
            'https://courier-api-sandbox.pathao.com/aladdin/api/v1/orders/issue-order' => Http::response([
                'type' => 'success',
                'code' => 200,
                'message' => 'Order created successfully',
                'data' => [
                    'consignment_id' => 'PT-99887766',
                    'merchant_order_id' => 'ORD-2026-PT-001',
                    'order_status' => 'Pickup_Requested',
                    'delivery_fee' => 70,
                ],
            ], 200),
        ]);

        $order = $this->createDummyOrder();
        $adapter = app(PathaoCourierAdapter::class);

        $result = $adapter->bookConsignment($order);

        $this->assertTrue($result->success);
        $this->assertSame('PT-99887766', $result->consignmentId);
        $this->assertSame('PT-99887766', $result->trackingCode);
        $this->assertSame(CourierConsignment::STATUS_BOOKED, $result->status);

        $this->assertDatabaseHas('courier_consignments', [
            'order_id' => $order->id,
            'courier_code' => 'pathao',
            'consignment_id' => 'PT-99887766',
            'status' => CourierConsignment::STATUS_BOOKED,
        ]);

        $freshOrder = $order->fresh();
        $this->assertSame('pathao', $freshOrder->courier_code);
        $this->assertSame('PT-99887766', $freshOrder->courier_consignment_id);
    }

    public function test_pathao_adapter_booking_handles_error(): void
    {
        Http::fake([
            'https://courier-api-sandbox.pathao.com/aladdin/api/v1/issue-token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 86400,
            ], 200),
            'https://courier-api-sandbox.pathao.com/aladdin/api/v1/orders/issue-order' => Http::response([
                'type' => 'error',
                'code' => 422,
                'message' => 'Invalid recipient phone number',
            ], 422),
        ]);

        $order = $this->createDummyOrder();
        $adapter = app(PathaoCourierAdapter::class);

        $result = $adapter->bookConsignment($order);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid recipient phone number', $result->errorMessage);
    }

    public function test_pathao_adapter_tracks_consignment(): void
    {
        Http::fake([
            'https://courier-api-sandbox.pathao.com/aladdin/api/v1/issue-token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 86400,
            ], 200),
            'https://courier-api-sandbox.pathao.com/aladdin/api/v1/orders/PT-99887766/info' => Http::response([
                'type' => 'success',
                'data' => [
                    'consignment_id' => 'PT-99887766',
                    'order_status' => 'Delivered',
                    'current_hub' => 'Gulshan Sorting Hub',
                    'delivered_at' => '2026-10-29 17:30:00',
                    'timeline' => [
                        ['status' => 'Picked_Up', 'time' => '2026-10-29 11:00:00'],
                        ['status' => 'Delivered', 'time' => '2026-10-29 17:30:00'],
                    ],
                ],
            ], 200),
        ]);

        $adapter = app(PathaoCourierAdapter::class);
        $result = $adapter->trackConsignment('PT-99887766');

        $this->assertTrue($result->success);
        $this->assertSame(CourierConsignment::STATUS_DELIVERED, $result->status);
        $this->assertSame('Gulshan Sorting Hub', $result->currentLocation);
        $this->assertSame('2026-10-29 17:30:00', $result->deliveredAt);
        $this->assertCount(2, $result->events);
    }

    public function test_pathao_adapter_test_connection_success(): void
    {
        Http::fake([
            'https://courier-api-sandbox.pathao.com/aladdin/api/v1/issue-token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 86400,
            ], 200),
            'https://courier-api-sandbox.pathao.com/aladdin/api/v1/stores' => Http::response([
                'data' => [
                    'data' => [
                        ['store_id' => 10293, 'store_name' => 'Main Warehouse Dhaka'],
                        ['store_id' => 10294, 'store_name' => 'Chittagong Hub'],
                    ],
                ],
            ], 200),
        ]);

        $adapter = app(PathaoCourierAdapter::class);
        $result = $adapter->testConnection($this->setting);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Found 2 pickup store(s)', $result->message);
        $this->assertNotNull($result->latencyMs);
    }

    public function test_pathao_adapter_test_connection_failure(): void
    {
        Http::fake([
            'https://courier-api-sandbox.pathao.com/aladdin/api/v1/issue-token' => Http::response([
                'error' => 'invalid_client',
                'message' => 'Client authentication failed',
            ], 401),
        ]);

        $adapter = app(PathaoCourierAdapter::class);
        $result = $adapter->testConnection($this->setting);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unable to acquire OAuth access token', $result->message);
    }

    public function test_pathao_webhook_controller_handles_delivered_event(): void
    {
        $order = $this->createDummyOrder();
        $consignment = CourierConsignment::create([
            'order_id' => $order->id,
            'courier_code' => 'pathao',
            'consignment_id' => 'PT-55443322',
            'tracking_code' => 'PT-55443322',
            'status' => CourierConsignment::STATUS_BOOKED,
            'delivery_zone' => 'inside_dhaka',
            'delivery_fee_bdt' => '70.0000',
            'cod_amount_bdt' => '3270.0000',
            'recipient_name' => 'Kazi Nazrul',
            'recipient_phone' => '+8801811223344',
            'recipient_address' => 'Road 12, Gulshan 1, Dhaka',
        ]);

        $response = $this->postJson(route('webhooks.courier.pathao'), [
            'consignment_id' => 'PT-55443322',
            'merchant_order_id' => 'ORD-2026-PT-001',
            'order_status' => 'Delivered',
            'collected_amount' => 3270,
            'delivery_fee' => 70,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('status_mapped', CourierConsignment::STATUS_DELIVERED);

        $freshConsignment = $consignment->fresh();
        $this->assertSame(CourierConsignment::STATUS_DELIVERED, $freshConsignment->status);
        $this->assertNotNull($freshConsignment->delivered_at);

        $freshOrder = $order->fresh();
        $this->assertSame(Order::STATUS_DELIVERED, $freshOrder->status);
        $this->assertSame(Order::PAYMENT_PAID, $freshOrder->payment_status);
        $this->assertSame('0.0000', (string) $freshOrder->payable_cod_amount_bdt);
        $this->assertNotNull($freshOrder->delivered_at);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => Order::STATUS_DELIVERED,
        ]);
    }

    public function test_pathao_webhook_controller_handles_returned_event(): void
    {
        $order = $this->createDummyOrder();
        $consignment = CourierConsignment::create([
            'order_id' => $order->id,
            'courier_code' => 'pathao',
            'consignment_id' => 'PT-77889900',
            'tracking_code' => 'PT-77889900',
            'status' => CourierConsignment::STATUS_BOOKED,
            'delivery_zone' => 'inside_dhaka',
            'delivery_fee_bdt' => '70.0000',
            'cod_amount_bdt' => '3270.0000',
            'recipient_name' => 'Kazi Nazrul',
            'recipient_phone' => '+8801811223344',
            'recipient_address' => 'Road 12, Gulshan 1, Dhaka',
        ]);

        $response = $this->postJson(route('webhooks.courier.pathao'), [
            'consignment_id' => 'PT-77889900',
            'merchant_order_id' => 'ORD-2026-PT-001',
            'order_status' => 'Returned',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('status_mapped', CourierConsignment::STATUS_RETURNED);

        $freshConsignment = $consignment->fresh();
        $this->assertSame(CourierConsignment::STATUS_RETURNED, $freshConsignment->status);
        $this->assertNotNull($freshConsignment->returned_at);

        $freshOrder = $order->fresh();
        $this->assertSame(Order::STATUS_RETURNED, $freshOrder->status);
        $this->assertNotNull($freshOrder->cancelled_at);
    }

    public function test_pathao_webhook_controller_validates_secret(): void
    {
        $this->setting->update([
            'webhook_secret' => 'super_secret_pathao_signature_key',
        ]);

        $order = $this->createDummyOrder();
        CourierConsignment::create([
            'order_id' => $order->id,
            'courier_code' => 'pathao',
            'consignment_id' => 'PT-SECRET-TEST',
            'status' => CourierConsignment::STATUS_BOOKED,
            'recipient_name' => 'Kazi Nazrul',
            'recipient_phone' => '+8801811223344',
            'recipient_address' => 'Dhaka',
        ]);

        // Unauthorized request without secret
        $unauth = $this->postJson(route('webhooks.courier.pathao'), [
            'consignment_id' => 'PT-SECRET-TEST',
            'order_status' => 'Delivered',
        ]);
        $unauth->assertStatus(401);

        // Authorized request with secret header
        $auth = $this->withHeaders([
            'X-Pathao-Signature' => 'super_secret_pathao_signature_key',
        ])->postJson(route('webhooks.courier.pathao'), [
            'consignment_id' => 'PT-SECRET-TEST',
            'order_status' => 'Delivered',
        ]);
        $auth->assertOk();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Logistics\Adapters\SteadfastCourierAdapter;
use App\Modules\Logistics\Models\CourierConsignment;
use App\Modules\Logistics\Models\CourierGatewaySetting;
use App\Modules\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SteadfastCourierAdapterTest extends TestCase
{
    use RefreshDatabase;

    private CourierGatewaySetting $setting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setting = CourierGatewaySetting::create([
            'code' => CourierGatewaySetting::CODE_STEADFAST,
            'name' => 'Steadfast Courier',
            'is_active' => true,
            'is_sandbox' => true,
            'base_url' => 'https://portal.packzy.com/api/v1',
            'supported_features' => ['booking', 'tracking', 'balance', 'webhook'],
            'sort_order' => 1,
            'credentials' => [
                'api_key' => 'test_api_key_123',
                'secret_key' => 'test_secret_key_456',
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
            'order_number' => 'ORD-2026-ST-001',
            'customer_name' => 'Shakil Ahmed',
            'customer_phone' => '+8801700000000',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'House 10, Road 4, Dhanmondi, Dhaka',
            'subtotal_bdt' => '2500.0000',
            'shipping_fee_bdt' => '70.0000',
            'total_bdt' => '2570.0000',
            'payable_cod_amount_bdt' => '2570.0000',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'cash_on_delivery',
            'fraud_risk_score' => 10,
            'fraud_risk_level' => 'low',
        ]);
    }

    public function test_steadfast_adapter_books_consignment_successfully(): void
    {
        Http::fake([
            'https://portal.packzy.com/api/v1/create_order' => Http::response([
                'status' => 200,
                'message' => 'Order created successfully',
                'consignment' => [
                    'consignment_id' => 987654,
                    'invoice' => 'ORD-2026-ST-001',
                    'tracking_code' => 'ST987654321',
                    'recipient_name' => 'Shakil Ahmed',
                    'recipient_phone' => '+8801700000000',
                    'recipient_address' => 'House 10, Road 4, Dhanmondi, Dhaka',
                    'cod_amount' => 2570,
                    'status' => 'in_review',
                ],
            ], 200),
        ]);

        $order = $this->createDummyOrder();
        $adapter = app(SteadfastCourierAdapter::class);

        $result = $adapter->bookConsignment($order);

        $this->assertTrue($result->success);
        $this->assertSame('987654', $result->consignmentId);
        $this->assertSame('ST987654321', $result->trackingCode);
        $this->assertSame(CourierConsignment::STATUS_BOOKED, $result->status);

        // Verify database consignment record
        $this->assertDatabaseHas('courier_consignments', [
            'order_id' => $order->id,
            'courier_code' => 'steadfast',
            'consignment_id' => '987654',
            'tracking_code' => 'ST987654321',
            'status' => CourierConsignment::STATUS_BOOKED,
        ]);

        // Verify order is updated
        $freshOrder = $order->fresh();
        $this->assertSame('steadfast', $freshOrder->courier_code);
        $this->assertSame('987654', $freshOrder->courier_consignment_id);
        $this->assertSame('ST987654321', $freshOrder->courier_tracking_code);
    }

    public function test_steadfast_adapter_booking_handles_error(): void
    {
        Http::fake([
            'https://portal.packzy.com/api/v1/create_order' => Http::response([
                'status' => 400,
                'message' => 'Invalid phone number or address',
            ], 400),
        ]);

        $order = $this->createDummyOrder();
        $adapter = app(SteadfastCourierAdapter::class);

        $result = $adapter->bookConsignment($order);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid phone number or address', $result->errorMessage);
    }

    public function test_steadfast_adapter_tracks_consignment_by_id(): void
    {
        Http::fake([
            'https://portal.packzy.com/api/v1/status_by_cid/987654' => Http::response([
                'status' => 200,
                'delivery_status' => 'delivered',
                'current_hub' => 'Dhanmondi Hub',
                'delivered_at' => '2026-10-29 16:00:00',
                'timeline' => [
                    ['status' => 'picked_up', 'time' => '2026-10-29 10:00:00'],
                    ['status' => 'delivered', 'time' => '2026-10-29 16:00:00'],
                ],
            ], 200),
        ]);

        $adapter = app(SteadfastCourierAdapter::class);
        $result = $adapter->trackConsignment('987654');

        $this->assertTrue($result->success);
        $this->assertSame(CourierConsignment::STATUS_DELIVERED, $result->status);
        $this->assertSame('Dhanmondi Hub', $result->currentLocation);
        $this->assertSame('2026-10-29 16:00:00', $result->deliveredAt);
        $this->assertCount(2, $result->events);
    }

    public function test_steadfast_adapter_test_connection_success(): void
    {
        Http::fake([
            'https://portal.packzy.com/api/v1/get_balance' => Http::response([
                'status' => 200,
                'current_balance' => 12500.50,
            ], 200),
        ]);

        $adapter = app(SteadfastCourierAdapter::class);
        $result = $adapter->testConnection($this->setting);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Steadfast API connected successfully', $result->message);
        $this->assertStringContainsString('12500.5', $result->message);
        $this->assertNotNull($result->latencyMs);
    }

    public function test_steadfast_adapter_test_connection_failure(): void
    {
        Http::fake([
            'https://portal.packzy.com/api/v1/get_balance' => Http::response([
                'status' => 401,
                'message' => 'Unauthorized: Invalid Api-Key or Secret-Key',
            ], 401),
        ]);

        $adapter = app(SteadfastCourierAdapter::class);
        $result = $adapter->testConnection($this->setting);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unauthorized', $result->message);
    }

    public function test_steadfast_webhook_controller_handles_delivered_event(): void
    {
        $order = $this->createDummyOrder();
        $consignment = CourierConsignment::create([
            'order_id' => $order->id,
            'courier_code' => 'steadfast',
            'consignment_id' => '987654',
            'tracking_code' => 'ST987654321',
            'status' => CourierConsignment::STATUS_BOOKED,
            'delivery_zone' => 'inside_dhaka',
            'delivery_fee_bdt' => '70.0000',
            'cod_amount_bdt' => '2570.0000',
            'recipient_name' => 'Shakil Ahmed',
            'recipient_phone' => '+8801700000000',
            'recipient_address' => 'House 10, Road 4, Dhanmondi, Dhaka',
        ]);

        $response = $this->postJson(route('webhooks.courier.steadfast'), [
            'consignment_id' => 987654,
            'invoice' => 'ORD-2026-ST-001',
            'status' => 'delivered',
            'cod_amount' => 2570,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('status_mapped', CourierConsignment::STATUS_DELIVERED);

        // Verify consignment updated
        $freshConsignment = $consignment->fresh();
        $this->assertSame(CourierConsignment::STATUS_DELIVERED, $freshConsignment->status);
        $this->assertNotNull($freshConsignment->delivered_at);
        $this->assertSame('delivered', $freshConsignment->last_webhook_payload['status']);

        // Verify order transitioned to delivered
        $freshOrder = $order->fresh();
        $this->assertSame(Order::STATUS_DELIVERED, $freshOrder->status);
        $this->assertSame(Order::PAYMENT_PAID, $freshOrder->payment_status);
        $this->assertSame('0.0000', (string) $freshOrder->payable_cod_amount_bdt);
        $this->assertNotNull($freshOrder->delivered_at);

        // Verify audit trail
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => Order::STATUS_DELIVERED,
        ]);
    }

    public function test_steadfast_webhook_controller_handles_returned_event(): void
    {
        $order = $this->createDummyOrder();
        $consignment = CourierConsignment::create([
            'order_id' => $order->id,
            'courier_code' => 'steadfast',
            'consignment_id' => '112233',
            'tracking_code' => 'ST1122334455',
            'status' => CourierConsignment::STATUS_BOOKED,
            'delivery_zone' => 'inside_dhaka',
            'delivery_fee_bdt' => '70.0000',
            'cod_amount_bdt' => '2570.0000',
            'recipient_name' => 'Shakil Ahmed',
            'recipient_phone' => '+8801700000000',
            'recipient_address' => 'House 10, Road 4, Dhanmondi, Dhaka',
        ]);

        $response = $this->postJson(route('webhooks.courier.steadfast'), [
            'consignment_id' => 112233,
            'invoice' => 'ORD-2026-ST-001',
            'status' => 'cancelled',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('status_mapped', CourierConsignment::STATUS_CANCELLED);

        $freshConsignment = $consignment->fresh();
        $this->assertSame(CourierConsignment::STATUS_CANCELLED, $freshConsignment->status);
        $this->assertNotNull($freshConsignment->returned_at);

        $freshOrder = $order->fresh();
        $this->assertSame(Order::STATUS_RETURNED, $freshOrder->status);
        $this->assertNotNull($freshOrder->cancelled_at);
    }

    public function test_steadfast_webhook_controller_validates_secret_if_configured(): void
    {
        $this->setting->update([
            'webhook_secret' => 'super_secret_steadfast_webhook_key',
        ]);

        $order = $this->createDummyOrder();
        CourierConsignment::create([
            'order_id' => $order->id,
            'courier_code' => 'steadfast',
            'consignment_id' => '556677',
            'status' => CourierConsignment::STATUS_BOOKED,
            'recipient_name' => 'Shakil',
            'recipient_phone' => '+8801700000000',
            'recipient_address' => 'Dhaka',
        ]);

        // Unauthorized request without secret
        $unauth = $this->postJson(route('webhooks.courier.steadfast'), [
            'consignment_id' => 556677,
            'status' => 'delivered',
        ]);
        $unauth->assertStatus(401);

        // Authorized request with secret header
        $auth = $this->withHeaders([
            'X-Webhook-Secret' => 'super_secret_steadfast_webhook_key',
        ])->postJson(route('webhooks.courier.steadfast'), [
            'consignment_id' => 556677,
            'status' => 'delivered',
        ]);
        $auth->assertOk();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Logistics\Actions\TestCourierConnectionAction;
use App\Modules\Logistics\Models\CourierConsignment;
use App\Modules\Logistics\Models\CourierGatewaySetting;
use App\Modules\Logistics\Services\CourierGatewayManager;
use App\Modules\Order\Models\Order;
use Database\Seeders\CourierGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\MockCourierAdapter;
use Tests\TestCase;

class CourierGatewaySettingsTest extends TestCase
{
    use RefreshDatabase;

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
            'order_number' => 'ORD-2026-LOG-001',
            'customer_name' => 'Tanvir Hasan',
            'customer_phone' => '+8801712345678',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'House 42, Road 11, Banani, Dhaka',
            'subtotal_bdt' => '1500.0000',
            'shipping_fee_bdt' => '70.0000',
            'total_bdt' => '1570.0000',
            'payable_cod_amount_bdt' => '1570.0000',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'cash_on_delivery',
            'fraud_risk_score' => 15,
            'fraud_risk_level' => 'low',
        ]);
    }

    public function test_courier_gateway_setting_can_be_created_with_encrypted_credentials(): void
    {
        $setting = CourierGatewaySetting::create([
            'code' => CourierGatewaySetting::CODE_STEADFAST,
            'name' => 'Steadfast Courier',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => [
                'api_key' => 'steadfast_api_key_test_123',
                'secret_key' => 'steadfast_secret_xyz_987',
            ],
            'base_url' => 'https://portal.packzy.com/api/v1',
            'supported_features' => ['booking', 'tracking', 'balance'],
            'sort_order' => 1,
        ]);

        $this->assertNotEmpty($setting->ulid);
        $this->assertSame('steadfast', $setting->code);
        $this->assertTrue($setting->is_active);
        $this->assertTrue($setting->is_sandbox);

        // Raw database check ensures encryption at rest
        $raw = DB::table('courier_gateway_settings')->where('id', $setting->id)->first();
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('steadfast_api_key_test_123', $raw->credentials);

        // Decrypted model check
        $fresh = $setting->fresh();
        $this->assertSame('steadfast_api_key_test_123', $fresh->credentials['api_key']);
        $this->assertSame('steadfast_api_key_test_123', $fresh->getCredential('api_key'));
        $this->assertSame('steadfast_secret_xyz_987', $fresh->getCredential('secret_key'));
    }

    public function test_masked_credentials_masks_sensitive_values(): void
    {
        $setting = CourierGatewaySetting::create([
            'code' => CourierGatewaySetting::CODE_PATHAO,
            'name' => 'Pathao Courier',
            'credentials' => [
                'client_id' => 'client_12345678',
                'client_secret' => 'super_secret_token_abcdef',
                'username' => 'merchant@pathao.com',
                'password' => 'secret_pass_1234',
                'store_id' => '10293',
            ],
        ]);

        $masked = $setting->getMaskedCredentials();

        $this->assertStringContainsString('••••••••5678', $masked['client_id']);
        $this->assertStringContainsString('••••••••cdef', $masked['client_secret']);
        $this->assertStringContainsString('••••••••1234', $masked['password']);
        $this->assertStringNotContainsString('super_secret_token_abcdef', $masked['client_secret']);
    }

    public function test_is_configured_evaluates_required_credentials(): void
    {
        $setting = new CourierGatewaySetting(['code' => CourierGatewaySetting::CODE_STEADFAST]);
        $this->assertFalse($setting->isConfigured());

        $setting->credentials = ['api_key' => 'key1'];
        $this->assertFalse($setting->isConfigured());

        $setting->credentials = ['api_key' => 'key1', 'secret_key' => 'secret1'];
        $this->assertTrue($setting->isConfigured());

        $pathao = new CourierGatewaySetting([
            'code' => CourierGatewaySetting::CODE_PATHAO,
            'credentials' => [
                'client_id' => 'cid',
                'client_secret' => 'csec',
                'username' => 'user',
                'password' => 'pass',
            ],
        ]);
        $this->assertTrue($pathao->isConfigured());

        $redx = new CourierGatewaySetting([
            'code' => CourierGatewaySetting::CODE_REDX,
            'credentials' => ['token' => 'redx_auth_token'],
        ]);
        $this->assertTrue($redx->isConfigured());
    }

    public function test_courier_consignment_can_be_created_and_linked_to_order(): void
    {
        $order = $this->createDummyOrder();

        $consignment = CourierConsignment::create([
            'order_id' => $order->id,
            'courier_code' => 'steadfast',
            'consignment_id' => 'ST-998877',
            'tracking_code' => 'TRK-STEADFAST-001',
            'status' => CourierConsignment::STATUS_BOOKED,
            'delivery_zone' => 'inside_dhaka',
            'delivery_fee_bdt' => '70.0000',
            'cod_amount_bdt' => '1570.0000',
            'recipient_name' => 'Tanvir Hasan',
            'recipient_phone' => '+8801712345678',
            'recipient_address' => 'House 42, Road 11, Banani, Dhaka',
            'dispatched_at' => now(),
        ]);

        $this->assertNotEmpty($consignment->ulid);
        $this->assertSame($order->id, $consignment->order->id);

        $orderFresh = $order->fresh();
        $this->assertCount(1, $orderFresh->consignments);
        $this->assertNotNull($orderFresh->latestConsignment);
        $this->assertSame('ST-998877', $orderFresh->latestConsignment->consignment_id);

        // Test scopes
        $this->assertSame(1, CourierConsignment::activeTracking()->count());
        $this->assertSame(0, CourierConsignment::delivered()->count());
        $this->assertSame(1, CourierConsignment::courier('steadfast')->count());
        $this->assertSame(0, CourierConsignment::courier('pathao')->count());
    }

    public function test_courier_gateway_manager_registers_and_resolves_adapters(): void
    {
        $manager = app(CourierGatewayManager::class);
        $mockAdapter = new MockCourierAdapter('mock_courier', 'Mock Courier Service');

        $manager->registerDriver('mock_courier', $mockAdapter);
        $resolved = $manager->resolve('mock_courier');

        $this->assertSame('mock_courier', $resolved->code());
        $this->assertSame('Mock Courier Service', $resolved->name());

        $this->expectException(InvalidArgumentException::class);
        $manager->resolve('non_existent_courier_xyz');
    }

    public function test_courier_gateway_manager_gets_active_adapter(): void
    {
        $manager = app(CourierGatewayManager::class);
        $mockAdapter = new MockCourierAdapter('mock_active', 'Active Mock Courier');
        $manager->registerDriver('mock_active', $mockAdapter);

        CourierGatewaySetting::create([
            'code' => 'mock_active',
            'name' => 'Active Mock Courier',
            'is_active' => true,
            'sort_order' => 1,
            'credentials' => ['api_key' => 'test'],
        ]);

        $activeAdapter = $manager->getActive();
        $this->assertNotNull($activeAdapter);
        $this->assertSame('mock_active', $activeAdapter->code());
    }

    public function test_test_courier_connection_action_executes_and_updates_status(): void
    {
        $manager = app(CourierGatewayManager::class);
        $mockAdapter = new MockCourierAdapter('mock_test_gw', 'Mock Test Gateway');
        $manager->registerDriver('mock_test_gw', $mockAdapter);

        $setting = CourierGatewaySetting::create([
            'code' => 'mock_test_gw',
            'name' => 'Mock Test Gateway',
            'is_active' => true,
            'sort_order' => 1,
            'credentials' => ['api_key' => 'valid_key'],
        ]);

        $action = app(TestCourierConnectionAction::class);
        $result = $action($setting);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Mock connection successful', $result->message);

        $fresh = $setting->fresh();
        $this->assertNotNull($fresh->last_tested_at);
        $this->assertSame('success', $fresh->last_test_status);
        $this->assertStringContainsString('Mock connection successful', $fresh->last_test_message);
    }

    public function test_test_courier_connection_action_handles_failure(): void
    {
        $manager = app(CourierGatewayManager::class);
        $failingAdapter = new MockCourierAdapter('mock_fail_gw', 'Failing Gateway', shouldFail: true);
        $manager->registerDriver('mock_fail_gw', $failingAdapter);

        $setting = CourierGatewaySetting::create([
            'code' => 'mock_fail_gw',
            'name' => 'Failing Gateway',
            'is_active' => true,
            'credentials' => ['api_key' => 'bad_key'],
        ]);

        $action = app(TestCourierConnectionAction::class);
        $result = $action($setting);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Mock connection failed', $result->message);

        $fresh = $setting->fresh();
        $this->assertSame('failed', $fresh->last_test_status);
    }

    public function test_courier_gateway_seeder_initializes_default_couriers(): void
    {
        $this->seed(CourierGatewaySeeder::class);

        $this->assertDatabaseHas('courier_gateway_settings', ['code' => 'steadfast', 'name' => 'Steadfast Courier']);
        $this->assertDatabaseHas('courier_gateway_settings', ['code' => 'pathao', 'name' => 'Pathao Courier']);
        $this->assertDatabaseHas('courier_gateway_settings', ['code' => 'redx', 'name' => 'RedX Delivery']);
        $this->assertDatabaseHas('courier_gateway_settings', ['code' => 'paperfly', 'name' => 'Paperfly Courier']);
        $this->assertDatabaseHas('courier_gateway_settings', ['code' => 'sundarban', 'name' => 'Sundarban Courier Service']);

        $this->assertSame(5, CourierGatewaySetting::count());
    }
}

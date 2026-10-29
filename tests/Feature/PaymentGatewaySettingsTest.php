<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Actions\TestGatewayConnectionAction;
use App\Modules\Payment\Contracts\PaymentGatewayContract;
use App\Modules\Payment\Models\PaymentGatewaySetting;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\Payment\Services\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\FailingPaymentAdapter;
use Tests\TestCase;

class PaymentGatewaySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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
            'order_number' => 'ORD-20261029-001',
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'cash_on_delivery',
            'customer_name' => 'Tanvir Ahmed',
            'customer_phone' => '01712345678',
            'customer_email' => 'tanvir@example.com',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'House 12, Road 4, Dhanmondi, Dhaka',
            'subtotal_bdt' => 1500.0000,
            'shipping_fee_bdt' => 70.0000,
            'total_bdt' => 1570.0000,
            'payable_cod_amount_bdt' => 1570.0000,
        ]);
    }

    public function test_payment_gateway_setting_can_be_created_with_encrypted_credentials(): void
    {
        $setting = PaymentGatewaySetting::create([
            'code' => PaymentGatewaySetting::CODE_BKASH,
            'name' => 'bKash Tokenized Checkout',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => [
                'app_key' => 'live_app_key_abc123',
                'app_secret' => 'super_secret_app_secret_xyz789',
                'username' => 'merchant_user',
                'password' => 'merchant_pass_secret',
            ],
            'surcharge_percentage' => 1.50,
            'sort_order' => 1,
        ]);

        $this->assertNotEmpty($setting->ulid);
        $this->assertEquals(PaymentGatewaySetting::CODE_BKASH, $setting->code);
        $this->assertTrue($setting->is_active);
        $this->assertTrue($setting->is_sandbox);
        $this->assertEquals('1.50', (string) $setting->surcharge_percentage);

        // Verify credentials decrypted via model
        $this->assertEquals('live_app_key_abc123', $setting->getCredential('app_key'));
        $this->assertEquals('super_secret_app_secret_xyz789', $setting->getCredential('app_secret'));

        // Verify raw credentials in database are encrypted
        $raw = DB::table('payment_gateway_settings')->where('id', $setting->id)->first();
        $this->assertNotNull($raw);
        $this->assertNotEquals('live_app_key_abc123', $raw->credentials);
        $this->assertStringNotContainsString('super_secret_app_secret_xyz789', $raw->credentials);
    }

    public function test_masked_credentials_masks_sensitive_values(): void
    {
        $setting = PaymentGatewaySetting::create([
            'code' => PaymentGatewaySetting::CODE_BKASH,
            'name' => 'bKash Tokenized Checkout',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => [
                'app_key' => 'live_app_key_abc123',
                'app_secret' => 'very_long_secret_key_9999',
                'short' => '123',
            ],
        ]);

        $masked = $setting->getMaskedCredentials();

        $this->assertArrayHasKey('app_key', $masked);
        $this->assertStringEndsWith('c123', $masked['app_key']);
        $this->assertStringStartsWith('••••••••', $masked['app_key']);

        $this->assertArrayHasKey('app_secret', $masked);
        $this->assertStringEndsWith('9999', $masked['app_secret']);

        $this->assertArrayHasKey('short', $masked);
        $this->assertEquals('••••', $masked['short']);
    }

    public function test_is_configured_evaluates_required_credentials(): void
    {
        $bkashUnconfigured = PaymentGatewaySetting::create([
            'code' => PaymentGatewaySetting::CODE_BKASH,
            'name' => 'bKash',
            'credentials' => ['app_key' => 'key_only'],
        ]);
        $this->assertFalse($bkashUnconfigured->isConfigured());

        $bkashConfigured = PaymentGatewaySetting::create([
            'code' => PaymentGatewaySetting::CODE_BKASH.'_test',
            'name' => 'bKash Full',
            'credentials' => [
                'app_key' => 'app_key_val',
                'app_secret' => 'app_secret_val',
                'username' => 'user_val',
                'password' => 'pass_val',
            ],
        ]);
        // Change code to bkash to test method match
        $bkashConfigured->code = PaymentGatewaySetting::CODE_BKASH;
        $this->assertTrue($bkashConfigured->isConfigured());

        $cod = PaymentGatewaySetting::create([
            'code' => PaymentGatewaySetting::CODE_COD,
            'name' => 'Cash on Delivery',
            'credentials' => null,
        ]);
        $this->assertTrue($cod->isConfigured());
    }

    public function test_active_and_ordered_scopes(): void
    {
        PaymentGatewaySetting::create([
            'code' => PaymentGatewaySetting::CODE_COD,
            'name' => 'COD',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        PaymentGatewaySetting::create([
            'code' => PaymentGatewaySetting::CODE_BKASH,
            'name' => 'bKash',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        PaymentGatewaySetting::create([
            'code' => PaymentGatewaySetting::CODE_NAGAD,
            'name' => 'Nagad',
            'is_active' => false,
            'sort_order' => 3,
        ]);

        $activeOrdered = PaymentGatewaySetting::query()->active()->ordered()->get();

        $this->assertCount(2, $activeOrdered);
        $this->assertEquals(PaymentGatewaySetting::CODE_BKASH, $activeOrdered->first()->code);
        $this->assertEquals(PaymentGatewaySetting::CODE_COD, $activeOrdered->last()->code);
    }

    public function test_payment_transaction_can_be_created_and_linked_to_order(): void
    {
        $order = $this->createDummyOrder();

        $trx = PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway_code' => PaymentGatewaySetting::CODE_BKASH,
            'transaction_id' => 'TRX-BKASH-778899',
            'payment_purpose' => PaymentTransaction::PURPOSE_ORDER_FULL,
            'amount_bdt' => 1570.0000,
            'gateway_fee_bdt' => 23.5500,
            'currency' => 'BDT',
            'status' => PaymentTransaction::STATUS_SUCCESSFUL,
            'customer_account' => '01712345678',
            'raw_response_payload' => ['statusCode' => '0000', 'statusMessage' => 'Successful'],
        ]);

        $this->assertNotEmpty($trx->ulid);
        $this->assertTrue($trx->isSuccessful());
        $this->assertEquals($order->id, $trx->order->id);

        $orderTransactions = $order->transactions;
        $this->assertCount(1, $orderTransactions);
        $this->assertEquals('TRX-BKASH-778899', $orderTransactions->first()->transaction_id);

        $successful = PaymentTransaction::successful()->forOrder($order->id)->get();
        $this->assertCount(1, $successful);
    }

    public function test_payment_gateway_manager_resolves_default_drivers(): void
    {
        $manager = app(PaymentGatewayManager::class);

        $codDriver = $manager->resolve(PaymentGatewaySetting::CODE_COD);
        $this->assertInstanceOf(PaymentGatewayContract::class, $codDriver);
        $this->assertEquals(PaymentGatewaySetting::CODE_COD, $codDriver->getGatewayCode());

        $bankDriver = $manager->resolve(PaymentGatewaySetting::CODE_BANK_TRANSFER);
        $this->assertInstanceOf(PaymentGatewayContract::class, $bankDriver);
        $this->assertEquals(PaymentGatewaySetting::CODE_BANK_TRANSFER, $bankDriver->getGatewayCode());

        $this->expectException(InvalidArgumentException::class);
        $manager->resolve('unregistered_cryptic_gateway');
    }

    public function test_test_gateway_connection_action_executes_and_updates_status(): void
    {
        $setting = PaymentGatewaySetting::create([
            'code' => PaymentGatewaySetting::CODE_COD,
            'name' => 'Cash on Delivery',
            'is_active' => true,
        ]);

        $action = app(TestGatewayConnectionAction::class);
        $result = $action(PaymentGatewaySetting::CODE_COD);

        $this->assertTrue($result->success);
        $this->assertEquals('Cash on Delivery gateway is enabled and operational', $result->message);

        $setting->refresh();
        $this->assertNotNull($setting->last_tested_at);
        $this->assertEquals('successful', $setting->last_test_status);
        $this->assertStringContainsString('operational', $setting->last_test_message);
    }

    public function test_test_gateway_connection_action_handles_failures(): void
    {
        $setting = PaymentGatewaySetting::create([
            'code' => 'custom_failing_gw',
            'name' => 'Failing Gateway',
            'is_active' => true,
        ]);

        $manager = app(PaymentGatewayManager::class);
        $manager->registerDriver('custom_failing_gw', new FailingPaymentAdapter);

        $action = app(TestGatewayConnectionAction::class);
        $result = $action('custom_failing_gw');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid Secret Key', $result->message);

        $setting->refresh();
        $this->assertEquals('failed', $setting->last_test_status);
        $this->assertStringContainsString('Invalid Secret Key', $setting->last_test_message);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Actions\ProcessAdvanceDeliveryPaymentAction;
use App\Modules\Payment\Models\PaymentGatewaySetting;
use App\Modules\Payment\Models\PaymentTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentAdminTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        return User::factory()->create([
            'email' => 'admin@example.com',
        ]);
    }

    private function createOrder(float $total = 1570.00, float $shippingFee = 100.00): Order
    {
        $division = BangladeshDivision::create(['name_en' => 'Dhaka', 'name_bn' => 'ঢাকা']);
        $district = BangladeshDistrict::create([
            'division_id' => $division->id,
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'zone_type' => 'inside_dhaka',
        ]);

        return Order::create([
            'order_number' => 'ORD-20261029-ADV01',
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'cash_on_delivery',
            'customer_name' => 'Kazi Shakil',
            'customer_phone' => '01712345678',
            'customer_email' => 'shakil@example.com',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Mirpur, Dhaka',
            'subtotal_bdt' => $total - $shippingFee,
            'shipping_fee_bdt' => $shippingFee,
            'total_bdt' => $total,
            'payable_cod_amount_bdt' => $total,
        ]);
    }

    public function test_admin_can_view_payment_gateway_settings_page(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->get(route('admin.settings.payment-gateways.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Settings/PaymentGatewaySettings')
            ->has('gateways')
            ->has('gateways', 5)
        );
    }

    public function test_admin_can_save_payment_gateway_credentials(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->post(route('admin.settings.payment-gateways.save'), [
            'code' => PaymentGatewaySetting::CODE_BKASH,
            'name' => 'bKash Instant Checkout',
            'is_active' => true,
            'is_sandbox' => false,
            'surcharge_percentage' => 1.75,
            'sort_order' => 1,
            'credentials' => [
                'app_key' => 'new_live_key_999',
                'app_secret' => 'super_secret_live_value',
                'username' => 'merchant_shakil',
                'password' => 'secret_pass_123',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setting = PaymentGatewaySetting::where('code', PaymentGatewaySetting::CODE_BKASH)->first();
        $this->assertNotNull($setting);
        $this->assertTrue($setting->is_active);
        $this->assertFalse($setting->is_sandbox);
        $this->assertEquals('1.75', (string) $setting->surcharge_percentage);
        $this->assertEquals('new_live_key_999', $setting->getCredential('app_key'));
        $this->assertEquals('super_secret_live_value', $setting->getCredential('app_secret'));
    }

    public function test_admin_saving_preserves_existing_secrets_when_masked(): void
    {
        $admin = $this->createAdminUser();

        // 1. Initial save with real secret
        PaymentGatewaySetting::create([
            'code' => PaymentGatewaySetting::CODE_BKASH,
            'name' => 'bKash',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => [
                'app_key' => 'initial_key_111',
                'app_secret' => 'original_secret_8888',
                'username' => 'merchant_user',
                'password' => 'initial_pass_4444',
            ],
        ]);

        // 2. Second save where form sends masked placeholders
        $response = $this->actingAs($admin)->post(route('admin.settings.payment-gateways.save'), [
            'code' => PaymentGatewaySetting::CODE_BKASH,
            'name' => 'bKash Updated Name',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => [
                'app_key' => 'updated_key_222',
                'app_secret' => '••••••••8888', // Masked placeholder should not overwrite original
                'username' => 'merchant_user',
                'password' => '••••••••4444', // Masked placeholder
            ],
        ]);

        $response->assertRedirect();

        $setting = PaymentGatewaySetting::where('code', PaymentGatewaySetting::CODE_BKASH)->first();
        $this->assertEquals('updated_key_222', $setting->getCredential('app_key'));
        $this->assertEquals('original_secret_8888', $setting->getCredential('app_secret'));
        $this->assertEquals('initial_pass_4444', $setting->getCredential('password'));
    }

    public function test_admin_can_test_gateway_connection_via_api(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->postJson(route('admin.settings.payment-gateways.test'), [
            'code' => PaymentGatewaySetting::CODE_COD,
            'is_sandbox' => true,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertNotNull($response->json('message'));
    }

    public function test_process_advance_delivery_payment_action_initiates_online_payment(): void
    {
        PaymentGatewaySetting::create([
            'code' => PaymentGatewaySetting::CODE_BKASH,
            'name' => 'bKash',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => [
                'app_key' => 'app_key_1',
                'app_secret' => 'app_secret_1',
                'username' => 'user_1',
                'password' => 'pass_1',
            ],
        ]);

        $order = $this->createOrder(1570.00, 100.00);

        Http::fake([
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'id_token' => 'mock_token',
                'expires_in' => 3600,
            ], 200),
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/create' => Http::response([
                'statusCode' => '0000',
                'statusMessage' => 'Successful',
                'paymentID' => 'TR_ADV_BK_99',
                'bkashURL' => 'https://sandbox.payment.bka.sh/pay/TR_ADV_BK_99',
            ], 200),
        ]);

        $action = app(ProcessAdvanceDeliveryPaymentAction::class);
        $result = $action($order, PaymentGatewaySetting::CODE_BKASH);

        $this->assertTrue($result->success);
        $this->assertEquals('https://sandbox.payment.bka.sh/pay/TR_ADV_BK_99', $result->redirectUrl);

        $transaction = PaymentTransaction::where('transaction_id', 'TR_ADV_BK_99')->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(PaymentTransaction::PURPOSE_ADVANCE_DELIVERY_CHARGE, $transaction->payment_purpose);
        $this->assertEquals('100.0000', (string) $transaction->amount_bdt);
        $this->assertEquals($order->id, $transaction->order_id);
    }

    public function test_process_advance_delivery_payment_action_rejects_cod_gateway(): void
    {
        $order = $this->createOrder();

        $action = app(ProcessAdvanceDeliveryPaymentAction::class);

        $this->expectException(InvalidArgumentException::class);
        $action($order, PaymentGatewaySetting::CODE_COD);
    }

    public function test_storefront_pay_advance_endpoint_redirects_customer(): void
    {
        PaymentGatewaySetting::create([
            'code' => PaymentGatewaySetting::CODE_BKASH,
            'name' => 'bKash',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => [
                'app_key' => 'app_key_1',
                'app_secret' => 'app_secret_1',
                'username' => 'user_1',
                'password' => 'pass_1',
            ],
        ]);

        $order = $this->createOrder(1570.00, 100.00);

        Http::fake([
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'id_token' => 'mock_token',
                'expires_in' => 3600,
            ], 200),
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/create' => Http::response([
                'statusCode' => '0000',
                'paymentID' => 'TR_ADV_WEB_1',
                'bkashURL' => 'https://sandbox.payment.bka.sh/pay/TR_ADV_WEB_1',
            ], 200),
        ]);

        $response = $this->post(route('orders.pay-advance', ['order' => $order->id]), [
            'gateway_code' => 'bkash',
        ]);

        $response->assertRedirect('https://sandbox.payment.bka.sh/pay/TR_ADV_WEB_1');
    }
}

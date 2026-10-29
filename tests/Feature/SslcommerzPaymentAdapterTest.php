<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Actions\TestGatewayConnectionAction;
use App\Modules\Payment\Adapters\SslcommerzPaymentAdapter;
use App\Modules\Payment\Models\PaymentGatewaySetting;
use App\Modules\Payment\Models\PaymentTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SslcommerzPaymentAdapterTest extends TestCase
{
    use RefreshDatabase;

    private function createSslcommerzSetting(bool $sandbox = true): PaymentGatewaySetting
    {
        return PaymentGatewaySetting::create([
            'code' => PaymentGatewaySetting::CODE_SSLCOMMERZ,
            'name' => 'SSLCommerz Secured Checkout',
            'is_active' => true,
            'is_sandbox' => $sandbox,
            'credentials' => [
                'store_id' => 'testbox',
                'store_passwd' => 'qwerty',
            ],
            'surcharge_percentage' => 2.00,
            'sort_order' => 3,
        ]);
    }

    private function createOrder(float $total = 1570.00): Order
    {
        $division = BangladeshDivision::create(['name_en' => 'Dhaka', 'name_bn' => 'ঢাকা']);
        $district = BangladeshDistrict::create([
            'division_id' => $division->id,
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'zone_type' => 'inside_dhaka',
        ]);

        return Order::create([
            'order_number' => 'ORD-20261029-SSLC01',
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'sslcommerz',
            'customer_name' => 'Kazi Shakil',
            'customer_phone' => '01912345678',
            'customer_email' => 'shakil@example.com',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Gulshan 2, Dhaka',
            'subtotal_bdt' => $total - 70.00,
            'shipping_fee_bdt' => 70.00,
            'total_bdt' => $total,
            'payable_cod_amount_bdt' => $total,
        ]);
    }

    public function test_sslcommerz_adapter_initiates_hosted_session(): void
    {
        $this->createSslcommerzSetting(true);
        $order = $this->createOrder(1570.00);

        Http::fake([
            'https://sandbox.sslcommerz.com/gwprocess/v4/api.php' => Http::response([
                'status' => 'SUCCESS',
                'failedreason' => '',
                'sessionkey' => 'SSLC_SESSION_KEY_ABC',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/EasyCheckOut/testbox_abc123',
            ], 200),
        ]);

        $adapter = app(SslcommerzPaymentAdapter::class);
        $result = $adapter->initiatePayment($order, 1570.00);

        $this->assertTrue($result->success);
        $this->assertEquals('https://sandbox.sslcommerz.com/EasyCheckOut/testbox_abc123', $result->redirectUrl);

        $transaction = PaymentTransaction::where('gateway_code', PaymentGatewaySetting::CODE_SSLCOMMERZ)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(PaymentTransaction::STATUS_PENDING, $transaction->status);
        $this->assertEquals('1570.0000', (string) $transaction->amount_bdt);
    }

    public function test_sslcommerz_adapter_initiation_fails_on_gateway_error(): void
    {
        $this->createSslcommerzSetting(true);
        $order = $this->createOrder(1570.00);

        Http::fake([
            'https://sandbox.sslcommerz.com/gwprocess/v4/api.php' => Http::response([
                'status' => 'FAILED',
                'failedreason' => 'Store Credential Error',
            ], 200),
        ]);

        $adapter = app(SslcommerzPaymentAdapter::class);
        $result = $adapter->initiatePayment($order, 1570.00);

        $this->assertFalse($result->success);
        $this->assertEquals('Store Credential Error', $result->errorMessage);
    }

    public function test_sslcommerz_adapter_verifies_successful_transaction_via_validation_api(): void
    {
        $this->createSslcommerzSetting(true);
        $order = $this->createOrder(1570.00);

        $transaction = PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway_code' => PaymentGatewaySetting::CODE_SSLCOMMERZ,
            'transaction_id' => 'SSLC_TRX_999888',
            'payment_purpose' => PaymentTransaction::PURPOSE_ORDER_FULL,
            'amount_bdt' => 1570.00,
            'status' => PaymentTransaction::STATUS_PENDING,
        ]);

        Http::fake([
            'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php*' => Http::response([
                'status' => 'VALID',
                'tran_id' => 'SSLC_TRX_999888',
                'val_id' => 'VAL_ID_20261029_001',
                'amount' => '1570.00',
                'card_type' => 'VISA-DBBL',
                'bank_tran_id' => 'BANK_TRX_REAL_8899',
                'card_no' => '411111XXXXXX1111',
            ], 200),
        ]);

        $request = Request::create('/payment/sslcommerz/success', 'POST', [
            'tran_id' => 'SSLC_TRX_999888',
            'val_id' => 'VAL_ID_20261029_001',
            'status' => 'VALID',
        ]);

        $adapter = app(SslcommerzPaymentAdapter::class);
        $verification = $adapter->verifyPayment($request, $order);

        $this->assertTrue($verification->isSuccessful);
        $this->assertEquals('BANK_TRX_REAL_8899', $verification->gatewayTrxId);
        $this->assertEquals(1570.00, $verification->amountPaid);
        $this->assertEquals('411111XXXXXX1111', $verification->customerAccount);

        $transaction->refresh();
        $this->assertEquals(PaymentTransaction::STATUS_SUCCESSFUL, $transaction->status);
        $this->assertEquals('BANK_TRX_REAL_8899', $transaction->transaction_id);
    }

    public function test_sslcommerz_adapter_verifies_failed_or_cancelled_status(): void
    {
        $this->createSslcommerzSetting(true);
        $order = $this->createOrder(1570.00);

        $transaction = PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway_code' => PaymentGatewaySetting::CODE_SSLCOMMERZ,
            'transaction_id' => 'SSLC_TRX_FAIL_1',
            'payment_purpose' => PaymentTransaction::PURPOSE_ORDER_FULL,
            'amount_bdt' => 1570.00,
            'status' => PaymentTransaction::STATUS_PENDING,
        ]);

        $request = Request::create('/payment/sslcommerz/fail', 'POST', [
            'tran_id' => 'SSLC_TRX_FAIL_1',
            'status' => 'FAILED',
        ]);

        $adapter = app(SslcommerzPaymentAdapter::class);
        $verification = $adapter->verifyPayment($request, $order);

        $this->assertFalse($verification->isSuccessful);
        $this->assertStringContainsString('declined or failed', $verification->errorMessage);

        $transaction->refresh();
        $this->assertEquals(PaymentTransaction::STATUS_FAILED, $transaction->status);
    }

    public function test_sslcommerz_callback_controller_success_confirms_full_order(): void
    {
        $this->createSslcommerzSetting(true);
        $order = $this->createOrder(1570.00);

        PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway_code' => PaymentGatewaySetting::CODE_SSLCOMMERZ,
            'transaction_id' => 'SSLC_FULL_CALLBACK',
            'payment_purpose' => PaymentTransaction::PURPOSE_ORDER_FULL,
            'amount_bdt' => 1570.00,
            'status' => PaymentTransaction::STATUS_PENDING,
        ]);

        Http::fake([
            'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php*' => Http::response([
                'status' => 'VALIDATED',
                'tran_id' => 'SSLC_FULL_CALLBACK',
                'val_id' => 'VAL_ID_FULL_99',
                'amount' => '1570.00',
                'bank_tran_id' => 'BANK_CONFIRMED_99',
                'card_no' => '424242XXXXXX4242',
            ], 200),
        ]);

        $response = $this->post('/payment/sslcommerz/success', [
            'tran_id' => 'SSLC_FULL_CALLBACK',
            'val_id' => 'VAL_ID_FULL_99',
            'status' => 'VALID',
        ]);

        $response->assertRedirect(route('storefront.order-confirmed', ['order' => $order->order_number]));
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->status);
        $this->assertEquals(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertEquals('1570.0000', (string) $order->advance_paid_bdt);
        $this->assertEquals('0.0000', (string) $order->payable_cod_amount_bdt);
    }

    public function test_sslcommerz_callback_controller_success_confirms_advance_delivery_charge(): void
    {
        $this->createSslcommerzSetting(true);
        $order = $this->createOrder(1570.00);

        PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway_code' => PaymentGatewaySetting::CODE_SSLCOMMERZ,
            'transaction_id' => 'SSLC_ADV_FEE_TRX',
            'payment_purpose' => PaymentTransaction::PURPOSE_ADVANCE_DELIVERY_CHARGE,
            'amount_bdt' => 150.00,
            'status' => PaymentTransaction::STATUS_PENDING,
        ]);

        Http::fake([
            'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php*' => Http::response([
                'status' => 'VALID',
                'tran_id' => 'SSLC_ADV_FEE_TRX',
                'val_id' => 'VAL_ID_ADV_150',
                'amount' => '150.00',
                'bank_tran_id' => 'BANK_ADV_TRX_150',
                'card_no' => '01912345678',
            ], 200),
        ]);

        $response = $this->post('/payment/sslcommerz/success', [
            'tran_id' => 'SSLC_ADV_FEE_TRX',
            'val_id' => 'VAL_ID_ADV_150',
            'status' => 'VALID',
        ]);

        $response->assertRedirect(route('storefront.order-confirmed', ['order' => $order->order_number]));

        $order->refresh();
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->status);
        $this->assertEquals(Order::PAYMENT_PARTIAL_ADVANCE, $order->payment_status);
        $this->assertEquals('150.0000', (string) $order->advance_paid_bdt);
        $this->assertEquals('1420.0000', (string) $order->payable_cod_amount_bdt);
    }

    public function test_sslcommerz_callback_controller_ipn_handles_async_notification(): void
    {
        $this->createSslcommerzSetting(true);
        $order = $this->createOrder(1570.00);

        PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway_code' => PaymentGatewaySetting::CODE_SSLCOMMERZ,
            'transaction_id' => 'SSLC_IPN_TRX_1',
            'payment_purpose' => PaymentTransaction::PURPOSE_ORDER_FULL,
            'amount_bdt' => 1570.00,
            'status' => PaymentTransaction::STATUS_PENDING,
        ]);

        Http::fake([
            'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php*' => Http::response([
                'status' => 'VALID',
                'tran_id' => 'SSLC_IPN_TRX_1',
                'val_id' => 'VAL_ID_IPN_1',
                'amount' => '1570.00',
                'bank_tran_id' => 'BANK_IPN_OK_1',
            ], 200),
        ]);

        $response = $this->postJson('/payment/sslcommerz/ipn', [
            'tran_id' => 'SSLC_IPN_TRX_1',
            'val_id' => 'VAL_ID_IPN_1',
            'status' => 'VALID',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'SUCCESS']);

        $order->refresh();
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->status);
        $this->assertEquals(Order::PAYMENT_PAID, $order->payment_status);
    }

    public function test_sslcommerz_test_connection_action_success(): void
    {
        $setting = $this->createSslcommerzSetting(true);
        $action = app(TestGatewayConnectionAction::class);

        Http::fake([
            'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php*' => Http::response([
                'status' => 'INVALID_TRANSACTION',
            ], 200),
        ]);

        $result = $action(PaymentGatewaySetting::CODE_SSLCOMMERZ);
        $this->assertTrue($result->success);
        $this->assertStringContainsString('verified', $result->message);

        $setting->refresh();
        $this->assertEquals('successful', $setting->last_test_status);
    }

    public function test_sslcommerz_test_connection_action_failure_on_bad_credentials(): void
    {
        $setting = $this->createSslcommerzSetting(true);
        $action = app(TestGatewayConnectionAction::class);

        Http::fake([
            'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php*' => Http::response([
                'status' => 'FAILED',
                'failedreason' => 'Store Credential Error',
            ], 200),
        ]);

        $result = $action(PaymentGatewaySetting::CODE_SSLCOMMERZ);
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid store credentials', $result->message);

        $setting->refresh();
        $this->assertEquals('failed', $setting->last_test_status);
    }
}

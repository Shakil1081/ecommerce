<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Actions\TestGatewayConnectionAction;
use App\Modules\Payment\Adapters\BkashPaymentAdapter;
use App\Modules\Payment\Models\PaymentGatewaySetting;
use App\Modules\Payment\Models\PaymentTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BkashPaymentAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function createBkashSetting(bool $sandbox = true): PaymentGatewaySetting
    {
        return PaymentGatewaySetting::create([
            'code' => PaymentGatewaySetting::CODE_BKASH,
            'name' => 'bKash Tokenized Checkout',
            'is_active' => true,
            'is_sandbox' => $sandbox,
            'credentials' => [
                'app_key' => 'test_app_key_123',
                'app_secret' => 'test_app_secret_456',
                'username' => 'test_user',
                'password' => 'test_pass',
            ],
            'surcharge_percentage' => 1.50,
            'sort_order' => 1,
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
            'order_number' => 'ORD-20261029-BK01',
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'bkash',
            'customer_name' => 'Kazi Shakil',
            'customer_phone' => '01712345678',
            'customer_email' => 'shakil@example.com',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Mirpur DOHS, Dhaka',
            'subtotal_bdt' => $total - 70.00,
            'shipping_fee_bdt' => 70.00,
            'total_bdt' => $total,
            'payable_cod_amount_bdt' => $total,
        ]);
    }

    public function test_bkash_adapter_can_obtain_grant_token(): void
    {
        Http::fake([
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'statusCode' => '0000',
                'statusMessage' => 'Successful',
                'id_token' => 'mock_bkash_jwt_token_xyz',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'refresh_token' => 'mock_refresh_token',
            ], 200),
        ]);

        $adapter = app(BkashPaymentAdapter::class);
        $credentials = [
            'app_key' => 'test_key',
            'app_secret' => 'test_secret',
            'username' => 'test_user',
            'password' => 'test_password',
        ];

        $token = $adapter->getGrantToken($credentials, true);
        $this->assertEquals('mock_bkash_jwt_token_xyz', $token);

        // Subsequent call should hit cache without calling HTTP endpoint again
        Http::fake([
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([], 500),
        ]);
        $cachedToken = $adapter->getGrantToken($credentials, true);
        $this->assertEquals('mock_bkash_jwt_token_xyz', $cachedToken);
    }

    public function test_bkash_adapter_initiates_payment_successfully(): void
    {
        $this->createBkashSetting(true);
        $order = $this->createOrder(1570.00);

        Http::fake([
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'id_token' => 'mock_token_123',
                'expires_in' => 3600,
            ], 200),
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/create' => Http::response([
                'statusCode' => '0000',
                'statusMessage' => 'Successful',
                'paymentID' => 'TR0011BKASH99',
                'bkashURL' => 'https://sandbox.payment.bka.sh/redirect/TR0011BKASH99',
                'amount' => '1570.00',
                'currency' => 'BDT',
            ], 200),
        ]);

        $adapter = app(BkashPaymentAdapter::class);
        $result = $adapter->initiatePayment($order, 1570.00, PaymentTransaction::PURPOSE_ORDER_FULL);

        $this->assertTrue($result->success);
        $this->assertEquals('https://sandbox.payment.bka.sh/redirect/TR0011BKASH99', $result->redirectUrl);
        $this->assertEquals('TR0011BKASH99', $result->gatewayPaymentId);

        $transaction = PaymentTransaction::where('transaction_id', 'TR0011BKASH99')->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(PaymentTransaction::STATUS_PENDING, $transaction->status);
        $this->assertEquals('1570.0000', (string) $transaction->amount_bdt);
        $this->assertEquals($order->id, $transaction->order_id);
    }

    public function test_bkash_adapter_initiation_fails_gracefully_on_error(): void
    {
        $this->createBkashSetting(true);
        $order = $this->createOrder(1570.00);

        Http::fake([
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'id_token' => 'mock_token_123',
                'expires_in' => 3600,
            ], 200),
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/create' => Http::response([
                'statusCode' => '2001',
                'statusMessage' => 'Invalid Amount',
            ], 200),
        ]);

        $adapter = app(BkashPaymentAdapter::class);
        $result = $adapter->initiatePayment($order, 1570.00);

        $this->assertFalse($result->success);
        $this->assertEquals('Invalid Amount', $result->errorMessage);
    }

    public function test_bkash_adapter_verifies_successful_callback_and_executes(): void
    {
        $this->createBkashSetting(true);
        $order = $this->createOrder(1570.00);

        $transaction = PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway_code' => PaymentGatewaySetting::CODE_BKASH,
            'transaction_id' => 'TR0011PAY999',
            'payment_purpose' => PaymentTransaction::PURPOSE_ORDER_FULL,
            'amount_bdt' => 1570.00,
            'status' => PaymentTransaction::STATUS_PENDING,
        ]);

        Http::fake([
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'id_token' => 'mock_token_123',
                'expires_in' => 3600,
            ], 200),
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/execute' => Http::response([
                'statusCode' => '0000',
                'statusMessage' => 'Successful',
                'paymentID' => 'TR0011PAY999',
                'trxID' => 'BK_TRX_REAL_8899',
                'amount' => '1570.00',
                'customerMsisdn' => '01712345678',
                'transactionStatus' => 'Completed',
            ], 200),
        ]);

        $request = Request::create('/payment/bkash/callback', 'GET', [
            'paymentID' => 'TR0011PAY999',
            'status' => 'success',
        ]);

        $adapter = app(BkashPaymentAdapter::class);
        $verification = $adapter->verifyPayment($request, $order);

        $this->assertTrue($verification->isSuccessful);
        $this->assertEquals('BK_TRX_REAL_8899', $verification->gatewayTrxId);
        $this->assertEquals(1570.00, $verification->amountPaid);
        $this->assertEquals('01712345678', $verification->customerAccount);

        $transaction->refresh();
        $this->assertEquals(PaymentTransaction::STATUS_SUCCESSFUL, $transaction->status);
        $this->assertEquals('BK_TRX_REAL_8899', $transaction->transaction_id);
    }

    public function test_bkash_adapter_handles_customer_cancellation(): void
    {
        $this->createBkashSetting(true);
        $order = $this->createOrder(1570.00);

        $transaction = PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway_code' => PaymentGatewaySetting::CODE_BKASH,
            'transaction_id' => 'TR0011CANCEL',
            'payment_purpose' => PaymentTransaction::PURPOSE_ORDER_FULL,
            'amount_bdt' => 1570.00,
            'status' => PaymentTransaction::STATUS_PENDING,
        ]);

        $request = Request::create('/payment/bkash/callback', 'GET', [
            'paymentID' => 'TR0011CANCEL',
            'status' => 'cancel',
        ]);

        $adapter = app(BkashPaymentAdapter::class);
        $verification = $adapter->verifyPayment($request, $order);

        $this->assertFalse($verification->isSuccessful);
        $this->assertStringContainsString('cancelled', $verification->errorMessage);

        $transaction->refresh();
        $this->assertEquals(PaymentTransaction::STATUS_CANCELLED, $transaction->status);
    }

    public function test_bkash_callback_controller_confirms_full_order_payment(): void
    {
        $this->createBkashSetting(true);
        $order = $this->createOrder(1570.00);

        PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway_code' => PaymentGatewaySetting::CODE_BKASH,
            'transaction_id' => 'TR_WEB_PAY',
            'payment_purpose' => PaymentTransaction::PURPOSE_ORDER_FULL,
            'amount_bdt' => 1570.00,
            'status' => PaymentTransaction::STATUS_PENDING,
        ]);

        Http::fake([
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'id_token' => 'mock_token_123',
                'expires_in' => 3600,
            ], 200),
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/execute' => Http::response([
                'statusCode' => '0000',
                'statusMessage' => 'Successful',
                'paymentID' => 'TR_WEB_PAY',
                'trxID' => 'BK_FULL_ORDER_99',
                'amount' => '1570.00',
                'customerMsisdn' => '01712345678',
                'transactionStatus' => 'Completed',
            ], 200),
        ]);

        $response = $this->get('/payment/bkash/callback?paymentID=TR_WEB_PAY&status=success');

        $response->assertRedirect(route('storefront.order-confirmed', ['order' => $order->order_number]));
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->status);
        $this->assertEquals(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertEquals('1570.0000', (string) $order->advance_paid_bdt);
        $this->assertEquals('0.0000', (string) $order->payable_cod_amount_bdt);
    }

    public function test_bkash_callback_controller_confirms_advance_delivery_charge(): void
    {
        $this->createBkashSetting(true);
        $order = $this->createOrder(1570.00);

        PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway_code' => PaymentGatewaySetting::CODE_BKASH,
            'transaction_id' => 'TR_ADV_FEE',
            'payment_purpose' => PaymentTransaction::PURPOSE_ADVANCE_DELIVERY_CHARGE,
            'amount_bdt' => 150.00,
            'status' => PaymentTransaction::STATUS_PENDING,
        ]);

        Http::fake([
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'id_token' => 'mock_token_123',
                'expires_in' => 3600,
            ], 200),
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/execute' => Http::response([
                'statusCode' => '0000',
                'statusMessage' => 'Successful',
                'paymentID' => 'TR_ADV_FEE',
                'trxID' => 'BK_ADV_TRX_150',
                'amount' => '150.00',
                'customerMsisdn' => '01712345678',
                'transactionStatus' => 'Completed',
            ], 200),
        ]);

        $response = $this->get('/payment/bkash/callback?paymentID=TR_ADV_FEE&status=success');

        $response->assertRedirect(route('storefront.order-confirmed', ['order' => $order->order_number]));

        $order->refresh();
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->status);
        $this->assertEquals(Order::PAYMENT_PARTIAL_ADVANCE, $order->payment_status);
        $this->assertEquals('150.0000', (string) $order->advance_paid_bdt);
        $this->assertEquals('1420.0000', (string) $order->payable_cod_amount_bdt);
    }

    public function test_bkash_test_connection_action_success(): void
    {
        $setting = $this->createBkashSetting(true);
        $action = app(TestGatewayConnectionAction::class);

        Http::fake([
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'id_token' => 'valid_handshake_token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $result = $action(PaymentGatewaySetting::CODE_BKASH);
        $this->assertTrue($result->success);
        $this->assertStringContainsString('handshake successful', $result->message);

        $setting->refresh();
        $this->assertEquals('successful', $setting->last_test_status);
    }

    public function test_bkash_test_connection_action_failure(): void
    {
        $setting = $this->createBkashSetting(true);
        $action = app(TestGatewayConnectionAction::class);

        Http::fake([
            'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'statusCode' => '2004',
                'statusMessage' => 'Unauthorized Access - Invalid credentials',
            ], 401),
        ]);

        $failedResult = $action(PaymentGatewaySetting::CODE_BKASH);
        $this->assertFalse($failedResult->success);
        $this->assertStringContainsString('failed', $failedResult->message);

        $setting->refresh();
        $this->assertEquals('failed', $setting->last_test_status);
    }
}

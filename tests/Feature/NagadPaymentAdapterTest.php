<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Actions\TestGatewayConnectionAction;
use App\Modules\Payment\Adapters\NagadPaymentAdapter;
use App\Modules\Payment\Models\PaymentGatewaySetting;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\Payment\Services\NagadCryptographicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NagadPaymentAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected string $merchantPrivateKey;

    protected string $merchantPublicKey;

    protected string $nagadPrivateKey;

    protected string $nagadPublicKey;

    protected function setUp(): void
    {
        parent::setUp();

        // Valid 1024-bit RSA keypairs for merchant and simulated Nagad PG
        $this->merchantPrivateKey = (string) base64_decode('LS0tLS1CRUdJTiBQUklWQVRFIEtFWS0tLS0tCk1JSUNkZ0lCQURBTkJna3Foa2lHOXcwQkFRRUZBQVNDQW1Bd2dnSmNBZ0VBQW9HQkFMWWVPdTJHNTJac3JOM2oKZzlSSElZU3JmZWtrNk4wa21iVU1mYmNNdDdjektpZ3lFT3U0VHNaN0VKVHBESHRETjFVUU9HVW1HOWxxRjl3dgpmTGRONXpUR1hxSW05TmhxRmVjWDVFaW54bFpWOHBxYUJmMUt0Uyt6VGVRVno5cE1aMmMzdlpoaGRhelJaMHZuCjhOVHV6WmhiUDE3OWdXUzBlOFpIR1ZyQVVMZjdBZ01CQUFFQ2dZQmZFVXJMb1lkOEJURDhKMzh5dWVyNldaZEIKdEYzR3l3dGQ0bVpodnNiTVlHNGFTNEJmdkhlS3pzVXFKcm00UXE5SXZVM2pKMUNVWHZpVG5VcHFXY1ZkK21obAo4K1JqRm1SRFhxQmV3eERBMytINGNIdy9uNUFyaE1Lc0ZqbDhHWDdTbm9XVlV6ak5TWGFQOFkzZGJ1VW8yM0puCllrWUJBd0RMZDEvZVNLaHVpUUpCQU9ZY2ZaaXpyYVJtV0YveUIzaGgvOXZqOUdXeS9iVWJqR2JUMTFOOXQ3ZjgKSUhkM09xdy9uK0NHNUNRQklNbVhSaExMeWgxakpFUC8xTnRwS2F2TEN5OENRUURLbTNqS0VpYldlNEFQYXE3RgpIMVNNaE5VNVZ4Tm1sQ1VQdmxjeFpydTREYlZhdFBzQ1J2N2t0ZHlROU02akNwdVU2UnBZSExzRWxxOEY0VUN0Cm9EejFBa0VBbEFZVHpSMFlRRDhON25wVXo5M0twdnJXaG0xRHd6TDdlRklobTdXdGJ3OGpHM1o1S2NDcjZkVWMKQVFVd3dEMHRlSDNiZ0ZmOFJqbldCV1o2eGVZUlJRSkFUdlFsTHdIQjFzVkwwellzb3k2aFlSMEdocERUUTU4cApqaWdMakhuUFgxc2dPQmZjMVRkYmJ6OGJrL1dDYlBEZVRqcEVoTisxVllWdThTaWxic1BKM1FKQU9uaWd0WjFUCnZrS1ZPTzRhbStNY2ZtNml1YThodmgrNzB3UVg5M3p1bXlqK2hpcy9Bb0Qxc2dVUTZ5bXVxVndJZEs4WGRlYkcKM3JlRUM4U2JhR2Zmd2c9PQotLS0tLUVORCBQUklWQVRFIEtFWS0tLS0tCg==');
        $this->merchantPublicKey = (string) base64_decode('LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0KTUlHZk1BMEdDU3FHU0liM0RRRUJBUVVBQTRHTkFEQ0JpUUtCZ1FDMkhqcnRodWRtYkt6ZDQ0UFVSeUdFcTMzcApKT2pkSkptMURIMjNETGUzTXlvb01oRHJ1RTdHZXhDVTZReDdRemRWRURobEpodlphaGZjTDN5M1RlYzB4bDZpCkp2VFlhaFhuRitSSXA4WldWZkthbWdYOVNyVXZzMDNrRmMvYVRHZG5ONzJZWVhXczBXZEw1L0RVN3MyWVd6OWUKL1lGa3RIdkdSeGxhd0ZDMyt3SURBUUFCCi0tLS0tRU5EIFBVQkxJQyBLRVktLS0tLQo=');

        $this->nagadPrivateKey = (string) base64_decode('LS0tLS1CRUdJTiBQUklWQVRFIEtFWS0tLS0tCk1JSUNkZ0lCQURBTkJna3Foa2lHOXcwQkFRRUZBQVNDQW1Bd2dnSmNBZ0VBQW9HQkFOMDZKMDVvVk9QZHlHN2EKQjdPRHd4bnNwS0RrOWVxYnNvNSsxRWdCUHFVSlcrbUJjY3lTc0FlZzVZWnFUaVVHajlVRDZaOFc5L3BGQmRIYwo1c2VrL0s1MlVBTmw4Q1ZhWjB6WkpoTUowNyszRk5DQ1U5QmwxWXlDREZsZVd0VFJKN2lBQzdrYU9RL3BlWWFnClMxZUFIbHRyVDRsTTg1VkN3ZDlubVRteUg5ZzNBZ01CQUFFQ2dZRUFyYTJwRENKV2V1bzBIK3JsM3VZVHRuZVYKSzRkMUZQR1ZOYytXWXMwdlhDdkR6OGREQ3lNaU5XcjRweFV3TWxzN2NqVjNKT0hJWHQvdldMbi9LRDk3NnhuYwprd1o1Y0lXUEh2UExYZ1VlVHlFN2htTmw0QXlaUUtZbit5TGJxUnZEZEZueHZmempxNDYxMGZ3cjlPcGhZUVI4CmJWREpCSHltN2VjazYvd1lNSGtDUVFEeGhkb1ZrbnNKWlpCY0JSUXorYVd0Umlmb2hjMmtoMnVXOVZYVHNXdDAKQ1hWazdpVHhlN3RDNy9KMHhaWi9RZDd3TVNKRlE0eDhPVFRhV2UxVHcvMHJBa0VBNm56ZHFoK2YzTmtYelRNagpMMHEyb2djYWtVWjFRdW5OM2xCN0MrM1hYZ3AycjZIS2VzeUxPZURTbHN2L3hpSDVQclp4YnRjY2dXVk5RNEoyCm9aNURKUUpBVDNvYWY4cktEaGVMMzV3VVA5NUE5TG9IVys3Yjh1NVZLMEhrVWxzcDN1OVY0RGExbFhsLzc5RUIKMmdkRE5QRTZLWlFoa09WZmoybEx5U2FhZ0Z3WUxRSkFhdHpLL0Z2N005K3Y3REZRNkoySkMrQm02Q3dQQnBhYwozcU1ZNEgvYzladVpLVU4zam0wblNoSStoc0NqYVAxVXBKTHFzUlBSaHRyTzBtUmpnaFlwRFFKQUFsbVQzQXl3CjA2TTlLVnJ3d3pkOU9lYVFXRWNocXlUYmRyQVdGTzJyMXpiSzZwSWR0UkVUREpmK00vT0J6RVdqVCtYMEg3SDMKMVVYd0p4TGxudVNZQUE9PQotLS0tLUVORCBQUklWQVRFIEtFWS0tLS0tCg==');
        $this->nagadPublicKey = (string) base64_decode('LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0KTUlHZk1BMEdDU3FHU0liM0RRRUJBUVVBQTRHTkFEQ0JpUUtCZ1FEZE9pZE9hRlRqM2NodTJnZXpnOE1aN0tTZwo1UFhxbTdLT2Z0UklBVDZsQ1Z2cGdYSE1rckFIb09XR2FrNGxCby9WQSttZkZ2ZjZSUVhSM09iSHBQeXVkbEFEClpmQWxXbWRNMlNZVENkTy90eFRRZ2xQUVpkV01nZ3haWGxyVTBTZTRnQXU1R2prUDZYbUdvRXRYZ0I1YmEwK0oKVFBPVlFzSGZaNWs1c2gvWU53SURBUUFCCi0tLS0tRU5EIFBVQkxJQyBLRVktLS0tLQo=');
    }

    private function createNagadSetting(bool $sandbox = true): PaymentGatewaySetting
    {
        return PaymentGatewaySetting::create([
            'code' => PaymentGatewaySetting::CODE_NAGAD,
            'name' => 'Nagad Digital Payment',
            'is_active' => true,
            'is_sandbox' => $sandbox,
            'credentials' => [
                'merchant_id' => '683002007104225',
                'merchant_private_key' => $this->merchantPrivateKey,
                'pg_public_key' => $this->nagadPublicKey,
            ],
            'surcharge_percentage' => 1.20,
            'sort_order' => 2,
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
            'order_number' => 'ORD-20261029-NG01',
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'nagad',
            'customer_name' => 'Kazi Shakil',
            'customer_phone' => '01812345678',
            'customer_email' => 'shakil@example.com',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Banani, Dhaka',
            'subtotal_bdt' => $total - 70.00,
            'shipping_fee_bdt' => 70.00,
            'total_bdt' => $total,
            'payable_cod_amount_bdt' => $total,
        ]);
    }

    public function test_nagad_crypto_service_encrypt_decrypt_and_signature(): void
    {
        $crypto = new NagadCryptographicService;
        $samplePlaintext = 'test_payload_order_12345';

        // Merchant signs, Nagad verifies
        $signature = $crypto->generateSignature($samplePlaintext, $this->merchantPrivateKey);
        $isValid = $crypto->verifySignature($samplePlaintext, $signature, $this->merchantPublicKey);
        $this->assertTrue($isValid);

        // Merchant encrypts with Nagad public key, Nagad decrypts with Nagad private key
        $cipher = $crypto->encryptWithPublicKey($samplePlaintext, $this->nagadPublicKey);
        $decrypted = $crypto->decryptWithPrivateKey($cipher, $this->nagadPrivateKey);
        $this->assertEquals($samplePlaintext, $decrypted);
    }

    public function test_nagad_adapter_initiates_payment_two_step_handshake(): void
    {
        $this->createNagadSetting(true);
        $order = $this->createOrder(1570.00);

        $crypto = new NagadCryptographicService;

        // Prepare simulated Nagad response for Initialize Step (encrypted with merchant public key)
        $simulatedInitPlain = json_encode([
            'paymentReferenceId' => 'NAGAD_REF_998877',
            'challenge' => 'simulated_nagad_challenge_abc',
        ], JSON_THROW_ON_ERROR);

        $simulatedEncryptedInit = $crypto->encryptWithPublicKey($simulatedInitPlain, $this->merchantPublicKey);

        Http::fake([
            'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs/check-out/initialize/*' => Http::response([
                'sensitiveData' => $simulatedEncryptedInit,
            ], 200),
            'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs/check-out/complete/*' => Http::response([
                'status' => 'Success',
                'statusCode' => '000_0000_000',
                'callBackUrl' => 'https://sandbox.mynagad.com/pay/NAGAD_REF_998877',
                'message' => 'Successful',
            ], 200),
        ]);

        $adapter = app(NagadPaymentAdapter::class);
        $result = $adapter->initiatePayment($order, 1570.00);

        $this->assertTrue($result->success);
        $this->assertEquals('https://sandbox.mynagad.com/pay/NAGAD_REF_998877', $result->redirectUrl);
        $this->assertEquals('NAGAD_REF_998877', $result->gatewayPaymentId);

        $transaction = PaymentTransaction::where('transaction_id', 'NAGAD_REF_998877')->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(PaymentTransaction::STATUS_PENDING, $transaction->status);
        $this->assertEquals(PaymentGatewaySetting::CODE_NAGAD, $transaction->gateway_code);
    }

    public function test_nagad_adapter_verifies_successful_callback(): void
    {
        $this->createNagadSetting(true);
        $order = $this->createOrder(1570.00);

        $transaction = PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway_code' => PaymentGatewaySetting::CODE_NAGAD,
            'transaction_id' => 'NAGAD_REF_VERIFY_11',
            'payment_purpose' => PaymentTransaction::PURPOSE_ORDER_FULL,
            'amount_bdt' => 1570.00,
            'status' => PaymentTransaction::STATUS_PENDING,
        ]);

        Http::fake([
            'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs/verify/payment/*' => Http::response([
                'status' => 'Success',
                'statusCode' => '000_0000_000',
                'merchantId' => '683002007104225',
                'orderId' => $order->order_number,
                'paymentRefId' => 'NAGAD_REF_VERIFY_11',
                'amount' => '1570.00',
                'clientMobileNo' => '01812345678',
                'issuerPaymentRefNo' => 'NGD_REAL_TRX_8899',
            ], 200),
        ]);

        $request = Request::create('/payment/nagad/callback', 'GET', [
            'payment_ref_id' => 'NAGAD_REF_VERIFY_11',
            'status' => 'Success',
        ]);

        $adapter = app(NagadPaymentAdapter::class);
        $verification = $adapter->verifyPayment($request, $order);

        $this->assertTrue($verification->isSuccessful);
        $this->assertEquals('NGD_REAL_TRX_8899', $verification->gatewayTrxId);
        $this->assertEquals(1570.00, $verification->amountPaid);
        $this->assertEquals('01812345678', $verification->customerAccount);

        $transaction->refresh();
        $this->assertEquals(PaymentTransaction::STATUS_SUCCESSFUL, $transaction->status);
        $this->assertEquals('NGD_REAL_TRX_8899', $transaction->transaction_id);
    }

    public function test_nagad_adapter_handles_aborted_payment(): void
    {
        $this->createNagadSetting(true);
        $order = $this->createOrder(1570.00);

        $transaction = PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway_code' => PaymentGatewaySetting::CODE_NAGAD,
            'transaction_id' => 'NAGAD_REF_ABORT',
            'payment_purpose' => PaymentTransaction::PURPOSE_ORDER_FULL,
            'amount_bdt' => 1570.00,
            'status' => PaymentTransaction::STATUS_PENDING,
        ]);

        $request = Request::create('/payment/nagad/callback', 'GET', [
            'payment_ref_id' => 'NAGAD_REF_ABORT',
            'status' => 'Aborted',
        ]);

        $adapter = app(NagadPaymentAdapter::class);
        $verification = $adapter->verifyPayment($request, $order);

        $this->assertFalse($verification->isSuccessful);
        $this->assertStringContainsString('cancelled', $verification->errorMessage);

        $transaction->refresh();
        $this->assertEquals(PaymentTransaction::STATUS_CANCELLED, $transaction->status);
    }

    public function test_nagad_callback_controller_confirms_full_order(): void
    {
        $this->createNagadSetting(true);
        $order = $this->createOrder(1570.00);

        PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway_code' => PaymentGatewaySetting::CODE_NAGAD,
            'transaction_id' => 'NGD_CALLBACK_FULL',
            'payment_purpose' => PaymentTransaction::PURPOSE_ORDER_FULL,
            'amount_bdt' => 1570.00,
            'status' => PaymentTransaction::STATUS_PENDING,
        ]);

        Http::fake([
            'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs/verify/payment/*' => Http::response([
                'status' => 'Success',
                'statusCode' => '000_0000_000',
                'paymentRefId' => 'NGD_CALLBACK_FULL',
                'amount' => '1570.00',
                'clientMobileNo' => '01812345678',
                'issuerPaymentRefNo' => 'NGD_CONFIRMED_TRX_1',
            ], 200),
        ]);

        $response = $this->get('/payment/nagad/callback?payment_ref_id=NGD_CALLBACK_FULL&status=Success');

        $response->assertRedirect(route('storefront.order-confirmed', ['order' => $order->order_number]));
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->status);
        $this->assertEquals(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertEquals('1570.0000', (string) $order->advance_paid_bdt);
        $this->assertEquals('0.0000', (string) $order->payable_cod_amount_bdt);
    }

    public function test_nagad_callback_controller_confirms_advance_delivery_charge(): void
    {
        $this->createNagadSetting(true);
        $order = $this->createOrder(1570.00);

        PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway_code' => PaymentGatewaySetting::CODE_NAGAD,
            'transaction_id' => 'NGD_ADVANCE_FEE_TRX',
            'payment_purpose' => PaymentTransaction::PURPOSE_ADVANCE_DELIVERY_CHARGE,
            'amount_bdt' => 150.00,
            'status' => PaymentTransaction::STATUS_PENDING,
        ]);

        Http::fake([
            'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs/verify/payment/*' => Http::response([
                'status' => 'Success',
                'statusCode' => '000_0000_000',
                'paymentRefId' => 'NGD_ADVANCE_FEE_TRX',
                'amount' => '150.00',
                'clientMobileNo' => '01812345678',
                'issuerPaymentRefNo' => 'NGD_ADV_ISSUER_150',
            ], 200),
        ]);

        $response = $this->get('/payment/nagad/callback?payment_ref_id=NGD_ADVANCE_FEE_TRX&status=Success');

        $response->assertRedirect(route('storefront.order-confirmed', ['order' => $order->order_number]));

        $order->refresh();
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->status);
        $this->assertEquals(Order::PAYMENT_PARTIAL_ADVANCE, $order->payment_status);
        $this->assertEquals('150.0000', (string) $order->advance_paid_bdt);
        $this->assertEquals('1420.0000', (string) $order->payable_cod_amount_bdt);
    }

    public function test_nagad_test_connection_action_success(): void
    {
        $setting = $this->createNagadSetting(true);
        $action = app(TestGatewayConnectionAction::class);

        $result = $action(PaymentGatewaySetting::CODE_NAGAD);
        $this->assertTrue($result->success);
        $this->assertStringContainsString('handshake', $result->message);

        $setting->refresh();
        $this->assertEquals('successful', $setting->last_test_status);
    }

    public function test_nagad_test_connection_action_failure_on_corrupt_key(): void
    {
        $setting = PaymentGatewaySetting::create([
            'code' => PaymentGatewaySetting::CODE_NAGAD,
            'name' => 'Nagad Broken',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => [
                'merchant_id' => '12345678',
                'merchant_private_key' => 'corrupt_key_invalid',
                'pg_public_key' => 'corrupt_key_invalid',
            ],
        ]);

        $action = app(TestGatewayConnectionAction::class);
        $result = $action(PaymentGatewaySetting::CODE_NAGAD);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('failed', $result->message);

        $setting->refresh();
        $this->assertEquals('failed', $setting->last_test_status);
    }
}

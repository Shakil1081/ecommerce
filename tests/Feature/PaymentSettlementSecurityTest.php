<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderStatusHistory;
use App\Modules\Payment\Models\PaymentGatewaySetting;
use App\Modules\Payment\Models\PaymentTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression tests for gateway callback settlement integrity (amount, ownership, idempotency).
 */
final class PaymentSettlementSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const VALIDATOR_URL = 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php*';

    protected function setUp(): void
    {
        parent::setUp();

        PaymentGatewaySetting::create([
            'code' => PaymentGatewaySetting::CODE_SSLCOMMERZ,
            'name' => 'SSLCommerz',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => ['store_id' => 'testbox', 'store_passwd' => 'qwerty'],
            'sort_order' => 1,
        ]);
    }

    private function createOrder(): Order
    {
        $division = BangladeshDivision::create(['name_en' => 'Dhaka', 'name_bn' => 'ঢাকা']);
        $district = BangladeshDistrict::create([
            'division_id' => $division->id,
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'zone_type' => 'inside_dhaka',
        ]);

        return Order::create([
            'order_number' => 'ORD-20261120-SECURE',
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'sslcommerz',
            'customer_name' => 'Arif Hossain',
            'customer_phone' => '01912345678',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Gulshan 2, Dhaka',
            'subtotal_bdt' => '50000.0000',
            'discount_amount_bdt' => '0.0000',
            'shipping_fee_bdt' => '0.0000',
            'total_bdt' => '50000.0000',
            'advance_paid_bdt' => '0.0000',
            'payable_cod_amount_bdt' => '50000.0000',
        ]);
    }

    private function createTransaction(Order $order, string $tranId, string $purpose, string $amount): PaymentTransaction
    {
        return PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway_code' => PaymentGatewaySetting::CODE_SSLCOMMERZ,
            'transaction_id' => $tranId,
            'payment_purpose' => $purpose,
            'amount_bdt' => $amount,
            'status' => PaymentTransaction::STATUS_PENDING,
        ]);
    }

    public function test_underpaid_gateway_amount_is_rejected_and_order_stays_unpaid(): void
    {
        $order = $this->createOrder();
        $transaction = $this->createTransaction($order, 'SSLC_EXPENSIVE', PaymentTransaction::PURPOSE_ORDER_FULL, '50000.0000');

        Http::fake([self::VALIDATOR_URL => Http::response([
            'status' => 'VALID',
            'tran_id' => 'SSLC_EXPENSIVE',
            'amount' => '10.00',
            'bank_tran_id' => 'BANK_TINY_PAYMENT',
        ], 200)]);

        $response = $this->post('/payment/sslcommerz/success', [
            'tran_id' => 'SSLC_EXPENSIVE',
            'val_id' => 'VAL_TINY',
            'status' => 'VALID',
        ]);

        $response->assertRedirect(route('storefront.checkout'));

        $order->refresh();
        $this->assertEquals(Order::PAYMENT_PENDING, $order->payment_status);
        $this->assertEquals(Order::STATUS_PENDING, $order->status);
        $this->assertEquals(PaymentTransaction::STATUS_FAILED, $transaction->fresh()->status);
    }

    public function test_val_id_belonging_to_another_transaction_is_rejected(): void
    {
        $order = $this->createOrder();
        $this->createTransaction($order, 'SSLC_TARGET', PaymentTransaction::PURPOSE_ORDER_FULL, '50000.0000');

        // Attacker replays a genuinely VALID val_id that was issued for a different transaction
        Http::fake([self::VALIDATOR_URL => Http::response([
            'status' => 'VALID',
            'tran_id' => 'SSLC_SOMEONE_ELSE',
            'amount' => '50000.00',
            'bank_tran_id' => 'BANK_OTHER',
        ], 200)]);

        $this->post('/payment/sslcommerz/success', [
            'tran_id' => 'SSLC_TARGET',
            'val_id' => 'VAL_FROM_OTHER_ORDER',
            'status' => 'VALID',
        ])->assertRedirect(route('storefront.checkout'));

        $this->assertEquals(Order::PAYMENT_PENDING, $order->fresh()->payment_status);
    }

    public function test_replayed_callback_does_not_double_count_advance_delivery_charge(): void
    {
        $order = $this->createOrder();
        $this->createTransaction($order, 'SSLC_ADV_150', PaymentTransaction::PURPOSE_ADVANCE_DELIVERY_CHARGE, '150.0000');

        Http::fake([self::VALIDATOR_URL => Http::response([
            'status' => 'VALID',
            'tran_id' => 'SSLC_ADV_150',
            'amount' => '150.00',
            'bank_tran_id' => 'SSLC_ADV_150', // keeps transaction_id stable so the replay finds the row
        ], 200)]);

        $payload = ['tran_id' => 'SSLC_ADV_150', 'val_id' => 'VAL_ADV', 'status' => 'VALID'];

        // Browser redirect + IPN (or a malicious replay) for the same payment
        $this->post('/payment/sslcommerz/success', $payload);
        $this->post('/payment/sslcommerz/ipn', $payload)->assertOk();
        $this->post('/payment/sslcommerz/success', $payload);

        $order->refresh();
        $this->assertEquals('150.0000', (string) $order->advance_paid_bdt);
        $this->assertEquals('49850.0000', (string) $order->payable_cod_amount_bdt);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->where('to_status', Order::STATUS_CONFIRMED)->count());
    }

    public function test_fail_redirect_cannot_downgrade_a_settled_transaction(): void
    {
        $order = $this->createOrder();
        $transaction = $this->createTransaction($order, 'SSLC_DONE', PaymentTransaction::PURPOSE_ORDER_FULL, '50000.0000');
        $transaction->update(['status' => PaymentTransaction::STATUS_SUCCESSFUL]);

        $this->post('/payment/sslcommerz/fail', ['tran_id' => 'SSLC_DONE', 'status' => 'FAILED']);

        $this->assertEquals(PaymentTransaction::STATUS_SUCCESSFUL, $transaction->fresh()->status);
    }
}

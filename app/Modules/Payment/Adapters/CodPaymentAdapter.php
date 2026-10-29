<?php

declare(strict_types=1);

namespace App\Modules\Payment\Adapters;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Contracts\PaymentGatewayContract;
use App\Modules\Payment\DTOs\GatewayConnectionTestResultDto;
use App\Modules\Payment\DTOs\PaymentInitiationResultDto;
use App\Modules\Payment\DTOs\PaymentVerificationResultDto;
use App\Modules\Payment\Models\PaymentGatewaySetting;
use App\Modules\Payment\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CodPaymentAdapter implements PaymentGatewayContract
{
    public function getGatewayCode(): string
    {
        return PaymentGatewaySetting::CODE_COD;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function initiatePayment(
        Order $order,
        float $amount,
        string $purpose = 'order_full',
        array $meta = []
    ): PaymentInitiationResultDto {
        $trxId = 'COD-'.Str::upper(Str::random(10));

        PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway_code' => $this->getGatewayCode(),
            'transaction_id' => $trxId,
            'payment_purpose' => $purpose,
            'amount_bdt' => $amount,
            'gateway_fee_bdt' => 0.0000,
            'currency' => 'BDT',
            'status' => PaymentTransaction::STATUS_PENDING,
            'customer_account' => $order->customer_phone,
            'raw_request_payload' => $meta,
        ]);

        return PaymentInitiationResultDto::successful(
            redirectUrl: route('storefront.order-confirmed', ['order' => $order->order_number]),
            transactionId: $trxId,
            gatewayPaymentId: $trxId,
            metadata: ['method' => 'cod'],
        );
    }

    public function verifyPayment(Request $request, ?Order $order = null): PaymentVerificationResultDto
    {
        $trxId = $request->input('transaction_id', 'COD-VERIFY');

        return PaymentVerificationResultDto::successful(
            transactionId: (string) $trxId,
            gatewayTrxId: (string) $trxId,
            amountPaid: 0.00,
            customerAccount: $order?->customer_phone,
            rawPayload: $request->all(),
        );
    }

    public function testConnection(?array $credentials = null, bool $sandbox = true): GatewayConnectionTestResultDto
    {
        return GatewayConnectionTestResultDto::successful(
            gatewayCode: $this->getGatewayCode(),
            message: 'Cash on Delivery gateway is enabled and operational',
            latencyMs: 1.0,
        );
    }
}

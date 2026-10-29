<?php

declare(strict_types=1);

namespace App\Modules\Payment\Contracts;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\DTOs\GatewayConnectionTestResultDto;
use App\Modules\Payment\DTOs\PaymentInitiationResultDto;
use App\Modules\Payment\DTOs\PaymentVerificationResultDto;
use Illuminate\Http\Request;

interface PaymentGatewayContract
{
    /**
     * Unique identifier code for the payment gateway.
     */
    public function getGatewayCode(): string;

    /**
     * Initiate a payment flow with the gateway provider.
     *
     * @param  array<string, mixed>  $meta
     */
    public function initiatePayment(
        Order $order,
        float $amount,
        string $purpose = 'order_full',
        array $meta = []
    ): PaymentInitiationResultDto;

    /**
     * Verify payment status from return URL or IPN webhook callback.
     */
    public function verifyPayment(Request $request, ?Order $order = null): PaymentVerificationResultDto;

    /**
     * Perform pre-flight API handshake test with the gateway provider.
     *
     * @param  array<string, mixed>|null  $credentials
     */
    public function testConnection(?array $credentials = null, bool $sandbox = true): GatewayConnectionTestResultDto;
}

<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Contracts\PaymentGatewayContract;
use App\Modules\Payment\DTOs\GatewayConnectionTestResultDto;
use App\Modules\Payment\DTOs\PaymentInitiationResultDto;
use App\Modules\Payment\DTOs\PaymentVerificationResultDto;
use Illuminate\Http\Request;

class FailingPaymentAdapter implements PaymentGatewayContract
{
    public function getGatewayCode(): string
    {
        return 'custom_failing_gw';
    }

    public function initiatePayment(
        Order $order,
        float $amount,
        string $purpose = 'order_full',
        array $meta = []
    ): PaymentInitiationResultDto {
        return PaymentInitiationResultDto::failed('Not implemented');
    }

    public function verifyPayment(Request $request, ?Order $order = null): PaymentVerificationResultDto
    {
        return PaymentVerificationResultDto::failed('Not implemented');
    }

    public function testConnection(?array $credentials = null, bool $sandbox = true): GatewayConnectionTestResultDto
    {
        return GatewayConnectionTestResultDto::failed(
            gatewayCode: 'custom_failing_gw',
            message: 'Authentication failed: Invalid Secret Key',
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Payment\Actions;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\DTOs\PaymentInitiationResultDto;
use App\Modules\Payment\Models\PaymentGatewaySetting;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\Payment\Services\PaymentGatewayManager;
use InvalidArgumentException;

class ProcessAdvanceDeliveryPaymentAction
{
    public function __construct(
        protected PaymentGatewayManager $manager,
    ) {}

    /**
     * Initiate an advance partial delivery fee online payment for a pending order.
     */
    public function __invoke(
        Order $order,
        string $gatewayCode,
        ?float $advanceAmount = null
    ): PaymentInitiationResultDto {
        if ($order->status !== Order::STATUS_PENDING) {
            throw new InvalidArgumentException("Cannot pay advance delivery fee on an order in [{$order->status}] state.");
        }

        // Determine amount: passed explicit amount, or order's shipping fee, or minimum ৳70.00
        $amountToCharge = $advanceAmount ?? (float) $order->shipping_fee_bdt;
        if ($amountToCharge <= 0.0) {
            $amountToCharge = 70.00; // Standard inside Dhaka delivery charge
        }

        // Must be an online gateway
        if (in_array($gatewayCode, [PaymentGatewaySetting::CODE_COD, PaymentGatewaySetting::CODE_BANK_TRANSFER], true)) {
            throw new InvalidArgumentException('Advance delivery fee must be prepaid using an online gateway (bKash, Nagad, or SSLCommerz).');
        }

        $adapter = $this->manager->resolve($gatewayCode);

        return $adapter->initiatePayment(
            order: $order,
            amount: $amountToCharge,
            purpose: PaymentTransaction::PURPOSE_ADVANCE_DELIVERY_CHARGE,
            meta: [
                'type' => 'advance_partial_delivery_fee',
                'original_total' => $order->total_bdt,
                'shipping_fee' => $order->shipping_fee_bdt,
            ]
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Payment\DTOs;

final readonly class PaymentVerificationResultDto
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public bool $isSuccessful,
        public ?string $transactionId = null,
        public ?string $gatewayTrxId = null,
        public ?float $amountPaid = null,
        public ?string $paymentStatus = null,
        public ?string $customerAccount = null,
        public ?string $errorMessage = null,
        public array $rawPayload = [],
    ) {}

    public static function successful(
        string $transactionId,
        string $gatewayTrxId,
        float $amountPaid,
        ?string $customerAccount = null,
        array $rawPayload = [],
    ): self {
        return new self(
            isSuccessful: true,
            transactionId: $transactionId,
            gatewayTrxId: $gatewayTrxId,
            amountPaid: $amountPaid,
            paymentStatus: 'successful',
            customerAccount: $customerAccount,
            errorMessage: null,
            rawPayload: $rawPayload,
        );
    }

    public static function failed(
        string $errorMessage,
        ?string $transactionId = null,
        ?string $gatewayTrxId = null,
        array $rawPayload = [],
    ): self {
        return new self(
            isSuccessful: false,
            transactionId: $transactionId,
            gatewayTrxId: $gatewayTrxId,
            amountPaid: null,
            paymentStatus: 'failed',
            customerAccount: null,
            errorMessage: $errorMessage,
            rawPayload: $rawPayload,
        );
    }
}

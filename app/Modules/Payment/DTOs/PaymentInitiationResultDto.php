<?php

declare(strict_types=1);

namespace App\Modules\Payment\DTOs;

final readonly class PaymentInitiationResultDto
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $success,
        public ?string $redirectUrl = null,
        public ?string $transactionId = null,
        public ?string $gatewayPaymentId = null,
        public ?string $errorMessage = null,
        public array $metadata = [],
    ) {}

    public static function successful(
        string $redirectUrl,
        ?string $transactionId = null,
        ?string $gatewayPaymentId = null,
        array $metadata = [],
    ): self {
        return new self(
            success: true,
            redirectUrl: $redirectUrl,
            transactionId: $transactionId,
            gatewayPaymentId: $gatewayPaymentId,
            errorMessage: null,
            metadata: $metadata,
        );
    }

    public static function failed(
        string $errorMessage,
        ?string $transactionId = null,
        array $metadata = [],
    ): self {
        return new self(
            success: false,
            redirectUrl: null,
            transactionId: $transactionId,
            gatewayPaymentId: null,
            errorMessage: $errorMessage,
            metadata: $metadata,
        );
    }
}

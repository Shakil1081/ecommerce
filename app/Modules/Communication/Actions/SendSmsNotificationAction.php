<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Modules\Communication\DTOs\SmsDispatchResultDto;
use App\Modules\Communication\Services\SmsGatewayManager;
use Throwable;

class SendSmsNotificationAction
{
    public function __construct(
        private readonly SmsGatewayManager $gatewayManager,
    ) {}

    /**
     * Dispatch an SMS message to a phone number.
     */
    public function __invoke(
        string $phoneNumber,
        string $message,
        ?string $providerCode = null,
        ?string $senderId = null,
    ): SmsDispatchResultDto {
        try {
            $gateway = $providerCode !== null
                ? $this->gatewayManager->resolve($providerCode)
                : $this->gatewayManager->getActiveGateway();

            if (! $gateway) {
                return new SmsDispatchResultDto(
                    success: false,
                    messageId: null,
                    message: 'No active SMS gateway configured.',
                );
            }

            return $gateway->sendSms($phoneNumber, $message, $senderId);
        } catch (Throwable $e) {
            return new SmsDispatchResultDto(
                success: false,
                messageId: null,
                message: "SMS dispatch failed: {$e->getMessage()}",
            );
        }
    }
}

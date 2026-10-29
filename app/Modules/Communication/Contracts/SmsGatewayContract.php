<?php

declare(strict_types=1);

namespace App\Modules\Communication\Contracts;

use App\Modules\Communication\DTOs\SmsBalanceResultDto;
use App\Modules\Communication\DTOs\SmsConnectionTestResultDto;
use App\Modules\Communication\DTOs\SmsDispatchResultDto;

interface SmsGatewayContract
{
    /**
     * Get unique provider code.
     */
    public function getCode(): string;

    /**
     * Get human-readable provider name.
     */
    public function getName(): string;

    /**
     * Send an SMS text message to the recipient phone number.
     */
    public function sendSms(string $to, string $message, ?string $senderId = null): SmsDispatchResultDto;

    /**
     * Inquire current account balance.
     */
    public function checkBalance(): SmsBalanceResultDto;

    /**
     * Perform pre-flight connection and credential test.
     */
    public function testConnection(): SmsConnectionTestResultDto;
}

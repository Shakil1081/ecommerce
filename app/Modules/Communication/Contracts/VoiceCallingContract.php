<?php

declare(strict_types=1);

namespace App\Modules\Communication\Contracts;

use App\Modules\Communication\DTOs\VoiceCallInitiationResultDto;
use App\Modules\Communication\DTOs\VoiceCallTestResultDto;
use App\Modules\Order\Models\Order;

interface VoiceCallingContract
{
    /**
     * Get unique provider code.
     */
    public function getProvider(): string;

    /**
     * Dial customer phone number for automated order verification.
     */
    public function initiateVerificationCall(
        string $phoneNumber,
        ?Order $order = null,
        ?string $twimlUrl = null,
    ): VoiceCallInitiationResultDto;

    /**
     * Test voice provider credentials and outbound capability.
     */
    public function testConnection(string $testPhoneNumber): VoiceCallTestResultDto;
}

<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTOs;

use App\Modules\Communication\Models\SmsOtp;

readonly class OtpVerificationResultDto
{
    public function __construct(
        public bool $success,
        public string $message,
        public ?SmsOtp $otp = null,
        public bool $expired = false,
        public bool $exceededAttempts = false,
    ) {}
}

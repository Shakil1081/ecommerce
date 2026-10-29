<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Modules\Communication\DTOs\SmsDispatchResultDto;
use App\Modules\Communication\Models\SmsOtp;
use Illuminate\Support\Facades\Hash;

class GenerateAndSendOtpAction
{
    public function __construct(
        private readonly SendSmsNotificationAction $sendSms,
    ) {}

    /**
     * Generate a cryptographic numeric OTP code, persist it, and dispatch via active SMS gateway.
     *
     * @return array{otp: SmsOtp, dispatch_result: SmsDispatchResultDto, plain_code: string}
     */
    public function __invoke(
        string $phoneNumber,
        string $purpose = 'order_verification',
        ?int $orderId = null,
        int $ttlMinutes = 10,
        int $digits = 4,
        ?string $ipAddress = null,
    ): array {
        // Generate random numeric code: 4 digits (1000..9999) or 6 digits (100000..999999)
        $min = 10 ** ($digits - 1);
        $max = (10 ** $digits) - 1;
        $plainCode = (string) random_int($min, $max);

        $otp = SmsOtp::create([
            'phone_number' => $phoneNumber,
            'otp_code_hash' => Hash::make($plainCode),
            'purpose' => $purpose,
            'order_id' => $orderId,
            'expires_at' => now()->addMinutes($ttlMinutes),
            'attempts' => 0,
            'max_attempts' => 3,
            'ip_address' => $ipAddress,
        ]);

        $message = "Your verification code for E-Commerce BD is: {$plainCode}. Valid for {$ttlMinutes} minutes. Do not share this code.";

        $dispatchResult = ($this->sendSms)(
            phoneNumber: $phoneNumber,
            message: $message,
        );

        return [
            'otp' => $otp,
            'dispatch_result' => $dispatchResult,
            'plain_code' => $plainCode,
        ];
    }
}

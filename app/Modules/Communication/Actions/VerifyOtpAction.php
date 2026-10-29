<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Modules\Communication\DTOs\OtpVerificationResultDto;
use App\Modules\Communication\Models\SmsOtp;

class VerifyOtpAction
{
    /**
     * Verify incoming customer OTP code.
     */
    public function __invoke(
        string $phoneNumber,
        string $code,
        ?string $purpose = null,
        ?int $orderId = null,
    ): OtpVerificationResultDto {
        $query = SmsOtp::query()
            ->where('phone_number', $phoneNumber)
            ->whereNull('verified_at')
            ->latest('id');

        if ($purpose !== null) {
            $query->where('purpose', $purpose);
        }

        if ($orderId !== null) {
            $query->where('order_id', $orderId);
        }

        $otp = $query->first();

        if (! $otp) {
            return new OtpVerificationResultDto(
                success: false,
                message: 'No pending OTP verification found for this phone number.',
            );
        }

        if ($otp->isExpired()) {
            return new OtpVerificationResultDto(
                success: false,
                message: 'This verification code has expired. Please request a new code.',
                otp: $otp,
                expired: true,
            );
        }

        if ($otp->hasExceededAttempts()) {
            return new OtpVerificationResultDto(
                success: false,
                message: 'Maximum verification attempts exceeded. Please request a new code.',
                otp: $otp,
                exceededAttempts: true,
            );
        }

        // Increment attempt counter
        $otp->increment('attempts');

        if (! $otp->verifyCode($code)) {
            $remaining = max(0, $otp->max_attempts - $otp->attempts);

            return new OtpVerificationResultDto(
                success: false,
                message: "Incorrect verification code. {$remaining} attempts remaining.",
                otp: $otp,
            );
        }

        // Successfully verified
        $otp->update([
            'verified_at' => now(),
        ]);

        return new OtpVerificationResultDto(
            success: true,
            message: 'OTP verified successfully.',
            otp: $otp,
        );
    }
}

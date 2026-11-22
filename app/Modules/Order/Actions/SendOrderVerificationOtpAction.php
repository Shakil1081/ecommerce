<?php

declare(strict_types=1);

namespace App\Modules\Order\Actions;

use App\Modules\Communication\Actions\GenerateAndSendOtpAction;
use App\Modules\Communication\Models\SmsOtp;
use App\Modules\Order\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends the order-confirmation SMS OTP for a medium/high-risk order.
 */
final class SendOrderVerificationOtpAction
{
    public const PURPOSE = 'order_verification';

    /** Minimum seconds between two codes for the same order. */
    public const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly GenerateAndSendOtpAction $generateAndSendOtp,
    ) {}

    /**
     * @return bool true when the SMS gateway accepted the message
     */
    public function __invoke(Order $order, ?string $ipAddress = null): bool
    {
        try {
            $result = ($this->generateAndSendOtp)(
                phoneNumber: $order->customer_phone,
                purpose: self::PURPOSE,
                orderId: $order->id,
                ipAddress: $ipAddress,
            );

            if (! $result['dispatch_result']->success) {
                Log::warning('Order verification OTP SMS was not delivered', [
                    'order_number' => $order->order_number,
                    'reason' => $result['dispatch_result']->message,
                ]);
            }

            return $result['dispatch_result']->success;
        } catch (Throwable $e) {
            Log::error('Order verification OTP could not be generated', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Seconds the customer must wait before another code can be sent (0 = can send now).
     */
    public function secondsUntilResendAllowed(Order $order): int
    {
        $lastSentAt = SmsOtp::query()
            ->where('order_id', $order->id)
            ->where('purpose', self::PURPOSE)
            ->latest('id')
            ->value('created_at');

        if ($lastSentAt === null) {
            return 0;
        }

        $elapsed = now()->getTimestamp() - Carbon::parse($lastSentAt)->getTimestamp();

        return max(0, self::RESEND_COOLDOWN_SECONDS - $elapsed);
    }
}

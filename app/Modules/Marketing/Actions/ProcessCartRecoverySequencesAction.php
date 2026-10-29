<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Actions;

use App\Modules\Cart\Models\Cart;
use App\Modules\Communication\Actions\SendSmsNotificationAction;
use App\Modules\Communication\Models\WhatsAppSetting;
use App\Modules\Communication\Services\WhatsAppClient;
use App\Modules\Marketing\Models\CartRecoveryLog;
use Carbon\Carbon;

class ProcessCartRecoverySequencesAction
{
    public function __construct(
        private readonly SendSmsNotificationAction $sendSms,
    ) {}

    /**
     * Scan eligible abandoned carts and dispatch recovery messages for step 1, 2, or 3.
     *
     * @return array{step_1_count: int, step_2_count: int, step_3_count: int, total_dispatched: int}
     */
    public function __invoke(): array
    {
        $now = Carbon::now();
        $step1Count = 0;
        $step2Count = 0;
        $step3Count = 0;

        // Find candidate carts: has items and not converted
        $candidateCarts = Cart::with(['user', 'recoveryLogs', 'items.variant.product'])
            ->where('status', '!=', 'converted')
            ->where('total_items_count', '>', 0)
            ->get();

        $storeName = config('app.name', 'E-Commerce BD');
        $whatsAppSetting = WhatsAppSetting::active()->first();
        $whatsAppClient = ($whatsAppSetting && $whatsAppSetting->isConfigured())
            ? new WhatsAppClient($whatsAppSetting)
            : null;

        foreach ($candidateCarts as $cart) {
            $phone = $cart->customer_phone ?: $cart->user?->phone;
            $name = $cart->customer_name ?: ($cart->user?->name ?: 'Customer');
            $email = $cart->customer_email ?: $cart->user?->email;

            if (empty($phone)) {
                continue;
            }

            $lastActivity = $cart->last_activity_at ?: $cart->updated_at;
            $elapsedMinutes = (int) $lastActivity->diffInMinutes($now);

            // Determine previous dispatched steps
            $existingLogs = $cart->recoveryLogs;
            $hasStep1 = $existingLogs->contains('step', CartRecoveryLog::STEP_ONE);
            $hasStep2 = $existingLogs->contains('step', CartRecoveryLog::STEP_TWO);
            $hasStep3 = $existingLogs->contains('step', CartRecoveryLog::STEP_THREE);

            // Check if cart was already marked recovered
            if ($existingLogs->contains('status', CartRecoveryLog::STATUS_RECOVERED)) {
                continue;
            }

            $recoveryUrl = url('/cart?recover='.$cart->ulid);

            // Step 1: 1 Hour (>= 60 minutes)
            if ($elapsedMinutes >= 60 && ! $hasStep1) {
                $message = "Hello {$name}, you left items in your shopping cart at {$storeName}! Complete your order here: {$recoveryUrl}";

                $channel = $this->dispatchReminder($phone, $message, $whatsAppClient);

                CartRecoveryLog::create([
                    'cart_id' => $cart->id,
                    'customer_name' => $name,
                    'customer_phone' => $phone,
                    'customer_email' => $email,
                    'step' => CartRecoveryLog::STEP_ONE,
                    'channel' => $channel,
                    'status' => CartRecoveryLog::STATUS_SENT,
                    'dispatched_at' => $now,
                    'message_content' => $message,
                ]);

                $cart->update(['status' => 'abandoned']);
                $step1Count++;

                continue;
            }

            // Step 2: 6 Hours (>= 360 minutes)
            if ($elapsedMinutes >= 360 && $hasStep1 && ! $hasStep2) {
                $message = "Hi {$name}, stock is running low for the items in your cart! Order now before they sell out: {$recoveryUrl}";

                $channel = $this->dispatchReminder($phone, $message, $whatsAppClient);

                CartRecoveryLog::create([
                    'cart_id' => $cart->id,
                    'customer_name' => $name,
                    'customer_phone' => $phone,
                    'customer_email' => $email,
                    'step' => CartRecoveryLog::STEP_TWO,
                    'channel' => $channel,
                    'status' => CartRecoveryLog::STATUS_SENT,
                    'dispatched_at' => $now,
                    'message_content' => $message,
                ]);

                $step2Count++;

                continue;
            }

            // Step 3: 24 Hours (>= 1440 minutes)
            if ($elapsedMinutes >= 1440 && $hasStep2 && ! $hasStep3) {
                $couponCode = 'COMEBACK5';
                $message = "Special surprise for {$name}! Use voucher code {$couponCode} for an extra discount on your cart: {$recoveryUrl}";

                $channel = $this->dispatchReminder($phone, $message, $whatsAppClient);

                CartRecoveryLog::create([
                    'cart_id' => $cart->id,
                    'customer_name' => $name,
                    'customer_phone' => $phone,
                    'customer_email' => $email,
                    'step' => CartRecoveryLog::STEP_THREE,
                    'channel' => $channel,
                    'status' => CartRecoveryLog::STATUS_SENT,
                    'incentive_coupon_code' => $couponCode,
                    'dispatched_at' => $now,
                    'message_content' => $message,
                ]);

                $step3Count++;

                continue;
            }
        }

        return [
            'step_1_count' => $step1Count,
            'step_2_count' => $step2Count,
            'step_3_count' => $step3Count,
            'total_dispatched' => $step1Count + $step2Count + $step3Count,
        ];
    }

    private function dispatchReminder(string $phone, string $message, ?WhatsAppClient $whatsAppClient): string
    {
        // Try WhatsApp first if active, otherwise SMS
        if ($whatsAppClient) {
            $waResult = $whatsAppClient->sendTextMessage($phone, $message);
            if ($waResult->success) {
                return CartRecoveryLog::CHANNEL_WHATSAPP;
            }
        }

        ($this->sendSms)(phoneNumber: $phone, message: $message);

        return CartRecoveryLog::CHANNEL_SMS;
    }
}

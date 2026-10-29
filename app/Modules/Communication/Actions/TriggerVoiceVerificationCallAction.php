<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Modules\Communication\DTOs\VoiceCallInitiationResultDto;
use App\Modules\Communication\Models\VoiceCallingSetting;
use App\Modules\Communication\Models\VoiceCallLog;
use App\Modules\Communication\Services\VoiceCallingManager;
use App\Modules\Order\Models\Order;
use Throwable;

class TriggerVoiceVerificationCallAction
{
    public function __construct(
        private readonly VoiceCallingManager $manager,
    ) {}

    /**
     * Trigger outbound AI voice verification call for an order.
     */
    public function __invoke(Order $order): VoiceCallInitiationResultDto
    {
        $setting = VoiceCallingSetting::active()->first();

        if (! $setting || ! $setting->isConfigured()) {
            return new VoiceCallInitiationResultDto(
                success: false,
                callSid: null,
                message: 'AI Voice Calling is not configured or inactive.',
            );
        }

        $log = VoiceCallLog::create([
            'order_id' => $order->id,
            'phone_number' => $order->customer_phone,
            'provider' => $setting->provider,
            'status' => VoiceCallLog::STATUS_INITIATED,
            'notes' => "Initiating automated COD verification call for order #{$order->order_number}.",
        ]);

        try {
            $driver = $this->manager->resolve($setting->provider);
            $result = $driver->initiateVerificationCall(
                phoneNumber: $order->customer_phone,
                order: $order,
            );

            if ($result->success && $result->callSid) {
                $log->update([
                    'call_sid' => $result->callSid,
                    'status' => VoiceCallLog::STATUS_RINGING,
                ]);
            } else {
                $log->update([
                    'status' => VoiceCallLog::STATUS_FAILED,
                    'verification_result' => VoiceCallLog::RESULT_FAILED,
                    'notes' => $result->message,
                ]);
            }

            return $result;
        } catch (Throwable $e) {
            $log->update([
                'status' => VoiceCallLog::STATUS_FAILED,
                'verification_result' => VoiceCallLog::RESULT_FAILED,
                'notes' => $e->getMessage(),
            ]);

            return new VoiceCallInitiationResultDto(
                success: false,
                callSid: null,
                message: "Voice calling failed: {$e->getMessage()}",
            );
        }
    }
}

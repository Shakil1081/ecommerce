<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Contracts\VoiceCallingContract;
use App\Modules\Communication\DTOs\VoiceCallInitiationResultDto;
use App\Modules\Communication\DTOs\VoiceCallTestResultDto;
use App\Modules\Communication\Models\VoiceCallingSetting;
use App\Modules\Order\Models\Order;
use Illuminate\Support\Facades\Http;
use Throwable;

class TwilioVoiceAdapter implements VoiceCallingContract
{
    private const BASE_URL = 'https://api.twilio.com/2010-04-01';

    public function __construct(
        private readonly ?VoiceCallingSetting $setting = null,
        private readonly ?array $overrideCredentials = null,
    ) {}

    public function getProvider(): string
    {
        return VoiceCallingSetting::PROVIDER_TWILIO;
    }

    private function getAccountSid(): string
    {
        if ($this->overrideCredentials && isset($this->overrideCredentials['account_sid'])) {
            return (string) $this->overrideCredentials['account_sid'];
        }

        return (string) ($this->setting?->getCredential('account_sid') ?? '');
    }

    private function getAuthToken(): string
    {
        if ($this->overrideCredentials && isset($this->overrideCredentials['auth_token'])) {
            return (string) $this->overrideCredentials['auth_token'];
        }

        return (string) ($this->setting?->getCredential('auth_token') ?? '');
    }

    private function getCallerId(): string
    {
        if ($this->overrideCredentials && isset($this->overrideCredentials['caller_id'])) {
            return (string) $this->overrideCredentials['caller_id'];
        }

        return (string) ($this->setting?->caller_id ?? $this->setting?->getCredential('caller_id') ?? '');
    }

    public function initiateVerificationCall(
        string $phoneNumber,
        ?Order $order = null,
        ?string $twimlUrl = null,
    ): VoiceCallInitiationResultDto {
        $sid = $this->getAccountSid();
        $token = $this->getAuthToken();
        $callerId = $this->getCallerId();

        if (empty($sid) || empty($token) || empty($callerId)) {
            return new VoiceCallInitiationResultDto(
                success: false,
                callSid: null,
                message: 'Twilio Voice credentials (account_sid, auth_token, or caller_id) are missing.',
            );
        }

        // Clean to E.164
        $cleanPhone = $phoneNumber;
        if (! str_starts_with($cleanPhone, '+')) {
            $digits = preg_replace('/[^\d]/', '', $cleanPhone) ?? '';
            if (str_starts_with($digits, '880')) {
                $cleanPhone = '+'.$digits;
            } elseif (str_starts_with($digits, '01')) {
                $cleanPhone = '+88'.$digits;
            } else {
                $cleanPhone = '+'.$digits;
            }
        }

        $orderParam = $order ? "?order_id={$order->id}" : '';
        $url = $twimlUrl ?: url("/webhooks/voice/twiml{$orderParam}");
        $statusCallback = url("/webhooks/voice/status{$orderParam}");

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->timeout(10)
                ->post(self::BASE_URL."/Accounts/{$sid}/Calls.json", [
                    'To' => $cleanPhone,
                    'From' => $callerId,
                    'Url' => $url,
                    'Method' => 'POST',
                    'StatusCallback' => $statusCallback,
                    'StatusCallbackMethod' => 'POST',
                    'StatusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
                ]);

            $json = $response->json();

            if ($response->successful() && isset($json['sid'])) {
                $callSid = (string) $json['sid'];

                return new VoiceCallInitiationResultDto(
                    success: true,
                    callSid: $callSid,
                    message: "Verification call dispatched to {$cleanPhone} (SID: {$callSid}).",
                    rawResponse: is_array($json) ? $json : [],
                );
            }

            $errMsg = $json['message'] ?? $response->body();

            return new VoiceCallInitiationResultDto(
                success: false,
                callSid: null,
                message: "Twilio Call initiation failed: {$errMsg}",
                rawResponse: is_array($json) ? $json : [],
            );
        } catch (Throwable $e) {
            return new VoiceCallInitiationResultDto(
                success: false,
                callSid: null,
                message: "Twilio Voice network error: {$e->getMessage()}",
            );
        }
    }

    public function testConnection(string $testPhoneNumber): VoiceCallTestResultDto
    {
        $startTime = hrtime(true);
        $initiation = $this->initiateVerificationCall($testPhoneNumber);
        $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);

        if ($initiation->success) {
            $msg = "Twilio Voice connection verified. Outbound test call initiated to {$testPhoneNumber} (SID: {$initiation->callSid}).";

            if ($this->setting && $this->setting->exists) {
                $this->setting->update([
                    'last_tested_at' => now(),
                    'last_test_status' => 'success',
                    'last_test_message' => $msg,
                ]);
            }

            return new VoiceCallTestResultDto(
                success: true,
                message: $msg,
                latencyMs: $latencyMs,
                details: ['call_sid' => $initiation->callSid],
            );
        }

        $errMsg = "Twilio Voice test failed: {$initiation->message}";

        if ($this->setting && $this->setting->exists) {
            $this->setting->update([
                'last_tested_at' => now(),
                'last_test_status' => 'failed',
                'last_test_message' => $errMsg,
            ]);
        }

        return new VoiceCallTestResultDto(
            success: false,
            message: $errMsg,
            latencyMs: $latencyMs,
        );
    }
}

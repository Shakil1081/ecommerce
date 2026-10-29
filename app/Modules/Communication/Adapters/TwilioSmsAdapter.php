<?php

declare(strict_types=1);

namespace App\Modules\Communication\Adapters;

use App\Modules\Communication\Contracts\SmsGatewayContract;
use App\Modules\Communication\DTOs\SmsBalanceResultDto;
use App\Modules\Communication\DTOs\SmsConnectionTestResultDto;
use App\Modules\Communication\DTOs\SmsDispatchResultDto;
use App\Modules\Communication\Models\SmsGatewaySetting;
use Illuminate\Support\Facades\Http;
use Throwable;

class TwilioSmsAdapter implements SmsGatewayContract
{
    private const BASE_URL = 'https://api.twilio.com/2010-04-01';

    public function __construct(
        private readonly ?SmsGatewaySetting $setting = null,
        private readonly ?array $overrideCredentials = null,
    ) {}

    public function getCode(): string
    {
        return SmsGatewaySetting::CODE_TWILIO;
    }

    public function getName(): string
    {
        return 'Twilio SMS Gateway';
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

    private function getFromNumber(?string $override = null): string
    {
        if (! empty($override)) {
            return $override;
        }

        if ($this->overrideCredentials && ! empty($this->overrideCredentials['from_number'])) {
            return (string) $this->overrideCredentials['from_number'];
        }

        return (string) ($this->setting?->getCredential('from_number') ?? '');
    }

    public function sendSms(string $to, string $message, ?string $senderId = null): SmsDispatchResultDto
    {
        $sid = $this->getAccountSid();
        $token = $this->getAuthToken();
        $from = $this->getFromNumber($senderId);

        if (empty($sid) || empty($token) || empty($from)) {
            return new SmsDispatchResultDto(
                success: false,
                messageId: null,
                message: 'Twilio SMS credentials (account_sid, auth_token, or from_number) are missing.',
            );
        }

        // Format E.164: +8801712345678
        $cleanPhone = $to;
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

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->timeout(10)
                ->post(self::BASE_URL."/Accounts/{$sid}/Messages.json", [
                    'To' => $cleanPhone,
                    'From' => $from,
                    'Body' => $message,
                ]);

            $json = $response->json();

            if ($response->successful() && isset($json['sid'])) {
                $cost = isset($json['price']) ? abs((float) $json['price']) : null;

                return new SmsDispatchResultDto(
                    success: true,
                    messageId: (string) $json['sid'],
                    message: "SMS sent successfully via Twilio to {$cleanPhone}.",
                    costBdt: $cost,
                    rawResponse: is_array($json) ? $json : [],
                );
            }

            $errMsg = $json['message'] ?? $response->body();

            return new SmsDispatchResultDto(
                success: false,
                messageId: null,
                message: "Twilio dispatch failed: {$errMsg}",
                rawResponse: is_array($json) ? $json : ['body' => $response->body()],
            );
        } catch (Throwable $e) {
            return new SmsDispatchResultDto(
                success: false,
                messageId: null,
                message: "Twilio network error: {$e->getMessage()}",
            );
        }
    }

    public function checkBalance(): SmsBalanceResultDto
    {
        $sid = $this->getAccountSid();
        $token = $this->getAuthToken();

        if (empty($sid) || empty($token)) {
            return new SmsBalanceResultDto(
                success: false,
                balanceBdt: null,
                message: 'Twilio credentials are missing.',
            );
        }

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->timeout(8)
                ->get(self::BASE_URL."/Accounts/{$sid}/Balance.json");

            $json = $response->json();

            if ($response->successful() && isset($json['balance'])) {
                $balance = (float) $json['balance'];
                $currency = (string) ($json['currency'] ?? 'USD');

                return new SmsBalanceResultDto(
                    success: true,
                    balanceBdt: $balance,
                    message: "Twilio account balance: {$currency} {$balance}",
                    details: is_array($json) ? $json : [],
                );
            }

            $errMsg = $json['message'] ?? $response->body();

            return new SmsBalanceResultDto(
                success: false,
                balanceBdt: null,
                message: "Failed to retrieve Twilio balance: {$errMsg}",
            );
        } catch (Throwable $e) {
            return new SmsBalanceResultDto(
                success: false,
                balanceBdt: null,
                message: "Twilio balance inquiry failed: {$e->getMessage()}",
            );
        }
    }

    public function testConnection(): SmsConnectionTestResultDto
    {
        $startTime = hrtime(true);
        $balanceResult = $this->checkBalance();
        $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);

        if ($balanceResult->success) {
            return new SmsConnectionTestResultDto(
                success: true,
                message: "Twilio connected successfully. Balance: {$balanceResult->balanceBdt}",
                latencyMs: $latencyMs,
                balanceBdt: $balanceResult->balanceBdt,
            );
        }

        return new SmsConnectionTestResultDto(
            success: false,
            message: $balanceResult->message,
            latencyMs: $latencyMs,
        );
    }
}

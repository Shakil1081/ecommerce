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

class ReveSmsAdapter implements SmsGatewayContract
{
    private const BASE_URL = 'https://smpp.revesms.com';

    public function __construct(
        private readonly ?SmsGatewaySetting $setting = null,
        private readonly ?array $overrideCredentials = null,
    ) {}

    public function getCode(): string
    {
        return SmsGatewaySetting::CODE_REVE;
    }

    public function getName(): string
    {
        return 'Reve SMS Gateway';
    }

    private function getApiKey(): string
    {
        if ($this->overrideCredentials && isset($this->overrideCredentials['api_key'])) {
            return (string) $this->overrideCredentials['api_key'];
        }

        return (string) ($this->setting?->getCredential('api_key') ?? '');
    }

    private function getSecretKey(): string
    {
        if ($this->overrideCredentials && isset($this->overrideCredentials['secret_key'])) {
            return (string) $this->overrideCredentials['secret_key'];
        }

        return (string) ($this->setting?->getCredential('secret_key') ?? '');
    }

    private function getCallerId(?string $override = null): string
    {
        if (! empty($override)) {
            return $override;
        }

        if ($this->overrideCredentials && ! empty($this->overrideCredentials['sender_id'])) {
            return (string) $this->overrideCredentials['sender_id'];
        }

        return (string) ($this->setting?->sender_id ?? 'REVESMS');
    }

    public function sendSms(string $to, string $message, ?string $senderId = null): SmsDispatchResultDto
    {
        $apiKey = $this->getApiKey();
        $secretKey = $this->getSecretKey();

        if (empty($apiKey) || empty($secretKey)) {
            return new SmsDispatchResultDto(
                success: false,
                messageId: null,
                message: 'Reve SMS credentials (api_key or secret_key) are missing.',
            );
        }

        $cleanPhone = preg_replace('/[^\d]/', '', $to) ?? '';

        try {
            $response = Http::timeout(10)->get(self::BASE_URL.'/sendtext', [
                'apikey' => $apiKey,
                'secretkey' => $secretKey,
                'callerID' => $this->getCallerId($senderId),
                'toUser' => $cleanPhone,
                'messageContent' => $message,
            ]);

            $json = $response->json();
            $status = $json['Status'] ?? $json['status'] ?? null;

            if ($response->successful() && ($status === 0 || $status === '0' || $status === 'SUCCESS')) {
                $msgId = (string) ($json['Message_ID'] ?? $json['message_id'] ?? uniqid('REVE-'));

                return new SmsDispatchResultDto(
                    success: true,
                    messageId: $msgId,
                    message: "SMS sent successfully via Reve SMS to {$cleanPhone}.",
                    rawResponse: is_array($json) ? $json : ['raw' => $response->body()],
                );
            }

            $errMsg = $json['Text'] ?? $json['text'] ?? $response->body();

            return new SmsDispatchResultDto(
                success: false,
                messageId: null,
                message: "Reve SMS dispatch rejected: {$errMsg}",
                rawResponse: is_array($json) ? $json : ['body' => $response->body()],
            );
        } catch (Throwable $e) {
            return new SmsDispatchResultDto(
                success: false,
                messageId: null,
                message: "Reve SMS connection error: {$e->getMessage()}",
            );
        }
    }

    public function checkBalance(): SmsBalanceResultDto
    {
        $apiKey = $this->getApiKey();
        $secretKey = $this->getSecretKey();

        if (empty($apiKey) || empty($secretKey)) {
            return new SmsBalanceResultDto(
                success: false,
                balanceBdt: null,
                message: 'Reve SMS credentials are missing.',
            );
        }

        try {
            $response = Http::timeout(8)->get(self::BASE_URL.'/checkbalance', [
                'apikey' => $apiKey,
                'secretkey' => $secretKey,
            ]);

            $body = trim($response->body());
            $json = $response->json();

            $balance = null;
            if (is_array($json) && isset($json['Balance'])) {
                $balance = (float) $json['Balance'];
            } elseif (preg_match('/([\d]+(?:\.\d+)?)/', $body, $matches)) {
                $balance = (float) $matches[1];
            }

            if ($balance !== null) {
                return new SmsBalanceResultDto(
                    success: true,
                    balanceBdt: $balance,
                    message: "Reve SMS account balance: ৳{$balance}",
                    details: is_array($json) ? $json : ['raw' => $body],
                );
            }

            return new SmsBalanceResultDto(
                success: false,
                balanceBdt: null,
                message: "Unable to parse Reve SMS balance: {$body}",
            );
        } catch (Throwable $e) {
            return new SmsBalanceResultDto(
                success: false,
                balanceBdt: null,
                message: "Reve SMS balance error: {$e->getMessage()}",
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
                message: "Reve SMS connected successfully. Current balance: ৳{$balanceResult->balanceBdt}",
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

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

class GreenwebSmsAdapter implements SmsGatewayContract
{
    private const BASE_URL = 'https://api.greenweb.com.bd';

    public function __construct(
        private readonly ?SmsGatewaySetting $setting = null,
        private readonly ?array $overrideCredentials = null,
    ) {}

    public function getCode(): string
    {
        return SmsGatewaySetting::CODE_GREENWEB;
    }

    public function getName(): string
    {
        return 'Greenweb SMS Gateway';
    }

    private function getToken(): string
    {
        if ($this->overrideCredentials && isset($this->overrideCredentials['token'])) {
            return (string) $this->overrideCredentials['token'];
        }

        return (string) ($this->setting?->getCredential('token') ?? '');
    }

    public function sendSms(string $to, string $message, ?string $senderId = null): SmsDispatchResultDto
    {
        $token = $this->getToken();
        if (empty($token)) {
            return new SmsDispatchResultDto(
                success: false,
                messageId: null,
                message: 'Greenweb SMS token is not configured.',
            );
        }

        // Format Bangladesh local number: 01712345678 or 8801712345678
        $cleanPhone = preg_replace('/[^\d]/', '', $to) ?? '';

        try {
            $response = Http::timeout(10)->post(self::BASE_URL.'/api.php', [
                'token' => $token,
                'to' => $cleanPhone,
                'message' => $message,
            ]);

            $body = $response->body();

            // Greenweb responds with text like: "Ok: SMS Sent 1" or error "Invalid MSISDN"
            if ($response->successful() && str_contains(strtolower($body), 'ok')) {
                return new SmsDispatchResultDto(
                    success: true,
                    messageId: 'GW-'.uniqid(),
                    message: "SMS dispatched successfully via Greenweb to {$cleanPhone}.",
                    rawResponse: ['response' => $body],
                );
            }

            return new SmsDispatchResultDto(
                success: false,
                messageId: null,
                message: "Greenweb SMS dispatch rejected: {$body}",
                rawResponse: ['response' => $body, 'status' => $response->status()],
            );
        } catch (Throwable $e) {
            return new SmsDispatchResultDto(
                success: false,
                messageId: null,
                message: "Greenweb network error: {$e->getMessage()}",
            );
        }
    }

    public function checkBalance(): SmsBalanceResultDto
    {
        $token = $this->getToken();
        if (empty($token)) {
            return new SmsBalanceResultDto(
                success: false,
                balanceBdt: null,
                message: 'Greenweb SMS token is missing.',
            );
        }

        try {
            $response = Http::timeout(8)->get(self::BASE_URL.'/grebxml.php', [
                'token' => $token,
                'balance' => '',
            ]);

            $body = trim($response->body());

            // Greenweb balance endpoint returns number or XML <response>250.00</response>
            if (preg_match('/([\d]+(?:\.\d+)?)/', $body, $matches)) {
                $balance = (float) $matches[1];

                return new SmsBalanceResultDto(
                    success: true,
                    balanceBdt: $balance,
                    message: "Greenweb account balance: ৳{$balance}",
                    details: ['raw' => $body],
                );
            }

            return new SmsBalanceResultDto(
                success: false,
                balanceBdt: null,
                message: "Could not parse Greenweb balance: {$body}",
            );
        } catch (Throwable $e) {
            return new SmsBalanceResultDto(
                success: false,
                balanceBdt: null,
                message: "Greenweb balance check failed: {$e->getMessage()}",
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
                message: "Greenweb SMS connected successfully. Current balance: ৳{$balanceResult->balanceBdt}",
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

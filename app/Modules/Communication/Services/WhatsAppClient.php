<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\DTOs\WhatsAppConnectionTestResultDto;
use App\Modules\Communication\DTOs\WhatsAppDispatchResultDto;
use App\Modules\Communication\Models\WhatsAppSetting;
use Illuminate\Support\Facades\Http;
use Throwable;

class WhatsAppClient
{
    private const BASE_URL = 'https://graph.facebook.com';

    public function __construct(
        private readonly ?WhatsAppSetting $setting = null,
        private readonly ?array $overrideCredentials = null,
    ) {}

    private function getPhoneNumberId(): string
    {
        if ($this->overrideCredentials && isset($this->overrideCredentials['phone_number_id'])) {
            return (string) $this->overrideCredentials['phone_number_id'];
        }

        return (string) ($this->setting?->phone_number_id ?? '');
    }

    private function getAccessToken(): string
    {
        if ($this->overrideCredentials && isset($this->overrideCredentials['access_token'])) {
            return (string) $this->overrideCredentials['access_token'];
        }

        return (string) ($this->setting?->access_token ?? '');
    }

    private function getApiVersion(): string
    {
        return $this->setting?->api_version ?? 'v21.0';
    }

    /**
     * Send a pre-approved WhatsApp Business template message.
     *
     * @param  array<int, array<string, mixed>>  $components
     */
    public function sendTemplateMessage(
        string $to,
        string $templateName,
        string $languageCode = 'en_US',
        array $components = [],
    ): WhatsAppDispatchResultDto {
        $phoneId = $this->getPhoneNumberId();
        $token = $this->getAccessToken();

        if (empty($phoneId) || empty($token)) {
            return new WhatsAppDispatchResultDto(
                success: false,
                messageId: null,
                message: 'WhatsApp Business API credentials (phone_number_id or access_token) are missing.',
            );
        }

        // Clean recipient phone: e.g. 8801712345678 (WhatsApp expects international format without +)
        $cleanPhone = preg_replace('/[^\d]/', '', $to) ?? '';
        if (str_starts_with($cleanPhone, '01')) {
            $cleanPhone = '88'.$cleanPhone;
        }

        $url = self::BASE_URL."/{$this->getApiVersion()}/{$phoneId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $cleanPhone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
            ],
        ];

        if (! empty($components)) {
            $payload['template']['components'] = $components;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->post($url, $payload);

            $json = $response->json();

            if ($response->successful() && isset($json['messages'][0]['id'])) {
                $msgId = (string) $json['messages'][0]['id'];

                return new WhatsAppDispatchResultDto(
                    success: true,
                    messageId: $msgId,
                    message: "WhatsApp template [{$templateName}] sent successfully to {$cleanPhone}.",
                    rawResponse: is_array($json) ? $json : [],
                );
            }

            $errMsg = $json['error']['message'] ?? $response->body();

            return new WhatsAppDispatchResultDto(
                success: false,
                messageId: null,
                message: "WhatsApp dispatch error: {$errMsg}",
                rawResponse: is_array($json) ? $json : ['body' => $response->body()],
            );
        } catch (Throwable $e) {
            return new WhatsAppDispatchResultDto(
                success: false,
                messageId: null,
                message: "WhatsApp network error: {$e->getMessage()}",
            );
        }
    }

    /**
     * Send free-form text message (valid within 24-hour service window).
     */
    public function sendTextMessage(string $to, string $body): WhatsAppDispatchResultDto
    {
        $phoneId = $this->getPhoneNumberId();
        $token = $this->getAccessToken();

        if (empty($phoneId) || empty($token)) {
            return new WhatsAppDispatchResultDto(
                success: false,
                messageId: null,
                message: 'WhatsApp Business API credentials are missing.',
            );
        }

        $cleanPhone = preg_replace('/[^\d]/', '', $to) ?? '';
        if (str_starts_with($cleanPhone, '01')) {
            $cleanPhone = '88'.$cleanPhone;
        }

        $url = self::BASE_URL."/{$this->getApiVersion()}/{$phoneId}/messages";

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $cleanPhone,
                    'type' => 'text',
                    'text' => ['preview_url' => false, 'body' => $body],
                ]);

            $json = $response->json();

            if ($response->successful() && isset($json['messages'][0]['id'])) {
                return new WhatsAppDispatchResultDto(
                    success: true,
                    messageId: (string) $json['messages'][0]['id'],
                    message: "WhatsApp text message sent successfully to {$cleanPhone}.",
                    rawResponse: is_array($json) ? $json : [],
                );
            }

            $errMsg = $json['error']['message'] ?? $response->body();

            return new WhatsAppDispatchResultDto(
                success: false,
                messageId: null,
                message: "WhatsApp dispatch error: {$errMsg}",
                rawResponse: is_array($json) ? $json : [],
            );
        } catch (Throwable $e) {
            return new WhatsAppDispatchResultDto(
                success: false,
                messageId: null,
                message: "WhatsApp network error: {$e->getMessage()}",
            );
        }
    }

    /**
     * Test connection to WhatsApp Business API by checking phone number ID details.
     */
    public function testConnection(): WhatsAppConnectionTestResultDto
    {
        $phoneId = $this->getPhoneNumberId();
        $token = $this->getAccessToken();

        if (empty($phoneId) || empty($token)) {
            return new WhatsAppConnectionTestResultDto(
                success: false,
                message: 'WhatsApp credentials (phone_number_id or access_token) are missing.',
            );
        }

        $url = self::BASE_URL."/{$this->getApiVersion()}/{$phoneId}";
        $startTime = hrtime(true);

        try {
            $response = Http::withToken($token)
                ->timeout(8)
                ->get($url);

            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);
            $json = $response->json();

            if ($response->successful() && isset($json['id'])) {
                $displayNumber = (string) ($json['display_phone_number'] ?? $phoneId);
                $verifiedName = (string) ($json['verified_name'] ?? 'Verified Business');

                $message = "WhatsApp Business API connected successfully. Phone: {$displayNumber} ({$verifiedName}).";

                if ($this->setting && $this->setting->exists) {
                    $this->setting->update([
                        'last_tested_at' => now(),
                        'last_test_status' => 'success',
                        'last_test_message' => $message,
                    ]);
                }

                return new WhatsAppConnectionTestResultDto(
                    success: true,
                    message: $message,
                    latencyMs: $latencyMs,
                    details: is_array($json) ? $json : [],
                );
            }

            $errMsg = $json['error']['message'] ?? $response->body();
            $message = "WhatsApp connection test failed: {$errMsg}";

            if ($this->setting && $this->setting->exists) {
                $this->setting->update([
                    'last_tested_at' => now(),
                    'last_test_status' => 'failed',
                    'last_test_message' => $message,
                ]);
            }

            return new WhatsAppConnectionTestResultDto(
                success: false,
                message: $message,
                latencyMs: $latencyMs,
                details: is_array($json) ? $json : [],
            );
        } catch (Throwable $e) {
            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);
            $errMsg = "WhatsApp connection error: {$e->getMessage()}";

            if ($this->setting && $this->setting->exists) {
                $this->setting->update([
                    'last_tested_at' => now(),
                    'last_test_status' => 'failed',
                    'last_test_message' => $errMsg,
                ]);
            }

            return new WhatsAppConnectionTestResultDto(
                success: false,
                message: $errMsg,
                latencyMs: $latencyMs,
            );
        }
    }
}

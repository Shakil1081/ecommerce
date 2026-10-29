<?php

declare(strict_types=1);

namespace App\Modules\Common\Services;

use App\Modules\Common\DTOs\CaptchaVerificationResultDto;
use App\Modules\Common\Models\BotSecuritySetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class BotCaptchaVerificationService
{
    public const GOOGLE_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Verify a submitted CAPTCHA or Turnstile token against the active provider.
     */
    public function verifyToken(
        string $token,
        ?string $clientIp = null,
        ?BotSecuritySetting $setting = null
    ): CaptchaVerificationResultDto {
        $activeSetting = $setting ?? BotSecuritySetting::getActive();

        // If bot security is disabled or not configured, pass automatically
        if ($activeSetting === null || ! $activeSetting->is_active) {
            return new CaptchaVerificationResultDto(
                success: true,
                errorMessage: 'Bot security is disabled.'
            );
        }

        if (empty(trim($token))) {
            return new CaptchaVerificationResultDto(
                success: false,
                provider: $activeSetting->provider,
                errorMessage: 'Missing CAPTCHA challenge token.'
            );
        }

        $endpoint = $activeSetting->provider === BotSecuritySetting::PROVIDER_TURNSTILE
            ? self::TURNSTILE_VERIFY_URL
            : self::GOOGLE_VERIFY_URL;

        try {
            $payload = [
                'secret' => $activeSetting->secret_key,
                'response' => $token,
            ];

            if (! empty($clientIp)) {
                $payload['remoteip'] = $clientIp;
            }

            $response = Http::asForm()->timeout(5)->post($endpoint, $payload);

            if (! $response->successful()) {
                Log::warning('CAPTCHA verification endpoint returned non-200', [
                    'provider' => $activeSetting->provider,
                    'status' => $response->status(),
                ]);

                return new CaptchaVerificationResultDto(
                    success: false,
                    provider: $activeSetting->provider,
                    errorMessage: 'CAPTCHA verification service returned an error.'
                );
            }

            /** @var array<string, mixed> $json */
            $json = $response->json() ?? [];
            $isSuccess = (bool) ($json['success'] ?? false);
            $score = isset($json['score']) ? (float) $json['score'] : null;
            $hostname = isset($json['hostname']) ? (string) $json['hostname'] : null;
            /** @var array<string> $errorCodes */
            $errorCodes = $json['error-codes'] ?? [];

            // If provider is reCAPTCHA v3, enforce the score threshold
            if ($isSuccess && $activeSetting->provider === BotSecuritySetting::PROVIDER_RECAPTCHA_V3 && $score !== null) {
                $threshold = (float) $activeSetting->score_threshold;
                if ($score < $threshold) {
                    return new CaptchaVerificationResultDto(
                        success: false,
                        score: $score,
                        provider: $activeSetting->provider,
                        hostname: $hostname,
                        errorCodes: $errorCodes,
                        errorMessage: "CAPTCHA score [{$score}] is below the required security threshold [{$threshold}]."
                    );
                }
            }

            return new CaptchaVerificationResultDto(
                success: $isSuccess,
                score: $score,
                provider: $activeSetting->provider,
                hostname: $hostname,
                errorCodes: $errorCodes,
                errorMessage: $isSuccess ? null : ($errorCodes[0] ?? 'Challenge verification failed.')
            );
        } catch (Throwable $e) {
            Log::warning('CAPTCHA verification failed with exception', [
                'provider' => $activeSetting->provider,
                'error' => $e->getMessage(),
            ]);

            return new CaptchaVerificationResultDto(
                success: false,
                provider: $activeSetting->provider,
                errorMessage: 'Could not connect to CAPTCHA verification provider.'
            );
        }
    }

    /**
     * Test connection handshake against provider using dummy or test token.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(string $provider, string $siteKey, string $secretKey): array
    {
        if (empty($siteKey) || empty($secretKey)) {
            return [
                'success' => false,
                'message' => 'Site Key and Secret Key are required to test connection.',
            ];
        }

        $endpoint = $provider === BotSecuritySetting::PROVIDER_TURNSTILE
            ? self::TURNSTILE_VERIFY_URL
            : self::GOOGLE_VERIFY_URL;

        try {
            // Send test handshake with dummy response to test that API credentials and secret key handshake succeed
            $response = Http::asForm()->timeout(5)->post($endpoint, [
                'secret' => $secretKey,
                'response' => 'test_handshake_probe_token',
            ]);

            if ($response->successful()) {
                /** @var array<string, mixed> $json */
                $json = $response->json() ?? [];

                // If error-codes contain 'invalid-input-secret', secret key is invalid!
                $errorCodes = $json['error-codes'] ?? [];
                if (in_array('invalid-input-secret', $errorCodes, true)) {
                    return [
                        'success' => false,
                        'message' => 'The provided Secret Key was rejected as invalid by the provider.',
                    ];
                }

                // If provider responded and checked the token (even if test token is invalid-input-response), credentials handshake succeeded!
                return [
                    'success' => true,
                    'message' => "Successfully connected to {$provider} verification endpoint.",
                ];
            }

            return [
                'success' => false,
                'message' => "Endpoint returned HTTP status {$response->status()}.",
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "Connection failed: {$e->getMessage()}",
            ];
        }
    }
}

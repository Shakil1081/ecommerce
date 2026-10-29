<?php

declare(strict_types=1);

namespace App\Modules\Common\Security;

use App\Modules\Common\Exceptions\BotSubmissionDetectedException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

final class HoneypotTimingGuardService
{
    public const DEFAULT_HONEYPOT_FIELD = 'user_company_fax';

    public const DEFAULT_TIMING_FIELD = '_form_timing_token';

    public const DEFAULT_MINIMUM_SECONDS = 3.5;

    public const MAX_TOKEN_AGE_SECONDS = 86400.0; // 24 hours

    /**
     * Generate an encrypted cryptographic timestamp token to embed in forms.
     */
    public function generateTimingToken(?float $microtime = null): string
    {
        $timestamp = $microtime ?? microtime(true);

        return Crypt::encryptString(json_encode([
            'rendered_at' => $timestamp,
            'nonce' => Str::random(16),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Verify that the invisible honeypot trap field is empty.
     *
     * @param  Request|array<string, mixed>  $source
     */
    public function validateHoneypot(
        Request|array $source,
        string $field = self::DEFAULT_HONEYPOT_FIELD
    ): bool {
        $value = $source instanceof Request ? $source->input($field) : ($source[$field] ?? null);

        return empty($value);
    }

    /**
     * Verify that the form submission took at least the minimum human threshold seconds.
     *
     * @param  Request|array<string, mixed>  $source
     * @return array{valid: bool, elapsed: float, error?: string}
     */
    public function validateTiming(
        Request|array $source,
        float $minimumSeconds = self::DEFAULT_MINIMUM_SECONDS,
        string $field = self::DEFAULT_TIMING_FIELD
    ): array {
        $token = $source instanceof Request ? $source->input($field) : ($source[$field] ?? null);

        if (! is_string($token) || empty($token)) {
            return [
                'valid' => false,
                'elapsed' => 0.0,
                'error' => 'Missing security timing token.',
            ];
        }

        try {
            $decrypted = Crypt::decryptString($token);
            /** @var array{rendered_at: float, nonce: string} $payload */
            $payload = json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);
            $renderedAt = (float) ($payload['rendered_at'] ?? 0.0);
        } catch (DecryptException|\JsonException) {
            return [
                'valid' => false,
                'elapsed' => 0.0,
                'error' => 'Invalid or corrupted timing token.',
            ];
        }

        $now = microtime(true);
        $elapsed = $now - $renderedAt;

        // Must not be in future (clock skew allowance 0.5s) and must not exceed max age
        if ($elapsed < -0.5 || $elapsed > self::MAX_TOKEN_AGE_SECONDS) {
            return [
                'valid' => false,
                'elapsed' => max(0.0, $elapsed),
                'error' => 'Timing token has expired or has invalid clock skew.',
            ];
        }

        if ($elapsed < $minimumSeconds) {
            return [
                'valid' => false,
                'elapsed' => $elapsed,
                'error' => "Submission completed in {$elapsed}s, faster than threshold ({$minimumSeconds}s).",
            ];
        }

        return [
            'valid' => true,
            'elapsed' => $elapsed,
        ];
    }

    /**
     * Inspect a submission payload and return structured evaluation details.
     *
     * @param  Request|array<string, mixed>  $source
     * @return array{
     *     is_bot: bool,
     *     reason: ?string,
     *     elapsed_seconds: ?float
     * }
     */
    public function inspect(
        Request|array $source,
        float $minimumSeconds = self::DEFAULT_MINIMUM_SECONDS,
        string $honeypotField = self::DEFAULT_HONEYPOT_FIELD,
        string $timingField = self::DEFAULT_TIMING_FIELD
    ): array {
        // 1. Check honeypot trap
        if (! $this->validateHoneypot($source, $honeypotField)) {
            return [
                'is_bot' => true,
                'reason' => 'Honeypot trap triggered (hidden field was filled by automated bot).',
                'elapsed_seconds' => null,
            ];
        }

        // 2. Check submission timing guard
        $timingResult = $this->validateTiming($source, $minimumSeconds, $timingField);
        if (! $timingResult['valid']) {
            return [
                'is_bot' => true,
                'reason' => $timingResult['error'] ?? 'Submission submitted faster than human threshold.',
                'elapsed_seconds' => $timingResult['elapsed'],
            ];
        }

        return [
            'is_bot' => false,
            'reason' => null,
            'elapsed_seconds' => $timingResult['elapsed'],
        ];
    }

    /**
     * Inspect submission and throw BotSubmissionDetectedException if bot behavior detected.
     *
     * @param  Request|array<string, mixed>  $source
     *
     * @throws BotSubmissionDetectedException
     */
    public function verifyOrFail(
        Request|array $source,
        float $minimumSeconds = self::DEFAULT_MINIMUM_SECONDS,
        string $honeypotField = self::DEFAULT_HONEYPOT_FIELD,
        string $timingField = self::DEFAULT_TIMING_FIELD
    ): void {
        if (! $this->validateHoneypot($source, $honeypotField)) {
            throw BotSubmissionDetectedException::honeypotTriggered($honeypotField);
        }

        $timingResult = $this->validateTiming($source, $minimumSeconds, $timingField);
        if (! $timingResult['valid']) {
            if (isset($timingResult['error']) && str_contains($timingResult['error'], 'faster than')) {
                throw BotSubmissionDetectedException::tooFast($timingResult['elapsed'], $minimumSeconds);
            }
            throw BotSubmissionDetectedException::invalidToken();
        }
    }
}

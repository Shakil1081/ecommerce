<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Modules\Communication\DTOs\MailTestResultDto;
use App\Modules\Communication\Mail\TestMailNotification;
use App\Modules\Communication\Models\MailSetting;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTestEmailAction
{
    public function __construct(
        private readonly ConfigureDynamicMailerAction $configureMailer,
    ) {}

    /**
     * Test SMTP connection and dispatch verification test email.
     *
     * @param  array<string, mixed>|null  $temporaryCredentials
     */
    public function __invoke(
        string $recipientEmail,
        ?MailSetting $setting = null,
        ?array $temporaryCredentials = null,
    ): MailTestResultDto {
        if (! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return new MailTestResultDto(
                success: false,
                message: "Invalid recipient email address: {$recipientEmail}",
            );
        }

        $effectiveSetting = $setting;

        if ($temporaryCredentials !== null) {
            $effectiveSetting = new MailSetting([
                'driver' => (string) ($temporaryCredentials['driver'] ?? 'smtp'),
                'host' => (string) ($temporaryCredentials['host'] ?? ''),
                'port' => (int) ($temporaryCredentials['port'] ?? 587),
                'encryption' => ! empty($temporaryCredentials['encryption']) ? (string) $temporaryCredentials['encryption'] : null,
                'username' => (string) ($temporaryCredentials['username'] ?? ''),
                'password' => (string) ($temporaryCredentials['password'] ?? ''),
                'from_address' => (string) ($temporaryCredentials['from_address'] ?? 'noreply@example.com'),
                'from_name' => (string) ($temporaryCredentials['from_name'] ?? 'E-Commerce Platform'),
                'is_active' => true,
            ]);
        } elseif ($effectiveSetting === null) {
            $effectiveSetting = MailSetting::active()->first();
        }

        if (! $effectiveSetting || ! $effectiveSetting->isConfigured()) {
            return new MailTestResultDto(
                success: false,
                message: 'SMTP settings are incomplete or not configured.',
            );
        }

        // Dynamically apply configuration
        ($this->configureMailer)($effectiveSetting);

        $startTime = hrtime(true);

        try {
            Mail::to($recipientEmail)->send(new TestMailNotification(
                recipientEmail: $recipientEmail,
                timestampString: now()->toIso8601String(),
            ));

            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);

            $message = "SMTP connection verified. Test email delivered successfully to {$recipientEmail}.";

            if ($setting !== null && $setting->exists) {
                $setting->update([
                    'last_tested_at' => now(),
                    'last_test_status' => 'success',
                    'last_test_message' => $message,
                ]);
            }

            return new MailTestResultDto(
                success: true,
                message: $message,
                latencyMs: $latencyMs,
                details: [
                    'recipient' => $recipientEmail,
                    'host' => $effectiveSetting->host,
                    'port' => $effectiveSetting->port,
                ],
            );
        } catch (Throwable $e) {
            $latencyMs = (int) round((hrtime(true) - $startTime) / 1e6);
            $errorMessage = "SMTP handshake failed: {$e->getMessage()}";

            if ($setting !== null && $setting->exists) {
                $setting->update([
                    'last_tested_at' => now(),
                    'last_test_status' => 'failed',
                    'last_test_message' => $errorMessage,
                ]);
            }

            return new MailTestResultDto(
                success: false,
                message: $errorMessage,
                latencyMs: $latencyMs,
                details: [
                    'error' => $e->getMessage(),
                    'host' => $effectiveSetting->host,
                    'port' => $effectiveSetting->port,
                ],
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Communication\Providers;

use App\Modules\Communication\Actions\ConfigureDynamicMailerAction;
use App\Modules\Communication\Models\MailSetting;
use App\Modules\Communication\Services\SmsGatewayManager;
use App\Modules\Communication\Services\VoiceCallingManager;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class CommunicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConfigureDynamicMailerAction::class);
        $this->app->singleton(SmsGatewayManager::class);
        $this->app->singleton(VoiceCallingManager::class);
    }

    public function boot(): void
    {
        // Dynamically apply active database SMTP settings if configured
        try {
            if (! $this->app->runningInConsole() || $this->app->runningUnitTests()) {
                if (Schema::hasTable('mail_settings')) {
                    $setting = MailSetting::active()->first();
                    if ($setting && $setting->isConfigured()) {
                        ($this->app->make(ConfigureDynamicMailerAction::class))($setting);
                    }
                }
            }
        } catch (Throwable) {
            // Silently fall back to default environment mailer before migration
        }
    }
}

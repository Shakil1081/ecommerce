<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Modules\Communication\Models\MailSetting;
use Illuminate\Support\Facades\Config;

class ConfigureDynamicMailerAction
{
    /**
     * Dynamically inject mail settings into Laravel runtime configuration.
     */
    public function __invoke(?MailSetting $setting = null): bool
    {
        $setting ??= MailSetting::active()->first();

        if (! $setting || ! $setting->isConfigured()) {
            return false;
        }

        $driver = $setting->driver ?: 'smtp';

        Config::set('mail.default', $driver);
        Config::set("mail.mailers.{$driver}.transport", $driver);
        Config::set("mail.mailers.{$driver}.host", $setting->host);
        Config::set("mail.mailers.{$driver}.port", $setting->port);
        Config::set("mail.mailers.{$driver}.encryption", $setting->encryption ?: null);
        Config::set("mail.mailers.{$driver}.username", $setting->username);
        Config::set("mail.mailers.{$driver}.password", $setting->password);

        if (! empty($setting->from_address)) {
            Config::set('mail.from.address', $setting->from_address);
        }

        if (! empty($setting->from_name)) {
            Config::set('mail.from.name', $setting->from_name);
        }

        if (app()->bound('mail.manager')) {
            $manager = app('mail.manager');
            if (is_object($manager) && method_exists($manager, 'forgetMailers')) {
                $manager->forgetMailers();
            }
        }

        return true;
    }
}

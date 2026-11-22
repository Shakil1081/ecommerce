<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Common\Models\BotSecuritySetting;
use Illuminate\Database\Seeder;

class BotSecuritySettingSeeder extends Seeder
{
    /**
     * Seed the first CAPTCHA configuration from config/security.php.
     *
     * In production without real RECAPTCHA_* keys nothing is seeded (Google's test keys always pass);
     * configure the keys in Admin > Staff & Security > Spam Protection instead.
     */
    public function run(): void
    {
        $keys = BotSecuritySetting::initialKeys();

        if ($keys === null) {
            $this->command?->warn('No RECAPTCHA_SITE_KEY / RECAPTCHA_SECRET_KEY set: CAPTCHA not seeded. Configure it in Admin > Spam Protection.');

            return;
        }

        BotSecuritySetting::firstOrCreate(
            ['id' => 1],
            [
                'provider' => $keys['provider'],
                'site_key' => $keys['site_key'],
                'secret_key' => $keys['secret_key'],
                'is_active' => true,
                'score_threshold' => 0.50,
                'pages_enabled' => [
                    BotSecuritySetting::PAGE_LOGIN,
                    BotSecuritySetting::PAGE_CHECKOUT,
                    BotSecuritySetting::PAGE_REGISTER,
                ],
            ]
        );
    }
}

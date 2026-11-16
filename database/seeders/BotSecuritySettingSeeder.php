<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Common\Models\BotSecuritySetting;
use Illuminate\Database\Seeder;

class BotSecuritySettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $siteKey = (string) env('RECAPTCHA_SITE_KEY', env('GOOGLE_RECAPTCHA_SITE_KEY', '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI'));
        $secretKey = (string) env('RECAPTCHA_SECRET_KEY', env('GOOGLE_RECAPTCHA_SECRET_KEY', '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe'));
        $provider = (string) env('RECAPTCHA_PROVIDER', BotSecuritySetting::PROVIDER_RECAPTCHA_V2);

        BotSecuritySetting::firstOrCreate(
            ['id' => 1],
            [
                'provider' => $provider,
                'site_key' => $siteKey,
                'secret_key' => $secretKey,
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

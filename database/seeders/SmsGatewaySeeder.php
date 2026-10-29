<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Communication\Models\SmsGatewaySetting;
use Illuminate\Database\Seeder;

class SmsGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $gateways = [
            [
                'code' => SmsGatewaySetting::CODE_GREENWEB,
                'name' => 'Greenweb SMS',
                'is_active' => true,
                'is_sandbox' => false,
                'sender_id' => 'GREENWEB',
                'sort_order' => 1,
                'credentials' => [
                    'token' => 'sample_greenweb_token_123',
                ],
            ],
            [
                'code' => SmsGatewaySetting::CODE_REVE,
                'name' => 'Reve Systems SMS',
                'is_active' => false,
                'is_sandbox' => false,
                'sender_id' => 'REVESMS',
                'sort_order' => 2,
                'credentials' => [
                    'api_key' => 'sample_reve_api_key',
                    'secret_key' => 'sample_reve_secret_key',
                ],
            ],
            [
                'code' => SmsGatewaySetting::CODE_TWILIO,
                'name' => 'Twilio SMS Global',
                'is_active' => false,
                'is_sandbox' => true,
                'sender_id' => '+18005550199',
                'sort_order' => 3,
                'credentials' => [
                    'account_sid' => 'ACsample_twilio_sid_9999',
                    'auth_token' => 'sample_twilio_auth_token_8888',
                    'from_number' => '+18005550199',
                ],
            ],
        ];

        foreach ($gateways as $gateway) {
            SmsGatewaySetting::firstOrCreate(
                ['code' => $gateway['code']],
                $gateway
            );
        }
    }
}

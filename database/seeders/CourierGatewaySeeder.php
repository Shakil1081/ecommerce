<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Logistics\Models\CourierGatewaySetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CourierGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $couriers = [
            [
                'code' => CourierGatewaySetting::CODE_STEADFAST,
                'name' => 'Steadfast Courier',
                'is_active' => false,
                'is_sandbox' => true,
                'base_url' => 'https://portal.packzy.com/api/v1',
                'supported_features' => ['booking', 'tracking', 'balance', 'rates', 'webhook'],
                'sort_order' => 1,
                'credentials' => [
                    'api_key' => '',
                    'secret_key' => '',
                ],
            ],
            [
                'code' => CourierGatewaySetting::CODE_PATHAO,
                'name' => 'Pathao Courier',
                'is_active' => false,
                'is_sandbox' => true,
                'base_url' => 'https://courier-api-sandbox.pathao.com',
                'supported_features' => ['booking', 'tracking', 'rates', 'stores', 'webhook'],
                'sort_order' => 2,
                'credentials' => [
                    'client_id' => '',
                    'client_secret' => '',
                    'username' => '',
                    'password' => '',
                    'store_id' => '',
                ],
            ],
            [
                'code' => CourierGatewaySetting::CODE_REDX,
                'name' => 'RedX Delivery',
                'is_active' => false,
                'is_sandbox' => true,
                'base_url' => 'https://openapi.redx.com.bd/v1.0.0-sandbox',
                'supported_features' => ['booking', 'tracking', 'rates', 'webhook'],
                'sort_order' => 3,
                'credentials' => [
                    'token' => '',
                ],
            ],
            [
                'code' => CourierGatewaySetting::CODE_PAPERFLY,
                'name' => 'Paperfly Courier',
                'is_active' => false,
                'is_sandbox' => true,
                'base_url' => 'https://api.paperfly.com.bd',
                'supported_features' => ['booking', 'tracking', 'webhook'],
                'sort_order' => 4,
                'credentials' => [
                    'username' => '',
                    'password' => '',
                    'key' => '',
                ],
            ],
            [
                'code' => CourierGatewaySetting::CODE_SUNDARBAN,
                'name' => 'Sundarban Courier Service',
                'is_active' => false,
                'is_sandbox' => true,
                'base_url' => 'https://api.sundarbancourier.com',
                'supported_features' => ['booking', 'tracking'],
                'sort_order' => 5,
                'credentials' => [
                    'api_key' => '',
                    'client_id' => '',
                ],
            ],
        ];

        foreach ($couriers as $courier) {
            CourierGatewaySetting::updateOrCreate(
                ['code' => $courier['code']],
                [
                    'ulid' => (string) Str::ulid(),
                    'name' => $courier['name'],
                    'is_active' => $courier['is_active'],
                    'is_sandbox' => $courier['is_sandbox'],
                    'base_url' => $courier['base_url'],
                    'supported_features' => $courier['supported_features'],
                    'sort_order' => $courier['sort_order'],
                    'credentials' => $courier['credentials'],
                ]
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\AI\Models\AiModelSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AiModelSettingSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'provider' => AiModelSetting::PROVIDER_GEMINI,
                'name' => 'Google Gemini AI',
                'is_active' => false,
                'default_model' => 'gemini-1.5-flash',
                'temperature' => 0.70,
                'max_tokens' => 2048,
                'assigned_features' => [
                    AiModelSetting::FEATURE_CATALOG_ENRICHMENT,
                    AiModelSetting::FEATURE_SMART_RESTOCKING,
                    AiModelSetting::FEATURE_CUSTOMER_SUPPORT,
                    AiModelSetting::FEATURE_TAX_ANOMALY,
                ],
                'sort_order' => 1,
                'credentials' => [
                    'api_key' => '',
                ],
            ],
            [
                'provider' => AiModelSetting::PROVIDER_OPENAI,
                'name' => 'OpenAI GPT-4o',
                'is_active' => false,
                'default_model' => 'gpt-4o-mini',
                'temperature' => 0.70,
                'max_tokens' => 2048,
                'assigned_features' => [],
                'sort_order' => 2,
                'credentials' => [
                    'api_key' => '',
                    'endpoint' => 'https://api.openai.com/v1/chat/completions',
                ],
            ],
            [
                'provider' => AiModelSetting::PROVIDER_CLAUDE,
                'name' => 'Anthropic Claude',
                'is_active' => false,
                'default_model' => 'claude-3-5-sonnet-20241022',
                'temperature' => 0.70,
                'max_tokens' => 2048,
                'assigned_features' => [],
                'sort_order' => 3,
                'credentials' => [
                    'api_key' => '',
                    'endpoint' => 'https://api.anthropic.com/v1/messages',
                ],
            ],
            [
                'provider' => AiModelSetting::PROVIDER_OLLAMA,
                'name' => 'Ollama (Local LLM)',
                'is_active' => false,
                'default_model' => 'llama3.2',
                'temperature' => 0.70,
                'max_tokens' => 2048,
                'assigned_features' => [],
                'sort_order' => 4,
                'credentials' => [
                    'endpoint' => 'http://localhost:11434',
                ],
            ],
        ];

        foreach ($providers as $item) {
            AiModelSetting::firstOrCreate(
                ['provider' => $item['provider']],
                array_merge($item, ['ulid' => (string) Str::ulid()])
            );
        }
    }
}


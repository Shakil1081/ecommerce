<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\AI\Actions\RecordAiUsageAction;
use App\Modules\AI\Actions\TestAiConnectionAction;
use App\Modules\AI\DTOs\AiPromptRequestDto;
use App\Modules\AI\Models\AiModelSetting;
use App\Modules\AI\Services\AiManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AiIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'ai_admin@example.com',
        ]);
    }

    public function test_ai_model_setting_stores_encrypted_credentials_and_masks_secrets(): void
    {
        $setting = AiModelSetting::create([
            'provider' => AiModelSetting::PROVIDER_GEMINI,
            'name' => 'Google Gemini Flash',
            'is_active' => true,
            'default_model' => 'gemini-1.5-flash',
            'temperature' => 0.70,
            'max_tokens' => 2048,
            'assigned_features' => ['catalog_enrichment', 'smart_restocking'],
            'credentials' => [
                'api_key' => 'AIzaSyA1234567890abcdefGhIjKlMnOpQrStU',
            ],
        ]);

        $this->assertNotEmpty($setting->ulid);
        $this->assertTrue($setting->is_active);
        $this->assertTrue($setting->handlesFeature('catalog_enrichment'));
        $this->assertTrue($setting->handlesFeature('smart_restocking'));
        $this->assertFalse($setting->handlesFeature('dispute_assistant'));

        // Verify credentials encrypted at database level
        $rawCredentials = DB::table('ai_model_settings')
            ->where('id', $setting->id)
            ->value('credentials');

        $this->assertNotSame('AIzaSyA1234567890abcdefGhIjKlMnOpQrStU', $rawCredentials);
        $this->assertSame('AIzaSyA1234567890abcdefGhIjKlMnOpQrStU', $setting->credentials['api_key']);

        // Verify masking
        $masked = $setting->masked_credentials;
        $this->assertStringContainsString('••••••••', $masked['api_key']);
        $this->assertStringEndsWith('rStU', $masked['api_key']);
    }

    public function test_ai_manager_dispatches_prompt_via_gemini_and_records_usage(): void
    {
        $setting = AiModelSetting::create([
            'provider' => AiModelSetting::PROVIDER_GEMINI,
            'name' => 'Google Gemini Flash',
            'is_active' => true,
            'default_model' => 'gemini-1.5-flash',
            'assigned_features' => ['catalog_enrichment'],
            'credentials' => [
                'api_key' => 'fake_gemini_key_12345',
            ],
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'High-performance noise cancelling headphones with 30-hour battery life.'],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 120,
                    'candidatesTokenCount' => 45,
                    'totalTokenCount' => 165,
                ],
            ], 200),
        ]);

        /** @var AiManager $aiManager */
        $aiManager = app(AiManager::class);

        $request = new AiPromptRequestDto(
            prompt: 'Generate an SEO description for Sony headphones.',
            systemPrompt: 'You are an e-commerce catalog SEO specialist.',
            feature: 'catalog_enrichment',
            userId: $this->admin->id
        );

        $response = $aiManager->prompt($request);

        $this->assertSame(AiModelSetting::PROVIDER_GEMINI, $response->provider);
        $this->assertSame('gemini-1.5-flash', $response->model);
        $this->assertStringContainsString('noise cancelling', $response->content);
        $this->assertSame(120, $response->promptTokens);
        $this->assertSame(45, $response->completionTokens);
        $this->assertSame(165, $response->totalTokens);

        // Verify usage log persisted
        $this->assertDatabaseHas('ai_usage_logs', [
            'provider' => AiModelSetting::PROVIDER_GEMINI,
            'feature' => 'catalog_enrichment',
            'prompt_tokens' => 120,
            'completion_tokens' => 45,
            'total_tokens' => 165,
            'status' => 'success',
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_ai_manager_dispatches_prompt_via_openai_and_records_usage(): void
    {
        $setting = AiModelSetting::create([
            'provider' => AiModelSetting::PROVIDER_OPENAI,
            'name' => 'OpenAI GPT-4o-mini',
            'is_active' => true,
            'default_model' => 'gpt-4o-mini',
            'assigned_features' => ['smart_restocking'],
            'credentials' => [
                'api_key' => 'fake_openai_key_98765',
                'endpoint' => 'https://api.openai.com/v1/chat/completions',
            ],
        ]);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Reorder 150 units based on 30-day velocity of 12 units/day.',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 200,
                    'completion_tokens' => 30,
                    'total_tokens' => 230,
                ],
            ], 200),
        ]);

        /** @var AiManager $aiManager */
        $aiManager = app(AiManager::class);

        $request = new AiPromptRequestDto(
            prompt: 'Calculate suggested restock for SKU-XYZ',
            feature: 'smart_restocking',
            userId: $this->admin->id
        );

        $response = $aiManager->prompt($request);

        $this->assertSame(AiModelSetting::PROVIDER_OPENAI, $response->provider);
        $this->assertSame('gpt-4o-mini', $response->model);
        $this->assertStringContainsString('Reorder 150 units', $response->content);
        $this->assertSame(200, $response->promptTokens);
        $this->assertSame(30, $response->completionTokens);

        $this->assertDatabaseHas('ai_usage_logs', [
            'provider' => AiModelSetting::PROVIDER_OPENAI,
            'feature' => 'smart_restocking',
            'total_tokens' => 230,
            'status' => 'success',
        ]);
    }

    public function test_ai_manager_dispatches_prompt_via_claude(): void
    {
        $setting = AiModelSetting::create([
            'provider' => AiModelSetting::PROVIDER_CLAUDE,
            'name' => 'Claude 3.5 Sonnet',
            'is_active' => true,
            'default_model' => 'claude-3-5-sonnet-20241022',
            'assigned_features' => ['customer_support'],
            'credentials' => [
                'api_key' => 'fake_claude_key_112233',
                'endpoint' => 'https://api.anthropic.com/v1/messages',
            ],
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    [
                        'text' => 'Dear Customer, We have approved your return request under warranty policy.',
                    ],
                ],
                'usage' => [
                    'input_tokens' => 150,
                    'output_tokens' => 50,
                ],
            ], 200),
        ]);

        /** @var AiManager $aiManager */
        $aiManager = app(AiManager::class);

        $request = new AiPromptRequestDto(
            prompt: 'Draft an RMA acceptance message for order ORD-1234',
            feature: 'customer_support',
            userId: $this->admin->id
        );

        $response = $aiManager->prompt($request);

        $this->assertSame(AiModelSetting::PROVIDER_CLAUDE, $response->provider);
        $this->assertStringContainsString('approved your return', $response->content);
        $this->assertSame(150, $response->promptTokens);
        $this->assertSame(50, $response->completionTokens);
    }

    public function test_ai_manager_dispatches_prompt_via_ollama(): void
    {
        $setting = AiModelSetting::create([
            'provider' => AiModelSetting::PROVIDER_OLLAMA,
            'name' => 'Ollama Local Llama 3',
            'is_active' => true,
            'default_model' => 'llama3.2',
            'assigned_features' => ['tax_anomaly'],
            'credentials' => [
                'endpoint' => 'http://localhost:11434',
            ],
        ]);

        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'response' => 'No tax anomaly detected. Output and input ratios match monthly baseline.',
                'prompt_eval_count' => 80,
                'eval_count' => 25,
            ], 200),
        ]);

        /** @var AiManager $aiManager */
        $aiManager = app(AiManager::class);

        $request = new AiPromptRequestDto(
            prompt: 'Analyze Mushak 6.7 variance for tax period 2026-10',
            feature: 'tax_anomaly',
            userId: $this->admin->id
        );

        $response = $aiManager->prompt($request);

        $this->assertSame(AiModelSetting::PROVIDER_OLLAMA, $response->provider);
        $this->assertStringContainsString('No tax anomaly detected', $response->content);
        $this->assertSame(80, $response->promptTokens);
        $this->assertSame(25, $response->completionTokens);
    }

    public function test_test_ai_connection_action_succeeds_with_valid_handshake(): void
    {
        $setting = AiModelSetting::create([
            'provider' => AiModelSetting::PROVIDER_GEMINI,
            'name' => 'Google Gemini Flash',
            'is_active' => true,
            'default_model' => 'gemini-1.5-flash',
            'credentials' => [
                'api_key' => 'valid_test_key_001',
            ],
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'AI Connection Verified.'],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 10,
                    'candidatesTokenCount' => 4,
                ],
            ], 200),
        ]);

        $testAction = app(TestAiConnectionAction::class);
        $result = $testAction($setting);

        $this->assertTrue($result->success);
        $this->assertSame(AiModelSetting::PROVIDER_GEMINI, $result->provider);
        $this->assertStringContainsString('Successfully connected', $result->message);
        $this->assertSame('AI Connection Verified.', $result->responseSample);
    }

    public function test_test_ai_connection_action_handles_endpoint_error_gracefully(): void
    {
        $setting = AiModelSetting::create([
            'provider' => AiModelSetting::PROVIDER_OPENAI,
            'name' => 'OpenAI GPT-4o',
            'is_active' => true,
            'default_model' => 'gpt-4o',
            'credentials' => [
                'api_key' => 'invalid_key',
            ],
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'Incorrect API key provided: invalid_key',
                ],
            ], 401),
        ]);

        $testAction = app(TestAiConnectionAction::class);
        $result = $testAction($setting);

        $this->assertFalse($result->success);
        $this->assertSame(AiModelSetting::PROVIDER_OPENAI, $result->provider);
        $this->assertStringContainsString('Incorrect API key provided', $result->message);
    }

    public function test_record_ai_usage_action_calculates_cost_estimates_accurately(): void
    {
        $action = app(RecordAiUsageAction::class);

        $log = $action([
            'provider' => AiModelSetting::PROVIDER_OPENAI,
            'model' => 'gpt-4o-mini',
            'feature' => 'catalog_enrichment',
            'prompt_tokens' => 10000,
            'completion_tokens' => 5000,
            'latency_ms' => 420,
            'status' => 'success',
            'created_by_user_id' => $this->admin->id,
        ]);

        // Prompt: 10,000 * 0.00000015 = 0.0015 USD
        // Output: 5,000 * 0.0000006 = 0.003 USD
        // Total USD = 0.0045 USD
        // BDT @ 120 = 0.54 BDT
        $this->assertEquals(0.0045, (float) $log->estimated_cost_usd);
        $this->assertEquals(0.5400, (float) $log->estimated_cost_bdt);
        $this->assertSame(15000, $log->total_tokens);
    }
}

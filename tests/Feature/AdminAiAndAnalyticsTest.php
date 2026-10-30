<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Admin\Models\AuditLog;
use App\Modules\AI\Models\AiModelSetting;
use App\Modules\AI\Models\AiUsageLog;
use Database\Seeders\AiModelSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AdminAiAndAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_admin_can_view_ai_settings_page(): void
    {
        $this->seed(AiModelSettingSeeder::class);

        AiUsageLog::create([
            'user_id' => $this->admin->id,
            'provider' => 'gemini',
            'model' => 'gemini-1.5-pro',
            'feature' => 'catalog_seo',
            'prompt_tokens' => 200,
            'completion_tokens' => 150,
            'total_tokens' => 350,
            'estimated_cost_usd' => 0.0005,
            'estimated_cost_bdt' => 0.06,
            'latency_ms' => 450,
            'status' => 'success',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/ai-settings');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AI/AiSettings')
            ->has('settings', 4)
            ->has('usageLogs', 1)
            ->has('stats')
            ->where('stats.total_requests', 1)
            ->where('stats.total_tokens', 350)
        );
    }

    public function test_admin_can_update_ai_provider_settings(): void
    {
        $setting = AiModelSetting::create([
            'provider' => 'gemini',
            'name' => 'Google Gemini AI',
            'default_model' => 'gemini-1.5-pro',
            'is_active' => false,
            'temperature' => 0.70,
            'max_tokens' => 2048,
            'credentials' => ['api_key' => 'old_secret_key'],
            'assigned_features' => ['catalog_seo'],
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->admin)->post("/admin/ai-settings/{$setting->id}", [
            'is_active' => true,
            'default_model' => 'gemini-2.0-flash',
            'temperature' => 0.35,
            'max_tokens' => 4096,
            'assigned_features' => ['catalog_seo', 'smart_restocking'],
            'credentials' => [
                'api_key' => 'AIzaSyNewGeminiKey123',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setting->refresh();
        $this->assertTrue($setting->is_active);
        $this->assertSame('gemini-2.0-flash', $setting->default_model);
        $this->assertEquals(0.35, $setting->temperature);
        $this->assertSame(4096, $setting->max_tokens);
        $this->assertContains('smart_restocking', $setting->assigned_features);
        $this->assertSame('AIzaSyNewGeminiKey123', $setting->credentials['api_key']);
    }

    public function test_admin_can_test_ai_connection(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'AI Connection OK!'],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 10,
                    'candidatesTokenCount' => 8,
                    'totalTokenCount' => 18,
                ],
            ], 200),
        ]);

        $setting = AiModelSetting::create([
            'provider' => 'gemini',
            'name' => 'Google Gemini AI',
            'default_model' => 'gemini-1.5-flash',
            'is_active' => true,
            'temperature' => 0.20,
            'max_tokens' => 100,
            'credentials' => ['api_key' => 'fake_gemini_api_key'],
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/admin/ai-settings/test', [
            'setting_id' => $setting->id,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'provider' => 'gemini',
            'model' => 'gemini-1.5-flash',
        ]);
    }

    public function test_admin_can_view_trading_pnl_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/analytics/trading-pnl');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Analytics/TradingPnL')
            ->has('pnl')
            ->has('pnl.grossSalesTurnoverBdt')
            ->has('pnl.netOperatingProfitBdt')
            ->has('pnl.categoryBreakdown')
            ->has('pnl.topPerformingSkus')
            ->has('filters')
            ->where('filters.period', 'this_month')
        );
    }

    public function test_admin_can_filter_trading_pnl_by_custom_range(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/analytics/trading-pnl?period=custom&start_date=2026-01-01&end_date=2026-01-31');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Analytics/TradingPnL')
            ->has('pnl')
            ->where('filters.period', 'custom')
            ->where('filters.start_date', '2026-01-01')
            ->where('filters.end_date', '2026-01-31')
        );
    }

    public function test_admin_can_view_audit_logs_index(): void
    {
        AuditLog::create([
            'user_id' => $this->admin->id,
            'user_name' => $this->admin->name,
            'user_email' => $this->admin->email,
            'event' => AuditLog::EVENT_PRICE_UPDATED,
            'auditable_type' => 'App\Modules\Catalog\Models\Product',
            'auditable_id' => 'prod_123',
            'description' => 'Updated retail price from 1500 to 1800 BDT',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/audit-logs');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Audit/AuditLogIndex')
            ->has('logs.data', 1)
            ->has('eventsList')
            ->has('filters')
        );
    }

    public function test_admin_can_filter_audit_logs_by_event_and_search(): void
    {
        AuditLog::create([
            'user_id' => $this->admin->id,
            'user_name' => 'Admin User',
            'user_email' => 'admin@test.com',
            'event' => AuditLog::EVENT_PRICE_UPDATED,
            'auditable_type' => 'App\Modules\Catalog\Models\Product',
            'auditable_id' => 'prod_abc',
            'description' => 'Adjusted price for Wireless Bluetooth Headset',
            'ip_address' => '103.20.10.5',
        ]);

        AuditLog::create([
            'user_id' => $this->admin->id,
            'user_name' => 'Staff User',
            'user_email' => 'staff@test.com',
            'event' => AuditLog::EVENT_STOCK_ADJUSTED,
            'auditable_type' => 'App\Modules\Inventory\Models\StockBatch',
            'auditable_id' => 'batch_xyz',
            'description' => 'Damaged inventory written off',
            'ip_address' => '103.20.10.8',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/audit-logs?event=price_updated&search=Wireless');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Audit/AuditLogIndex')
            ->has('logs.data', 1)
            ->where('logs.data.0.event', AuditLog::EVENT_PRICE_UPDATED)
            ->where('logs.data.0.auditable_id', 'prod_abc')
        );
    }
}

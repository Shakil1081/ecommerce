<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Common\Models\BotSecuritySetting;
use App\Modules\Common\Services\BotCaptchaVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class SecuritySettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_admin_can_view_bot_security_settings_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/settings/bot-security');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings/BotSecuritySettings')
            ->has('setting')
            ->has('availablePages')
            ->has('availableProviders')
        );
    }

    public function test_admin_can_save_bot_security_settings(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/settings/bot-security', [
            'provider' => 'recaptcha_v3',
            'site_key' => '6LeTestSiteKey123',
            'secret_key' => 'super_secret_google_key',
            'is_active' => true,
            'score_threshold' => 0.60,
            'pages_enabled' => ['checkout', 'login', 'register'],
        ]);

        $response->assertRedirect();

        $setting = BotSecuritySetting::first();
        $this->assertNotNull($setting);
        $this->assertEquals('recaptcha_v3', $setting->provider);
        $this->assertEquals('6LeTestSiteKey123', $setting->site_key);
        $this->assertTrue($setting->is_active);
        $this->assertEquals('0.60', $setting->score_threshold);
        $this->assertEquals(['checkout', 'login', 'register'], $setting->pages_enabled);

        // Assert secret key is encrypted at rest in the database
        $rawSecret = DB::table('bot_security_settings')->where('id', $setting->id)->value('secret_key');
        $this->assertNotEquals('super_secret_google_key', $rawSecret);
        $this->assertEquals('super_secret_google_key', Crypt::decryptString((string) $rawSecret));
        $this->assertEquals('super_secret_google_key', $setting->secret_key);
    }

    public function test_admin_can_test_google_recaptcha_connection(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'], // Proves credentials were received
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->postJson('/admin/settings/bot-security/test', [
            'provider' => 'recaptcha_v3',
            'site_key' => 'test_site_key',
            'secret_key' => 'test_secret_key',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_admin_can_test_cloudflare_turnstile_connection(): void
    {
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->postJson('/admin/settings/bot-security/test', [
            'provider' => 'turnstile',
            'site_key' => 'turnstile_site_key',
            'secret_key' => 'turnstile_secret_key',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_bot_verification_service_approves_recaptcha_v3_human_score(): void
    {
        $setting = BotSecuritySetting::create([
            'provider' => BotSecuritySetting::PROVIDER_RECAPTCHA_V3,
            'site_key' => 'site_key_123',
            'secret_key' => 'secret_key_123',
            'is_active' => true,
            'score_threshold' => 0.50,
            'pages_enabled' => ['checkout'],
        ]);

        $service = app(BotCaptchaVerificationService::class);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.85,
                'hostname' => 'localhost',
            ], 200),
        ]);

        $res = $service->verifyToken('human_token', setting: $setting);
        $this->assertTrue($res->success);
        $this->assertEquals(0.85, $res->score);
    }

    public function test_bot_verification_service_rejects_recaptcha_v3_low_score(): void
    {
        $setting = BotSecuritySetting::create([
            'provider' => BotSecuritySetting::PROVIDER_RECAPTCHA_V3,
            'site_key' => 'site_key_123',
            'secret_key' => 'secret_key_123',
            'is_active' => true,
            'score_threshold' => 0.50,
            'pages_enabled' => ['checkout'],
        ]);

        $service = app(BotCaptchaVerificationService::class);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.20,
                'hostname' => 'localhost',
            ], 200),
        ]);

        $res = $service->verifyToken('bot_token', setting: $setting);
        $this->assertFalse($res->success);
        $this->assertEquals(0.20, $res->score);
        $this->assertStringContainsString('below the required security threshold', (string) $res->errorMessage);
    }

    public function test_bot_verification_service_verifies_turnstile_token(): void
    {
        $setting = BotSecuritySetting::create([
            'provider' => BotSecuritySetting::PROVIDER_TURNSTILE,
            'site_key' => '0x4AAAAAAATurnstile',
            'secret_key' => '0x4AAAAAAASecTurnstile',
            'is_active' => true,
            'score_threshold' => 0.50,
            'pages_enabled' => ['checkout', 'login'],
        ]);

        $service = app(BotCaptchaVerificationService::class);

        // Successful Turnstile
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'ecommerce.local',
            ], 200),
        ]);

        $res = $service->verifyToken('valid_turnstile_token', setting: $setting);
        $this->assertTrue($res->success);
        $this->assertEquals('turnstile', $res->provider);
    }

    public function test_bot_verification_service_bypasses_when_inactive(): void
    {
        $setting = BotSecuritySetting::create([
            'provider' => BotSecuritySetting::PROVIDER_RECAPTCHA_V3,
            'site_key' => 'site_key',
            'secret_key' => 'secret_key',
            'is_active' => false, // Inactive
            'score_threshold' => 0.50,
            'pages_enabled' => ['checkout'],
        ]);

        $service = app(BotCaptchaVerificationService::class);
        $res = $service->verifyToken('any_token', setting: $setting);

        $this->assertTrue($res->success);
    }

    public function test_is_page_protected_helper_accurately_checks_page_status(): void
    {
        $setting = BotSecuritySetting::create([
            'provider' => BotSecuritySetting::PROVIDER_RECAPTCHA_V3,
            'site_key' => 'site_key',
            'secret_key' => 'secret_key',
            'is_active' => true,
            'score_threshold' => 0.50,
            'pages_enabled' => ['checkout', 'login'],
        ]);

        $this->assertTrue($setting->isPageProtected('checkout'));
        $this->assertTrue($setting->isPageProtected('login'));
        $this->assertFalse($setting->isPageProtected('reviews'));
        $this->assertFalse($setting->isPageProtected('contact'));

        // When inactive, all pages return false
        $setting->update(['is_active' => false]);
        $this->assertFalse($setting->isPageProtected('checkout'));
    }
}

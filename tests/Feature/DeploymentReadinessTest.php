<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Admin\Models\AuditLog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Common\Models\BotSecuritySetting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeploymentReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_never_falls_back_to_google_test_captcha_keys(): void
    {
        config(['security.captcha.site_key' => null, 'security.captcha.secret_key' => null]);
        $this->app['env'] = 'production';

        $this->assertNull(BotSecuritySetting::initialKeys());
        $this->assertNull(BotSecuritySetting::getActive());
        $this->assertSame(0, BotSecuritySetting::query()->count());
    }

    public function test_configured_captcha_keys_are_used_in_production(): void
    {
        config(['security.captcha.site_key' => 'real-site', 'security.captcha.secret_key' => 'real-secret']);
        $this->app['env'] = 'production';

        $this->assertSame('real-site', BotSecuritySetting::initialKeys()['site_key'] ?? null);
    }

    public function test_production_seed_keeps_demo_products_but_not_fake_audit_logs(): void
    {
        config(['security.captcha.site_key' => null, 'security.captcha.secret_key' => null]);
        $this->app['env'] = 'production';

        // Same command as DEPLOYMENT.md (production asks for confirmation without --force)
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertGreaterThan(0, Product::query()->where('is_published', true)->count());
        $this->assertSame(0, AuditLog::query()->count());
        $this->assertTrue(User::query()->where('email', 'admin@ecommerce.test')->exists());
    }

    public function test_background_jobs_are_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())
            ->map(fn (Event $event): string => (string) $event->command)
            ->implode("\n");

        $this->assertStringContainsString('inventory:release-expired-reservations', $commands);
        $this->assertStringContainsString('cart:process-recovery', $commands);
    }
}

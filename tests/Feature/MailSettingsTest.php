<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Communication\Actions\ConfigureDynamicMailerAction;
use App\Modules\Communication\Actions\SendTestEmailAction;
use App\Modules\Communication\Mail\TestMailNotification;
use App\Modules\Communication\Models\MailSetting;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_setting_can_be_persisted_with_encrypted_password(): void
    {
        $setting = MailSetting::create([
            'driver' => 'smtp',
            'host' => 'smtp.sendgrid.net',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'apikey',
            'password' => 'SG.my_super_secret_smtp_token_1234',
            'from_address' => 'orders@ecommerce.com.bd',
            'from_name' => 'Commerce BD Store',
            'is_active' => true,
        ]);

        $this->assertNotEmpty($setting->ulid);
        $this->assertEquals('SG.my_super_secret_smtp_token_1234', $setting->password);
        $this->assertEquals('••••••••1234', $setting->getMaskedPassword());

        // Verify encrypted at rest in raw SQL
        $raw = DB::table('mail_settings')->where('id', $setting->id)->first();
        $this->assertNotEquals('SG.my_super_secret_smtp_token_1234', $raw->password);
        $this->assertStringStartsWith('eyJ', $raw->password);
    }

    public function test_is_configured_and_active_scope(): void
    {
        $incomplete = MailSetting::create([
            'driver' => 'smtp',
            'host' => null,
            'port' => 587,
            'username' => null,
            'is_active' => false,
        ]);

        $this->assertFalse($incomplete->isConfigured());

        $complete = MailSetting::create([
            'driver' => 'smtp',
            'host' => 'smtp.mailtrap.io',
            'port' => 2525,
            'username' => 'test_user',
            'password' => 'test_pass',
            'from_address' => 'test@ecommerce.bd',
            'from_name' => 'Test Store',
            'is_active' => true,
        ]);

        $this->assertTrue($complete->isConfigured());

        $activeSettings = MailSetting::active()->get();
        $this->assertCount(1, $activeSettings);
        $this->assertEquals($complete->id, $activeSettings->first()->id);
    }

    public function test_configure_dynamic_mailer_action_applies_config(): void
    {
        $setting = MailSetting::create([
            'driver' => 'smtp',
            'host' => 'live.smtp.mailprovider.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'merchant_user',
            'password' => 'merchant_secret_99',
            'from_address' => 'support@ecommerce.com.bd',
            'from_name' => 'Online Support Team',
            'is_active' => true,
        ]);

        $action = app(ConfigureDynamicMailerAction::class);
        $configured = $action($setting);

        $this->assertTrue($configured);
        $this->assertEquals('smtp', Config::get('mail.default'));
        $this->assertEquals('live.smtp.mailprovider.com', Config::get('mail.mailers.smtp.host'));
        $this->assertEquals(465, Config::get('mail.mailers.smtp.port'));
        $this->assertEquals('ssl', Config::get('mail.mailers.smtp.encryption'));
        $this->assertEquals('merchant_user', Config::get('mail.mailers.smtp.username'));
        $this->assertEquals('merchant_secret_99', Config::get('mail.mailers.smtp.password'));
        $this->assertEquals('support@ecommerce.com.bd', Config::get('mail.from.address'));
        $this->assertEquals('Online Support Team', Config::get('mail.from.name'));
    }

    public function test_send_test_email_action_success(): void
    {
        Mail::fake();

        $setting = MailSetting::create([
            'driver' => 'smtp',
            'host' => 'smtp.mailtrap.io',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'test_user',
            'password' => 'test_password',
            'from_address' => 'noreply@ecommerce.bd',
            'from_name' => 'E-Commerce Store',
            'is_active' => true,
        ]);

        $action = app(SendTestEmailAction::class);
        $result = $action('admin@example.com', $setting);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('verified', $result->message);
        $this->assertNotNull($result->latencyMs);

        Mail::assertSent(TestMailNotification::class, function (TestMailNotification $mail) {
            return $mail->recipientEmail === 'admin@example.com';
        });

        $setting->refresh();
        $this->assertEquals('success', $setting->last_test_status);
        $this->assertNotNull($setting->last_tested_at);
        $this->assertStringContainsString('admin@example.com', $setting->last_test_message);
    }

    public function test_send_test_email_action_fails_on_invalid_email(): void
    {
        $action = app(SendTestEmailAction::class);
        $result = $action('not-a-valid-email');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid recipient', $result->message);
    }

    public function test_send_test_email_action_fails_when_settings_incomplete(): void
    {
        $incomplete = MailSetting::create([
            'driver' => 'smtp',
            'host' => '',
            'port' => 587,
            'username' => '',
            'is_active' => true,
        ]);

        $action = app(SendTestEmailAction::class);
        $result = $action('admin@example.com', $incomplete);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('incomplete', $result->message);
    }

    public function test_send_test_email_action_handles_smtp_exception(): void
    {
        $setting = MailSetting::create([
            'driver' => 'smtp',
            'host' => 'smtp.unreachable-host-xyz.local',
            'port' => 587,
            'username' => 'test',
            'password' => 'test',
            'from_address' => 'noreply@ecommerce.bd',
            'from_name' => 'Store',
            'is_active' => true,
        ]);

        // Force Mail::send to throw an exception
        Mail::shouldReceive('forgetMailers')->zeroOrMoreTimes()->andReturnNull();
        Mail::shouldReceive('to->send')->andThrow(new Exception('Connection timed out to port 587'));

        $action = app(SendTestEmailAction::class);
        $result = $action('admin@example.com', $setting);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Connection timed out', $result->message);

        $setting->refresh();
        $this->assertEquals('failed', $setting->last_test_status);
        $this->assertStringContainsString('Connection timed out', $setting->last_test_message);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Cart\Models\Cart;
use App\Modules\Communication\Models\MailSetting;
use App\Modules\Communication\Models\SmsGatewaySetting;
use App\Modules\Communication\Models\VoiceCallingSetting;
use App\Modules\Communication\Models\VoiceCallLog;
use App\Modules\Marketing\Models\CartRecoveryLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommunicationAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin@ecommerce.test',
        ]);
    }

    public function test_mail_settings_page_loads_and_displays_data(): void
    {
        MailSetting::create([
            'driver' => 'smtp',
            'host' => 'smtp.mailtrap.io',
            'port' => 2525,
            'encryption' => 'tls',
            'username' => 'testuser',
            'password' => 'secret1234',
            'from_address' => 'orders@ecommerce.test',
            'from_name' => 'Store Mailer',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.communication.mail'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Communication/MailSettings')
            ->where('mailSetting.host', 'smtp.mailtrap.io')
            ->where('mailSetting.port', 2525)
            ->where('mailSetting.is_active', true)
            ->where('mailSetting.has_password', true)
            ->where('mailSetting.masked_password', '••••••••1234')
        );
    }

    public function test_update_mail_settings_stores_and_configures_dynamic_mailer(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.communication.mail.update'), [
            'driver' => 'smtp',
            'host' => 'smtp.sendgrid.net',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'apikey',
            'password' => 'SG.my_secret_token_here',
            'from_address' => 'dispatch@ecommerce.test',
            'from_name' => 'Dispatch Desk',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('mail_settings', [
            'host' => 'smtp.sendgrid.net',
            'port' => 587,
            'username' => 'apikey',
            'from_address' => 'dispatch@ecommerce.test',
            'is_active' => true,
        ]);
    }

    public function test_test_mail_settings_endpoint_validates_and_executes_handshake(): void
    {
        Mail::fake();

        MailSetting::create([
            'driver' => 'smtp',
            'host' => 'smtp.mailtrap.io',
            'port' => 2525,
            'encryption' => 'tls',
            'username' => 'user',
            'password' => 'pass',
            'from_address' => 'noreply@ecommerce.test',
            'from_name' => 'Test Sender',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('admin.communication.mail.test'), [
            'recipient_email' => 'dev@ecommerce.test',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_sms_settings_page_loads_with_default_gateways(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.communication.sms'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Communication/SmsSettings')
            ->has('gateways', 3)
            ->where('gateways.0.code', 'greenweb')
            ->where('gateways.1.code', 'reve')
            ->where('gateways.2.code', 'twilio')
        );
    }

    public function test_update_sms_setting_stores_credentials_and_status(): void
    {
        $setting = SmsGatewaySetting::create([
            'code' => SmsGatewaySetting::CODE_GREENWEB,
            'name' => 'Greenweb SMS',
            'is_active' => false,
            'is_sandbox' => true,
            'credentials' => ['token' => 'old_token'],
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.communication.sms.update', $setting), [
            'is_active' => true,
            'is_sandbox' => false,
            'sender_id' => '8809612000000',
            'credentials' => [
                'token' => 'new_greenweb_api_token_12345',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setting->refresh();
        $this->assertTrue($setting->is_active);
        $this->assertFalse($setting->is_sandbox);
        $this->assertSame('8809612000000', $setting->sender_id);
        $this->assertSame('new_greenweb_api_token_12345', $setting->getCredential('token'));
    }

    public function test_test_sms_connection_and_balance_endpoints(): void
    {
        Http::fake([
            'https://api.greenweb.com.bd/api.php*' => Http::response('Ok: 100 SMS Sent', 200),
            'https://api.greenweb.com.bd/grebxml.php*' => Http::response('<response>550.00</response>', 200),
        ]);

        SmsGatewaySetting::create([
            'code' => SmsGatewaySetting::CODE_GREENWEB,
            'name' => 'Greenweb SMS',
            'is_active' => true,
            'is_sandbox' => false,
            'credentials' => ['token' => 'sample_token'],
            'sort_order' => 1,
        ]);

        // Test connection
        $connResponse = $this->actingAs($this->admin)->postJson(route('admin.communication.sms.test'), [
            'code' => 'greenweb',
            'phone' => '01712345678',
        ]);
        $connResponse->assertOk();
        $connResponse->assertJson(['success' => true]);

        // Test balance query
        $balResponse = $this->actingAs($this->admin)->postJson(route('admin.communication.sms.balance'), [
            'code' => 'greenweb',
        ]);
        $balResponse->assertOk();
        $balResponse->assertJson([
            'success' => true,
            'balance' => 550.0,
            'currency' => 'BDT',
        ]);
    }

    public function test_voice_settings_page_loads_with_setting_and_logs(): void
    {
        $setting = VoiceCallingSetting::create([
            'provider' => VoiceCallingSetting::PROVIDER_TWILIO,
            'is_active' => true,
            'is_sandbox' => true,
            'caller_id' => '+8809612345678',
            'language' => 'bn-BD',
            'dtmf_enabled' => true,
            'speech_enabled' => false,
            'retry_attempts' => 2,
            'credentials' => ['account_sid' => 'ACtest', 'auth_token' => 'authtest', 'from_number' => '+1555'],
        ]);

        VoiceCallLog::create([
            'phone_number' => '01712345678',
            'call_sid' => 'CA_test_sid_001',
            'provider' => 'twilio',
            'status' => 'completed',
            'verification_result' => 'confirmed',
            'dtmf_digit' => '1',
            'duration_seconds' => 45,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.communication.voice'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Communication/VoiceCallingSettings')
            ->where('setting.provider', 'twilio')
            ->where('setting.caller_id', '+8809612345678')
            ->has('logs.data', 1)
            ->where('logs.data.0.call_sid', 'CA_test_sid_001')
            ->where('logs.data.0.verification_result', 'confirmed')
        );
    }

    public function test_update_voice_setting_updates_configuration(): void
    {
        $setting = VoiceCallingSetting::create([
            'provider' => VoiceCallingSetting::PROVIDER_TWILIO,
            'is_active' => false,
            'is_sandbox' => true,
            'language' => 'bn-BD',
            'dtmf_enabled' => true,
            'speech_enabled' => false,
            'retry_attempts' => 2,
            'credentials' => ['account_sid' => 'AC_old', 'auth_token' => 'auth_old', 'from_number' => '+100'],
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.communication.voice.update', $setting), [
            'provider' => 'twilio',
            'is_active' => true,
            'is_sandbox' => false,
            'caller_id' => '+8809611223344',
            'language' => 'bn-BD',
            'dtmf_enabled' => true,
            'speech_enabled' => true,
            'retry_attempts' => 3,
            'script_template_bn' => 'Custom Bangla Prompt',
            'script_template_en' => 'Custom English Prompt',
            'credentials' => [
                'account_sid' => 'AC_new_twilio_sid',
                'auth_token' => 'auth_new_token',
                'from_number' => '+12015550199',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setting->refresh();
        $this->assertTrue($setting->is_active);
        $this->assertFalse($setting->is_sandbox);
        $this->assertSame('+8809611223344', $setting->caller_id);
        $this->assertSame(3, $setting->retry_attempts);
        $this->assertSame('AC_new_twilio_sid', $setting->getCredential('account_sid'));
    }

    public function test_test_voice_call_endpoint_triggers_call(): void
    {
        Http::fake([
            'api.twilio.com/2010-04-01/Accounts/*/Calls.json' => Http::response([
                'sid' => 'CA_mock_test_call_sid',
                'status' => 'queued',
            ], 201),
        ]);

        VoiceCallingSetting::create([
            'provider' => VoiceCallingSetting::PROVIDER_TWILIO,
            'is_active' => true,
            'is_sandbox' => false,
            'caller_id' => '+1555000',
            'language' => 'bn-BD',
            'dtmf_enabled' => true,
            'speech_enabled' => false,
            'retry_attempts' => 2,
            'credentials' => ['account_sid' => 'AC123', 'auth_token' => 'sec456', 'from_number' => '+1555000'],
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('admin.communication.voice.test'), [
            'phone' => '+8801712345678',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'call_sid' => 'CA_mock_test_call_sid',
        ]);
    }

    public function test_cart_recovery_page_loads_with_funnel_stats_and_logs(): void
    {
        $cart = Cart::create([
            'session_id' => 'sess_abandoned_001',
            'status' => 'active',
            'customer_name' => 'Akhtar Hossain',
            'customer_phone' => '01711223344',
            'created_at' => now()->subHours(2),
        ]);

        CartRecoveryLog::create([
            'cart_id' => $cart->id,
            'customer_name' => 'Akhtar Hossain',
            'customer_phone' => '01711223344',
            'step' => 1,
            'channel' => 'whatsapp',
            'status' => 'sent',
            'dispatched_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.marketing.cart-recovery'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Marketing/CartRecovery')
            ->where('stats.total_dispatches', 1)
            ->where('stats.total_recovered_carts', 0)
            ->has('logs.data', 1)
            ->where('logs.data.0.customer_name', 'Akhtar Hossain')
            ->where('logs.data.0.channel', 'whatsapp')
            ->where('logs.data.0.step', 1)
        );
    }

    public function test_trigger_manual_recovery_executes_action(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.marketing.cart-recovery.trigger'));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }
}

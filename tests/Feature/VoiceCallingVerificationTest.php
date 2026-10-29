<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Communication\Actions\TestVoiceCallAction;
use App\Modules\Communication\Actions\TriggerVoiceVerificationCallAction;
use App\Modules\Communication\Models\VoiceCallingSetting;
use App\Modules\Communication\Models\VoiceCallLog;
use App\Modules\Communication\Services\VoiceCallingManager;
use App\Modules\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VoiceCallingVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function createDummyOrder(): Order
    {
        $division = BangladeshDivision::create(['name_en' => 'Dhaka', 'name_bn' => 'ঢাকা']);
        $district = BangladeshDistrict::create([
            'division_id' => $division->id,
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'zone_type' => 'inside_dhaka',
        ]);

        return Order::create([
            'order_number' => 'ORD-20261029-VC01',
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'cash_on_delivery',
            'customer_name' => 'Mehedi Hasan',
            'customer_phone' => '01711223344',
            'customer_email' => 'mehedi@example.com',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Uttara, Sector 4, Dhaka',
            'subtotal_bdt' => 1930.00,
            'shipping_fee_bdt' => 70.00,
            'total_bdt' => 2000.00,
            'payable_cod_amount_bdt' => 2000.00,
        ]);
    }

    public function test_voice_calling_setting_persists_encrypted_credentials(): void
    {
        $setting = VoiceCallingSetting::create([
            'provider' => VoiceCallingSetting::PROVIDER_TWILIO,
            'is_active' => true,
            'is_sandbox' => false,
            'caller_id' => '+18005550199',
            'language' => 'bn-BD',
            'dtmf_enabled' => true,
            'speech_enabled' => true,
            'credentials' => [
                'account_sid' => 'AC1234567890abcdef',
                'auth_token' => 'super_secret_voice_auth_token_999',
                'caller_id' => '+18005550199',
            ],
        ]);

        $this->assertNotEmpty($setting->ulid);
        $this->assertEquals('super_secret_voice_auth_token_999', $setting->getCredential('auth_token'));
        $this->assertEquals('••••••••_999', $setting->getMaskedCredentials()['auth_token']);
        $this->assertTrue($setting->isConfigured());

        // Verify encrypted in database
        $raw = DB::table('voice_calling_settings')->where('id', $setting->id)->first();
        $this->assertNotEquals('super_secret_voice_auth_token_999', $raw->credentials);
        $this->assertStringStartsWith('eyJ', $raw->credentials);
    }

    public function test_twilio_voice_adapter_initiates_call(): void
    {
        $setting = VoiceCallingSetting::create([
            'provider' => VoiceCallingSetting::PROVIDER_TWILIO,
            'is_active' => true,
            'credentials' => [
                'account_sid' => 'AC1234567890',
                'auth_token' => 'test_token',
                'caller_id' => '+18005550199',
            ],
        ]);

        Http::fake([
            'https://api.twilio.com/2010-04-01/Accounts/AC1234567890/Calls.json' => Http::response([
                'sid' => 'CA998877665544',
                'status' => 'queued',
                'to' => '+8801711223344',
                'from' => '+18005550199',
            ], 200),
        ]);

        $manager = app(VoiceCallingManager::class);
        $driver = $manager->resolve(VoiceCallingSetting::PROVIDER_TWILIO);

        $result = $driver->initiateVerificationCall('01711223344');

        $this->assertTrue($result->success);
        $this->assertEquals('CA998877665544', $result->callSid);
        $this->assertStringContainsString('CA998877665544', $result->message);
    }

    public function test_test_voice_call_action_executes_preflight_verification(): void
    {
        $setting = VoiceCallingSetting::create([
            'provider' => VoiceCallingSetting::PROVIDER_TWILIO,
            'is_active' => true,
            'credentials' => [
                'account_sid' => 'AC1234567890',
                'auth_token' => 'test_token',
                'caller_id' => '+18005550199',
            ],
        ]);

        Http::fake([
            'https://api.twilio.com/2010-04-01/Accounts/AC1234567890/Calls.json' => Http::response([
                'sid' => 'CA_TEST_CALL_123',
                'status' => 'queued',
            ], 200),
        ]);

        $action = app(TestVoiceCallAction::class);
        $result = $action('01711223344', VoiceCallingSetting::PROVIDER_TWILIO);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->latencyMs);
        $this->assertStringContainsString('CA_TEST_CALL_123', $result->message);

        $setting->refresh();
        $this->assertEquals('success', $setting->last_test_status);
        $this->assertNotNull($setting->last_tested_at);
    }

    public function test_trigger_voice_verification_call_action_creates_log_and_calls_customer(): void
    {
        VoiceCallingSetting::create([
            'provider' => VoiceCallingSetting::PROVIDER_TWILIO,
            'is_active' => true,
            'credentials' => [
                'account_sid' => 'AC1234567890',
                'auth_token' => 'test_token',
                'caller_id' => '+18005550199',
            ],
        ]);

        $order = $this->createDummyOrder();

        Http::fake([
            'https://api.twilio.com/2010-04-01/Accounts/AC1234567890/Calls.json' => Http::response([
                'sid' => 'CA_ORDER_CALL_99',
                'status' => 'queued',
            ], 200),
        ]);

        $action = app(TriggerVoiceVerificationCallAction::class);
        $result = $action($order);

        $this->assertTrue($result->success);
        $this->assertEquals('CA_ORDER_CALL_99', $result->callSid);

        $log = VoiceCallLog::where('order_id', $order->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('CA_ORDER_CALL_99', $log->call_sid);
        $this->assertEquals(VoiceCallLog::STATUS_RINGING, $log->status);
        $this->assertEquals($order->customer_phone, $log->phone_number);
    }

    public function test_voice_webhook_renders_bilingual_twiml_with_gather(): void
    {
        $order = $this->createDummyOrder();

        $response = $this->get('/webhooks/voice/twiml?order_id='.$order->id);

        $response->assertOk();
        $this->assertStringContainsString('text/xml', (string) $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('<Gather numDigits="1"', $content);
        $this->assertStringContainsString($order->customer_name, $content);
        $this->assertStringContainsString($order->order_number, $content);
        $this->assertStringContainsString('১ চাপুন', $content);
        $this->assertStringContainsString('২ চাপুন', $content);
    }

    public function test_voice_webhook_gather_confirms_order_on_digit_1(): void
    {
        $order = $this->createDummyOrder();
        $callLog = VoiceCallLog::create([
            'order_id' => $order->id,
            'phone_number' => $order->customer_phone,
            'call_sid' => 'CA_VERIFY_01',
            'status' => VoiceCallLog::STATUS_ANSWERED,
        ]);

        $response = $this->post('/webhooks/voice/gather?order_id='.$order->id, [
            'Digits' => '1',
            'CallSid' => 'CA_VERIFY_01',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('কনফার্ম করা হয়েছে', $response->getContent());

        $order->refresh();
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->status);

        $callLog->refresh();
        $this->assertEquals(VoiceCallLog::RESULT_CONFIRMED, $callLog->verification_result);
        $this->assertEquals('1', $callLog->dtmf_digit);
        $this->assertEquals('dtmf', $callLog->response_mode);
    }

    public function test_voice_webhook_gather_cancels_order_on_digit_2(): void
    {
        $order = $this->createDummyOrder();
        $callLog = VoiceCallLog::create([
            'order_id' => $order->id,
            'phone_number' => $order->customer_phone,
            'call_sid' => 'CA_VERIFY_02',
            'status' => VoiceCallLog::STATUS_ANSWERED,
        ]);

        $response = $this->post('/webhooks/voice/gather?order_id='.$order->id, [
            'Digits' => '2',
            'CallSid' => 'CA_VERIFY_02',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('বাতিল করা হয়েছে', $response->getContent());

        $order->refresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);

        $callLog->refresh();
        $this->assertEquals(VoiceCallLog::RESULT_CANCELLED, $callLog->verification_result);
        $this->assertEquals('2', $callLog->dtmf_digit);
        $this->assertEquals('dtmf', $callLog->response_mode);
    }

    public function test_voice_webhook_status_callback_updates_call_metrics(): void
    {
        $callLog = VoiceCallLog::create([
            'phone_number' => '01711223344',
            'call_sid' => 'CA_STATUS_CALLBACK_99',
            'status' => VoiceCallLog::STATUS_RINGING,
        ]);

        $response = $this->post('/webhooks/voice/status', [
            'CallSid' => 'CA_STATUS_CALLBACK_99',
            'CallStatus' => 'completed',
            'CallDuration' => '42',
            'RecordingUrl' => 'https://api.twilio.com/recordings/REC998877',
        ]);

        $response->assertOk();

        $callLog->refresh();
        $this->assertEquals('completed', $callLog->status);
        $this->assertEquals(42, $callLog->duration_seconds);
        $this->assertEquals('https://api.twilio.com/recordings/REC998877', $callLog->recording_url);
    }
}

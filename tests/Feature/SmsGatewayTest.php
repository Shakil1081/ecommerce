<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Communication\Actions\GenerateAndSendOtpAction;
use App\Modules\Communication\Actions\VerifyOtpAction;
use App\Modules\Communication\Models\SmsGatewaySetting;
use App\Modules\Communication\Models\SmsOtp;
use App\Modules\Communication\Services\SmsGatewayManager;
use Database\Seeders\SmsGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class SmsGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_gateway_setting_persists_encrypted_credentials(): void
    {
        $setting = SmsGatewaySetting::create([
            'code' => SmsGatewaySetting::CODE_GREENWEB,
            'name' => 'Greenweb SMS',
            'is_active' => true,
            'is_sandbox' => false,
            'sender_id' => 'GREENWEB',
            'credentials' => [
                'token' => 'my_secret_token_123456',
            ],
        ]);

        $this->assertNotEmpty($setting->ulid);
        $this->assertEquals('my_secret_token_123456', $setting->getCredential('token'));
        $this->assertEquals(['token' => '••••••••3456'], $setting->getMaskedCredentials());

        // Verify encrypted in raw SQL
        $raw = DB::table('sms_gateway_settings')->where('id', $setting->id)->first();
        $this->assertNotEquals('my_secret_token_123456', $raw->credentials);
        $this->assertStringStartsWith('eyJ', $raw->credentials);
    }

    public function test_sms_gateway_manager_resolves_adapters(): void
    {
        $manager = app(SmsGatewayManager::class);

        $greenweb = $manager->resolve(SmsGatewaySetting::CODE_GREENWEB);
        $this->assertEquals(SmsGatewaySetting::CODE_GREENWEB, $greenweb->getCode());

        $reve = $manager->resolve(SmsGatewaySetting::CODE_REVE);
        $this->assertEquals(SmsGatewaySetting::CODE_REVE, $reve->getCode());

        $twilio = $manager->resolve(SmsGatewaySetting::CODE_TWILIO);
        $this->assertEquals(SmsGatewaySetting::CODE_TWILIO, $twilio->getCode());

        $this->expectException(InvalidArgumentException::class);
        $manager->resolve('non_existent_provider');
    }

    public function test_greenweb_sms_adapter_dispatches_message_and_checks_balance(): void
    {
        $setting = SmsGatewaySetting::create([
            'code' => SmsGatewaySetting::CODE_GREENWEB,
            'name' => 'Greenweb SMS',
            'is_active' => true,
            'credentials' => ['token' => 'sample_token'],
        ]);

        Http::fake([
            'https://api.greenweb.com.bd/api.php' => Http::response('Ok: SMS Sent 1', 200),
            'https://api.greenweb.com.bd/grebxml.php*' => Http::response('<response>450.50</response>', 200),
        ]);

        $manager = app(SmsGatewayManager::class);
        $adapter = $manager->resolve(SmsGatewaySetting::CODE_GREENWEB);

        $sendResult = $adapter->sendSms('01712345678', 'Hello test message');
        $this->assertTrue($sendResult->success);
        $this->assertNotNull($sendResult->messageId);

        $balanceResult = $adapter->checkBalance();
        $this->assertTrue($balanceResult->success);
        $this->assertEquals(450.50, $balanceResult->balanceBdt);

        $testResult = $adapter->testConnection();
        $this->assertTrue($testResult->success);
        $this->assertEquals(450.50, $testResult->balanceBdt);
    }

    public function test_reve_sms_adapter_dispatches_message_and_checks_balance(): void
    {
        $setting = SmsGatewaySetting::create([
            'code' => SmsGatewaySetting::CODE_REVE,
            'name' => 'Reve Systems SMS',
            'is_active' => true,
            'sender_id' => 'REVESMS',
            'credentials' => [
                'api_key' => 'test_api_key',
                'secret_key' => 'test_secret_key',
            ],
        ]);

        Http::fake([
            'https://smpp.revesms.com/sendtext*' => Http::response([
                'Status' => 0,
                'Message_ID' => 'REVE-MSG-9988',
                'Text' => 'Message submitted successfully',
            ], 200),
            'https://smpp.revesms.com/checkbalance*' => Http::response([
                'Balance' => 620.00,
            ], 200),
        ]);

        $manager = app(SmsGatewayManager::class);
        $adapter = $manager->resolve(SmsGatewaySetting::CODE_REVE);

        $sendResult = $adapter->sendSms('01912345678', 'Order confirmation notice');
        $this->assertTrue($sendResult->success);
        $this->assertEquals('REVE-MSG-9988', $sendResult->messageId);

        $balanceResult = $adapter->checkBalance();
        $this->assertTrue($balanceResult->success);
        $this->assertEquals(620.00, $balanceResult->balanceBdt);

        $testResult = $adapter->testConnection();
        $this->assertTrue($testResult->success);
    }

    public function test_twilio_sms_adapter_dispatches_message(): void
    {
        $setting = SmsGatewaySetting::create([
            'code' => SmsGatewaySetting::CODE_TWILIO,
            'name' => 'Twilio SMS',
            'is_active' => true,
            'credentials' => [
                'account_sid' => 'AC1234567890',
                'auth_token' => 'auth_token_secret',
                'from_number' => '+18005550199',
            ],
        ]);

        Http::fake([
            'https://api.twilio.com/2010-04-01/Accounts/AC1234567890/Messages.json' => Http::response([
                'sid' => 'SM999988887777',
                'status' => 'queued',
                'price' => '-0.015',
            ], 200),
            'https://api.twilio.com/2010-04-01/Accounts/AC1234567890/Balance.json' => Http::response([
                'balance' => '75.50',
                'currency' => 'USD',
            ], 200),
        ]);

        $manager = app(SmsGatewayManager::class);
        $adapter = $manager->resolve(SmsGatewaySetting::CODE_TWILIO);

        $sendResult = $adapter->sendSms('01812345678', 'Your code is 1234');
        $this->assertTrue($sendResult->success);
        $this->assertEquals('SM999988887777', $sendResult->messageId);
        $this->assertEquals(0.015, $sendResult->costBdt);

        $balanceResult = $adapter->checkBalance();
        $this->assertTrue($balanceResult->success);
        $this->assertEquals(75.50, $balanceResult->balanceBdt);
    }

    public function test_generate_and_send_otp_action_creates_record_and_dispatches_sms(): void
    {
        SmsGatewaySetting::create([
            'code' => SmsGatewaySetting::CODE_GREENWEB,
            'name' => 'Greenweb SMS',
            'is_active' => true,
            'credentials' => ['token' => 'active_token'],
        ]);

        Http::fake([
            'https://api.greenweb.com.bd/api.php' => Http::response('Ok: SMS Sent 1', 200),
        ]);

        $action = app(GenerateAndSendOtpAction::class);
        $result = $action(
            phoneNumber: '01711223344',
            purpose: 'order_verification',
            ttlMinutes: 15,
            digits: 4,
            ipAddress: '103.205.10.1',
        );

        $this->assertArrayHasKey('otp', $result);
        $this->assertArrayHasKey('dispatch_result', $result);
        $this->assertArrayHasKey('plain_code', $result);

        $plainCode = $result['plain_code'];
        $this->assertEquals(4, strlen($plainCode));

        $otp = $result['otp'];
        $this->assertEquals('01711223344', $otp->phone_number);
        $this->assertTrue(Hash::check($plainCode, $otp->otp_code_hash));
        $this->assertFalse($otp->isExpired());
        $this->assertFalse($otp->isVerified());
        $this->assertEquals('103.205.10.1', $otp->ip_address);
        $this->assertTrue($result['dispatch_result']->success);
    }

    public function test_verify_otp_action_validates_code_successfully(): void
    {
        $plainCode = '4821';
        $otp = SmsOtp::create([
            'phone_number' => '01799887766',
            'otp_code_hash' => Hash::make($plainCode),
            'purpose' => 'order_verification',
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        $action = app(VerifyOtpAction::class);
        $result = $action('01799887766', '4821', 'order_verification');

        $this->assertTrue($result->success);
        $this->assertEquals('OTP verified successfully.', $result->message);

        $otp->refresh();
        $this->assertTrue($otp->isVerified());
        $this->assertNotNull($otp->verified_at);
        $this->assertEquals(1, $otp->attempts);
    }

    public function test_verify_otp_action_rejects_incorrect_code_and_tracks_attempts(): void
    {
        $otp = SmsOtp::create([
            'phone_number' => '01755443322',
            'otp_code_hash' => Hash::make('6621'),
            'purpose' => 'order_verification',
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        $action = app(VerifyOtpAction::class);

        // Attempt 1: wrong code
        $result1 = $action('01755443322', '0000');
        $this->assertFalse($result1->success);
        $this->assertStringContainsString('Incorrect', $result1->message);
        $this->assertEquals(1, $otp->fresh()->attempts);

        // Attempt 2: wrong code
        $action('01755443322', '0001');
        $this->assertEquals(2, $otp->fresh()->attempts);

        // Attempt 3: wrong code
        $action('01755443322', '0002');
        $this->assertEquals(3, $otp->fresh()->attempts);

        // Attempt 4: should be blocked due to exceeded max attempts
        $result4 = $action('01755443322', '6621'); // Even with right code, should be blocked!
        $this->assertFalse($result4->success);
        $this->assertTrue($result4->exceededAttempts);
        $this->assertStringContainsString('Maximum verification attempts exceeded', $result4->message);
    }

    public function test_verify_otp_action_rejects_expired_code(): void
    {
        SmsOtp::create([
            'phone_number' => '01733221100',
            'otp_code_hash' => Hash::make('1234'),
            'purpose' => 'order_verification',
            'expires_at' => now()->subMinutes(5), // Expired 5 minutes ago
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        $action = app(VerifyOtpAction::class);
        $result = $action('01733221100', '1234');

        $this->assertFalse($result->success);
        $this->assertTrue($result->expired);
        $this->assertStringContainsString('expired', $result->message);
    }

    public function test_sms_gateway_seeder_initializes_default_providers(): void
    {
        $this->seed(SmsGatewaySeeder::class);

        $this->assertEquals(3, SmsGatewaySetting::count());
        $this->assertNotNull(SmsGatewaySetting::where('code', SmsGatewaySetting::CODE_GREENWEB)->first());
        $this->assertNotNull(SmsGatewaySetting::where('code', SmsGatewaySetting::CODE_REVE)->first());
        $this->assertNotNull(SmsGatewaySetting::where('code', SmsGatewaySetting::CODE_TWILIO)->first());
    }
}

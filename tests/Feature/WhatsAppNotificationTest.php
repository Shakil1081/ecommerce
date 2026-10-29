<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Communication\Actions\SendOrderWhatsAppAlertAction;
use App\Modules\Communication\Models\WhatsAppSetting;
use App\Modules\Communication\Services\WhatsAppClient;
use App\Modules\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppNotificationTest extends TestCase
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
            'order_number' => 'ORD-20261029-WA01',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'cash_on_delivery',
            'customer_name' => 'Sabbir Hossain',
            'customer_phone' => '01712345678',
            'customer_email' => 'sabbir@example.com',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Banani, Dhaka',
            'subtotal_bdt' => 2430.00,
            'shipping_fee_bdt' => 70.00,
            'total_bdt' => 2500.00,
            'payable_cod_amount_bdt' => 2500.00,
        ]);
    }

    public function test_whatsapp_setting_can_be_created_with_encrypted_tokens(): void
    {
        $setting = WhatsAppSetting::create([
            'phone_number_id' => '109887766554433',
            'business_account_id' => '998877665544332',
            'access_token' => 'EAAGm0PX4sample_meta_system_user_token_9988',
            'app_secret' => 'meta_app_secret_hash_value',
            'webhook_verify_token' => 'wh_verify_token_secret_123',
            'is_active' => true,
            'is_sandbox' => false,
            'api_version' => 'v21.0',
        ]);

        $this->assertNotEmpty($setting->ulid);
        $this->assertEquals('EAAGm0PX4sample_meta_system_user_token_9988', $setting->access_token);
        $this->assertEquals('meta_app_secret_hash_value', $setting->app_secret);
        $this->assertEquals('••••••••9988', $setting->getMaskedToken());
        $this->assertTrue($setting->isConfigured());

        // Verify encrypted in raw database row
        $raw = DB::table('whatsapp_settings')->where('id', $setting->id)->first();
        $this->assertNotEquals('EAAGm0PX4sample_meta_system_user_token_9988', $raw->access_token);
        $this->assertStringStartsWith('eyJ', $raw->access_token);
    }

    public function test_whatsapp_client_sends_template_message_successfully(): void
    {
        $setting = WhatsAppSetting::create([
            'phone_number_id' => '109887766554433',
            'access_token' => 'meta_token_123',
            'is_active' => true,
        ]);

        Http::fake([
            'https://graph.facebook.com/v21.0/109887766554433/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '8801712345678', 'wa_id' => '8801712345678']],
                'messages' => [['id' => 'wamid.HBgLMjAyNjEwMjkwMTAwMDI2AA==']],
            ], 200),
        ]);

        $client = new WhatsAppClient($setting);
        $result = $client->sendTemplateMessage(
            to: '01712345678',
            templateName: 'order_confirmation_alert',
            languageCode: 'en_US',
            components: [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => 'Sabbir Hossain'],
                        ['type' => 'text', 'text' => 'ORD-123'],
                    ],
                ],
            ],
        );

        $this->assertTrue($result->success);
        $this->assertEquals('wamid.HBgLMjAyNjEwMjkwMTAwMDI2AA==', $result->messageId);
        $this->assertStringContainsString('order_confirmation_alert', $result->message);
    }

    public function test_whatsapp_client_handles_meta_api_error(): void
    {
        $setting = WhatsAppSetting::create([
            'phone_number_id' => '109887766554433',
            'access_token' => 'meta_token_123',
            'is_active' => true,
        ]);

        Http::fake([
            'https://graph.facebook.com/v21.0/109887766554433/messages' => Http::response([
                'error' => [
                    'message' => 'Template does not exist for the specified language',
                    'type' => 'OAuthException',
                    'code' => 100,
                ],
            ], 400),
        ]);

        $client = new WhatsAppClient($setting);
        $result = $client->sendTemplateMessage(
            to: '01712345678',
            templateName: 'unknown_template',
        );

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Template does not exist', $result->message);
    }

    public function test_whatsapp_client_tests_connection(): void
    {
        $setting = WhatsAppSetting::create([
            'phone_number_id' => '109887766554433',
            'access_token' => 'meta_token_123',
            'is_active' => true,
        ]);

        Http::fake([
            'https://graph.facebook.com/v21.0/109887766554433' => Http::response([
                'id' => '109887766554433',
                'display_phone_number' => '+880 1712-345678',
                'verified_name' => 'Official E-Commerce BD',
                'quality_rating' => 'GREEN',
            ], 200),
        ]);

        $client = new WhatsAppClient($setting);
        $testResult = $client->testConnection();

        $this->assertTrue($testResult->success);
        $this->assertStringContainsString('+880 1712-345678', $testResult->message);
        $this->assertStringContainsString('Official E-Commerce BD', $testResult->message);
        $this->assertNotNull($testResult->latencyMs);

        $setting->refresh();
        $this->assertEquals('success', $setting->last_test_status);
        $this->assertNotNull($setting->last_tested_at);
    }

    public function test_send_order_whatsapp_alert_action_for_confirmed_dispatched_and_delivered(): void
    {
        WhatsAppSetting::create([
            'phone_number_id' => '109887766554433',
            'access_token' => 'meta_token_123',
            'is_active' => true,
        ]);

        $order = $this->createDummyOrder();

        Http::fake([
            'https://graph.facebook.com/v21.0/109887766554433/messages' => Http::response([
                'messages' => [['id' => 'wamid.HBgL'.uniqid()]],
            ], 200),
        ]);

        $action = app(SendOrderWhatsAppAlertAction::class);

        // 1. Order Confirmed
        $resConfirmed = $action($order, SendOrderWhatsAppAlertAction::EVENT_CONFIRMED);
        $this->assertTrue($resConfirmed->success);

        // 2. Order Dispatched
        $resDispatched = $action($order, SendOrderWhatsAppAlertAction::EVENT_DISPATCHED, [
            'courier_name' => 'Steadfast Express',
            'tracking_url' => 'https://packzy.com/track/STDF-9988',
        ]);
        $this->assertTrue($resDispatched->success);

        // 3. Order Delivered
        $resDelivered = $action($order, SendOrderWhatsAppAlertAction::EVENT_DELIVERED, [
            'invoice_url' => 'https://ecommerce.com.bd/invoices/mushak-6-3-9988',
        ]);
        $this->assertTrue($resDelivered->success);
    }

    public function test_whatsapp_webhook_verification_handshake(): void
    {
        WhatsAppSetting::create([
            'phone_number_id' => '109887766554433',
            'access_token' => 'meta_token_123',
            'webhook_verify_token' => 'my_secret_hub_token_4455',
            'is_active' => true,
        ]);

        // Valid verification request
        $response = $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=my_secret_hub_token_4455&hub_challenge=challenge_number_998877');
        $response->assertOk();
        $this->assertEquals('challenge_number_998877', $response->getContent());

        // Invalid token returns 403
        $forbidden = $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong_token&hub_challenge=123');
        $forbidden->assertForbidden();
    }

    public function test_whatsapp_webhook_handles_status_callbacks(): void
    {
        $response = $this->postJson('/webhooks/whatsapp', [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'WHATSAPP_BUSINESS_ACCOUNT_ID',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '8801712345678',
                                    'phone_number_id' => '109887766554433',
                                ],
                                'statuses' => [
                                    [
                                        'id' => 'wamid.HBgL123456',
                                        'status' => 'delivered',
                                        'timestamp' => '1730200000',
                                        'recipient_id' => '8801712345678',
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'success']);
    }
}

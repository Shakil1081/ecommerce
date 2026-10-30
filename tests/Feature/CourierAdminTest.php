<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Logistics\Models\CourierConsignment;
use App\Modules\Logistics\Models\CourierDisbursement;
use App\Modules\Logistics\Models\CourierGatewaySetting;
use App\Modules\Logistics\Models\ShippingRate;
use App\Modules\Logistics\Models\ShippingZone;
use App\Modules\Order\Models\Order;
use Database\Seeders\CourierGatewaySeeder;
use Database\Seeders\ShippingZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CourierAdminTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        return User::factory()->create([
            'email' => 'admin@example.com',
        ]);
    }

    private function createDummyOrder(float $total = 1500.00): Order
    {
        $division = BangladeshDivision::create(['name_en' => 'Dhaka', 'name_bn' => 'ঢাকা']);
        $district = BangladeshDistrict::create([
            'division_id' => $division->id,
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'zone_type' => 'inside_dhaka',
        ]);

        return Order::create([
            'order_number' => 'ORD-20261029-'.uniqid(),
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'cash_on_delivery',
            'customer_name' => 'Kazi Shakil',
            'customer_phone' => '01712345678',
            'customer_email' => 'shakil@example.com',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'House 12, Road 4, Dhanmondi, Dhaka',
            'subtotal_bdt' => $total - 70.00,
            'shipping_fee_bdt' => 70.00,
            'total_bdt' => $total,
            'payable_cod_amount_bdt' => $total,
        ]);
    }

    public function test_admin_can_view_courier_settings_page(): void
    {
        $this->seed(CourierGatewaySeeder::class);
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->get(route('admin.settings.couriers.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Settings/CourierSettings')
            ->has('couriers', 5)
            ->where('couriers.0.code', 'steadfast')
        );
    }

    public function test_admin_can_update_courier_settings(): void
    {
        $admin = $this->createAdminUser();
        $setting = CourierGatewaySetting::create([
            'code' => 'steadfast',
            'name' => 'Steadfast Courier',
            'is_active' => false,
            'is_sandbox' => true,
            'base_url' => 'https://portal.packzy.com/api/v1',
            'webhook_secret' => null,
            'sort_order' => 1,
            'credentials' => [
                'api_key' => 'old_key',
                'secret_key' => 'old_secret',
            ],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.couriers.update', $setting), [
            'name' => 'Steadfast Express BD',
            'is_active' => true,
            'is_sandbox' => false,
            'base_url' => 'https://portal.steadfast.com.bd/api/v1',
            'webhook_secret' => 'whsec_steadfast_prod_99',
            'sort_order' => 2,
            'credentials' => [
                'api_key' => 'live_api_key_888',
                'secret_key' => 'live_secret_key_999',
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setting->refresh();
        $this->assertEquals('Steadfast Express BD', $setting->name);
        $this->assertTrue($setting->is_active);
        $this->assertFalse($setting->is_sandbox);
        $this->assertEquals('https://portal.steadfast.com.bd/api/v1', $setting->base_url);
        $this->assertEquals('whsec_steadfast_prod_99', $setting->webhook_secret);
        $this->assertEquals('live_api_key_888', $setting->getCredential('api_key'));
        $this->assertEquals('live_secret_key_999', $setting->getCredential('secret_key'));
    }

    public function test_admin_updating_courier_preserves_masked_secrets(): void
    {
        $admin = $this->createAdminUser();
        $setting = CourierGatewaySetting::create([
            'code' => 'steadfast',
            'name' => 'Steadfast Courier',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => [
                'api_key' => 'existing_key_111',
                'secret_key' => 'real_secret_token_9999',
            ],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.couriers.update', $setting), [
            'name' => 'Steadfast Courier',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => [
                'api_key' => 'updated_key_222',
                'secret_key' => '••••••••9999', // Masked placeholder should not overwrite original
            ],
        ]);

        $response->assertRedirect();

        $setting->refresh();
        $this->assertEquals('updated_key_222', $setting->getCredential('api_key'));
        $this->assertEquals('real_secret_token_9999', $setting->getCredential('secret_key'));
    }

    public function test_admin_can_test_courier_connection_via_api(): void
    {
        $admin = $this->createAdminUser();
        CourierGatewaySetting::create([
            'code' => 'steadfast',
            'name' => 'Steadfast Courier',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => [
                'api_key' => 'test_api_key',
                'secret_key' => 'test_secret_key',
            ],
        ]);

        Http::fake([
            'https://portal.packzy.com/api/v1/get_balance' => Http::response([
                'status' => 200,
                'current_balance' => 4500.50,
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.settings.couriers.test'), [
            'code' => 'steadfast',
            'is_sandbox' => true,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertStringContainsString('Connected', $response->json('message'));
    }

    public function test_admin_can_view_shipping_zones_page(): void
    {
        $this->seed(ShippingZoneSeeder::class);
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->get(route('admin.logistics.zones'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Logistics/ShippingZones')
            ->has('zones', 3)
            ->where('zones.0.code', 'inside_dhaka')
        );
    }

    public function test_admin_can_update_zone_pricing_and_weight_thresholds(): void
    {
        $this->seed(ShippingZoneSeeder::class);
        $admin = $this->createAdminUser();
        $zone = ShippingZone::where('code', 'inside_dhaka')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.logistics.zones.rate', $zone), [
            'name' => 'Inside Dhaka Metro (24h Express)',
            'estimated_days_min' => 1,
            'estimated_days_max' => 2,
            'base_rate_bdt' => 80.00,
            'base_weight_kg' => 1.5,
            'extra_rate_per_kg_bdt' => 25.00,
            'free_shipping_threshold_bdt' => 3000.00,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $zone->refresh();
        $this->assertEquals('Inside Dhaka Metro (24h Express)', $zone->name);

        $rate = ShippingRate::where('zone_id', $zone->id)->whereNull('courier_code')->first();
        $this->assertNotNull($rate);
        $this->assertEquals('80.0000', (string) $rate->base_rate_bdt);
        $this->assertEquals('1.500', (string) $rate->base_weight_kg);
        $this->assertEquals('25.0000', (string) $rate->extra_rate_per_kg_bdt);
        $this->assertEquals('3000.0000', (string) $rate->free_shipping_threshold_bdt);
    }

    public function test_admin_can_view_cod_reconciliation_page(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->get(route('admin.logistics.reconciliation'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Logistics/CodReconciliation')
            ->has('metrics')
            ->has('disbursements')
        );
    }

    public function test_admin_can_record_courier_disbursement_and_reconcile_cod(): void
    {
        $admin = $this->createAdminUser();
        $order = $this->createDummyOrder(1850.00);

        $consignment = CourierConsignment::create([
            'order_id' => $order->id,
            'courier_code' => 'steadfast',
            'consignment_id' => 'STDF-20261029-99',
            'tracking_code' => 'TRK-990011',
            'status' => CourierConsignment::STATUS_DELIVERED,
            'delivery_zone' => 'inside_dhaka',
            'delivery_fee_bdt' => 70.00,
            'cod_amount_bdt' => 1850.00,
            'recipient_name' => $order->customer_name,
            'recipient_phone' => $order->customer_phone,
            'recipient_address' => $order->delivery_address,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.logistics.reconciliation.store'), [
            'courier_code' => 'steadfast',
            'disbursement_date' => '2026-10-29',
            'reference_invoice' => 'STDF-INV-88771',
            'notes' => 'Weekly settlement from Steadfast Head Office',
            'items' => [
                [
                    'consignment_id' => 'STDF-20261029-99',
                    'order_number' => $order->order_number,
                    'collected_amount_bdt' => 1850.00,
                    'courier_fee_bdt' => 70.00,
                    'notes' => 'Full COD collected',
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $disbursement = CourierDisbursement::where('reference_invoice', 'STDF-INV-88771')->first();
        $this->assertNotNull($disbursement);
        $this->assertEquals(CourierDisbursement::STATUS_RECONCILED, $disbursement->status);
        $this->assertEquals('1850.0000', (string) $disbursement->total_collected_bdt);
        $this->assertEquals('70.0000', (string) $disbursement->total_courier_fee_bdt);
        $this->assertEquals('1780.0000', (string) $disbursement->net_disbursed_bdt);

        $this->assertCount(1, $disbursement->items);
        $item = $disbursement->items->first();
        $this->assertTrue($item->is_reconciled);
        $this->assertEquals('0.0000', (string) $item->discrepancy_amount_bdt);
        $this->assertEquals($order->id, $item->order_id);
    }

    public function test_reconciliation_flags_discrepancy_when_collected_amount_mismatches(): void
    {
        $admin = $this->createAdminUser();
        $order = $this->createDummyOrder(2000.00);

        CourierConsignment::create([
            'order_id' => $order->id,
            'courier_code' => 'steadfast',
            'consignment_id' => 'STDF-20261029-DISC',
            'tracking_code' => 'TRK-DISC-01',
            'status' => CourierConsignment::STATUS_DELIVERED,
            'delivery_zone' => 'inside_dhaka',
            'delivery_fee_bdt' => 70.00,
            'cod_amount_bdt' => 2000.00,
            'recipient_name' => $order->customer_name,
            'recipient_phone' => $order->customer_phone,
            'recipient_address' => $order->delivery_address,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.logistics.reconciliation.store'), [
            'courier_code' => 'steadfast',
            'disbursement_date' => '2026-10-29',
            'reference_invoice' => 'STDF-INV-SHORTAGE',
            'items' => [
                [
                    'consignment_id' => 'STDF-20261029-DISC',
                    'order_number' => $order->order_number,
                    'collected_amount_bdt' => 1800.00, // Shortage of 200 BDT
                    'courier_fee_bdt' => 70.00,
                    'notes' => 'Customer paid partial due to courier dispute',
                ],
            ],
        ]);

        $response->assertRedirect();

        $disbursement = CourierDisbursement::where('reference_invoice', 'STDF-INV-SHORTAGE')->first();
        $this->assertNotNull($disbursement);
        $this->assertEquals(CourierDisbursement::STATUS_DISCREPANCY, $disbursement->status);

        $item = $disbursement->items->first();
        $this->assertFalse($item->is_reconciled);
        $this->assertEquals('-200.0000', (string) $item->discrepancy_amount_bdt);
    }
}

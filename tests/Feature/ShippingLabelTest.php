<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Logistics\Actions\BookCourierConsignmentAction;
use App\Modules\Logistics\Actions\BulkBookCourierConsignmentsAction;
use App\Modules\Logistics\Actions\GenerateShippingLabelHtmlAction;
use App\Modules\Logistics\Models\CourierGatewaySetting;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShippingLabelTest extends TestCase
{
    use RefreshDatabase;

    private CourierGatewaySetting $steadfastSetting;

    private BangladeshDivision $division;

    private BangladeshDistrict $district;

    protected function setUp(): void
    {
        parent::setUp();

        $this->steadfastSetting = CourierGatewaySetting::create([
            'code' => CourierGatewaySetting::CODE_STEADFAST,
            'name' => 'Steadfast Courier',
            'is_active' => true,
            'is_sandbox' => true,
            'base_url' => 'https://portal.packzy.com/api/v1',
            'credentials' => [
                'api_key' => 'live_key_xyz',
                'secret_key' => 'secret_123',
            ],
            'sort_order' => 1,
        ]);

        $this->division = BangladeshDivision::create(['name_en' => 'Dhaka', 'name_bn' => 'ঢাকা']);
        $this->district = BangladeshDistrict::create([
            'division_id' => $this->division->id,
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'zone_type' => 'inside_dhaka',
        ]);
    }

    private function createOrderWithItem(string $orderNumber = 'ORD-2026-LBL-001', string $codAmount = '1850.0000'): Order
    {
        $order = Order::create([
            'order_number' => $orderNumber,
            'customer_name' => 'Monirul Islam',
            'customer_phone' => '+8801755667788',
            'customer_email' => 'monirul@test.com',
            'division_id' => $this->division->id,
            'district_id' => $this->district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Flat 4B, Building 12, Mirpur DOHS, Dhaka',
            'subtotal_bdt' => '1780.0000',
            'shipping_fee_bdt' => '70.0000',
            'total_bdt' => '1850.0000',
            'payable_cod_amount_bdt' => $codAmount,
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => (float) $codAmount > 0 ? Order::PAYMENT_PENDING : Order::PAYMENT_PAID,
            'payment_method' => (float) $codAmount > 0 ? 'cash_on_delivery' : 'bkash',
            'fraud_risk_score' => 10,
            'fraud_risk_level' => 'low',
        ]);

        $category = Category::create([
            'name' => 'Audio',
            'slug' => 'audio-'.uniqid(),
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Premium Wireless Earbuds',
            'slug' => 'premium-wireless-earbuds-'.uniqid(),
            'category_id' => $category->id,
            'is_published' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-EARBUDS-'.uniqid(),
            'attributes' => ['color' => 'Black'],
            'purchase_cost_bdt' => '1200.0000',
            'selling_price_bdt' => '1780.0000',
            'is_active' => true,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'sku' => $variant->sku,
            'unit_price_bdt' => '1780.0000',
            'quantity' => 1,
            'subtotal_bdt' => '1780.0000',
        ]);

        return $order;
    }

    public function test_book_courier_consignment_action_dispatches_order(): void
    {
        Http::fake([
            'https://portal.packzy.com/api/v1/create_order' => Http::response([
                'status' => 200,
                'message' => 'Order created successfully',
                'consignment' => [
                    'consignment_id' => 456789,
                    'invoice' => 'ORD-2026-LBL-001',
                    'tracking_code' => 'ST456789012',
                    'status' => 'in_review',
                ],
            ], 200),
        ]);

        $order = $this->createOrderWithItem();
        $action = app(BookCourierConsignmentAction::class);

        $result = $action($order, 'steadfast');

        $this->assertTrue($result->success);
        $this->assertSame('456789', $result->consignmentId);
        $this->assertSame('ST456789012', $result->trackingCode);

        // Verify order transitions to processing
        $fresh = $order->fresh();
        $this->assertSame(Order::STATUS_PROCESSING, $fresh->status);
        $this->assertSame('steadfast', $fresh->courier_code);
        $this->assertSame('456789', $fresh->courier_consignment_id);

        // Verify audit history created
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => Order::STATUS_PROCESSING,
        ]);
    }

    public function test_book_courier_consignment_action_prevents_booking_cancelled_order(): void
    {
        $order = $this->createOrderWithItem();
        $order->update(['status' => Order::STATUS_CANCELLED]);

        $action = app(BookCourierConsignmentAction::class);
        $result = $action($order);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('cannot be dispatched', $result->errorMessage);
    }

    public function test_bulk_book_courier_consignments_action_processes_batch(): void
    {
        Http::fake([
            'https://portal.packzy.com/api/v1/create_order' => Http::response([
                'status' => 200,
                'message' => 'Order created successfully',
                'consignment' => [
                    'consignment_id' => 999888,
                    'invoice' => 'ORD-BATCH',
                    'tracking_code' => 'ST-BATCH-001',
                ],
            ], 200),
        ]);

        $order1 = $this->createOrderWithItem('ORD-BATCH-01');
        $order2 = $this->createOrderWithItem('ORD-BATCH-02');

        $bulkAction = app(BulkBookCourierConsignmentsAction::class);
        $summary = $bulkAction([$order1->id, $order2->id], 'steadfast');

        $this->assertSame(2, $summary['total']);
        $this->assertSame(2, $summary['booked_count']);
        $this->assertSame(0, $summary['failed_count']);
        $this->assertCount(2, $summary['items']);
    }

    public function test_generate_shipping_label_html_action_renders_thermal_4x6(): void
    {
        $order = $this->createOrderWithItem();
        $order->update([
            'courier_code' => 'steadfast',
            'courier_consignment_id' => '123456',
            'courier_tracking_code' => 'ST123456789',
        ]);

        $action = app(GenerateShippingLabelHtmlAction::class);
        $html = $action($order);

        $this->assertStringContainsString('100mm 150mm', $html);
        $this->assertStringContainsString('Monirul Islam', $html);
        $this->assertStringContainsString('+8801755667788', $html);
        $this->assertStringContainsString('Mirpur DOHS, Dhaka', $html);
        $this->assertStringContainsString('STEADFAST COURIER', $html);
        $this->assertStringContainsString('Cash on Delivery (COD) Amount', $html);
        $this->assertStringContainsString('1,850.00', $html);
        $this->assertStringContainsString('<svg', $html); // Code128 SVG barcode
        $this->assertStringContainsString('ST123456789', $html);
        $this->assertStringContainsString('Premium Wireless Earbuds', $html);
    }

    public function test_generate_shipping_label_renders_prepaid_badge_when_paid(): void
    {
        $order = $this->createOrderWithItem('ORD-PREPAID-001', '0.0000');

        $action = app(GenerateShippingLabelHtmlAction::class);
        $html = $action($order);

        $this->assertStringContainsString('PREPAID - DO NOT COLLECT CASH', $html);
        $this->assertStringNotContainsString('Cash on Delivery (COD) Amount', $html);
    }

    public function test_shipping_label_controller_endpoints(): void
    {
        $admin = User::factory()->create();
        $order = $this->createOrderWithItem();

        // 1. Single Label HTML View
        $response = $this->actingAs($admin)->get(route('admin.orders.shipping-label', $order));
        $response->assertOk();
        $this->assertStringContainsString('100mm 150mm', $response->getContent());

        // 2. Single Consignment Booking endpoint
        Http::fake([
            'https://portal.packzy.com/api/v1/create_order' => Http::response([
                'status' => 200,
                'consignment' => [
                    'consignment_id' => 777111,
                    'tracking_code' => 'ST777111222',
                ],
            ], 200),
        ]);

        $bookResponse = $this->actingAs($admin)->postJson(route('admin.orders.book-courier', $order), [
            'courier_code' => 'steadfast',
        ]);

        $bookResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('consignment_id', '777111');

        // 3. Bulk Label HTML View
        $bulkLabelResponse = $this->actingAs($admin)->post(route('admin.orders.bulk-shipping-labels'), [
            'order_ids' => [$order->id],
        ]);
        $bulkLabelResponse->assertOk();
        $this->assertStringContainsString('Monirul Islam', $bulkLabelResponse->getContent());
    }
}

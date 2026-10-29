<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Common\Models\BangladeshThana;
use App\Modules\Logistics\Models\CourierConsignment;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderTrackingPortalTest extends TestCase
{
    use RefreshDatabase;

    private BangladeshDivision $division;

    private BangladeshDistrict $district;

    private BangladeshThana $thana;

    private Product $product;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = BangladeshDivision::create([
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'is_active' => true,
        ]);

        $this->district = BangladeshDistrict::create([
            'division_id' => $this->division->id,
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'zone_type' => 'inside_dhaka',
            'is_active' => true,
        ]);

        $this->thana = BangladeshThana::create([
            'district_id' => $this->district->id,
            'name_en' => 'Gulshan',
            'name_bn' => 'গুলশান',
            'post_code' => '1212',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Apex',
            'slug' => 'apex',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Flagship Smartphone Pro',
            'slug' => 'flagship-smartphone-pro',
            'is_published' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'PHONE-PRO-BLK',
            'attributes' => ['color' => 'Black', 'storage' => '256GB'],
            'purchase_cost_bdt' => 50000.00,
            'selling_price_bdt' => 65000.00,
            'is_active' => true,
        ]);
    }

    public function test_order_tracking_portal_page_loads_successfully_without_query(): void
    {
        $response = $this->get(route('storefront.order-tracking'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/OrderTracking')
            ->where('tracking', null)
            ->where('errorMessage', null)
        );
    }

    public function test_order_tracking_returns_milestones_and_details_for_valid_order_and_phone(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORD-20261029-0001',
            'customer_phone' => '01712345678',
            'status' => Order::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        $response = $this->get(route('storefront.order-tracking', [
            'order_number' => 'ORD-20261029-0001',
            'phone' => '01712345678',
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/OrderTracking')
            ->where('tracking.order.order_number', 'ORD-20261029-0001')
            ->where('tracking.order.status', Order::STATUS_CONFIRMED)
            ->where('tracking.shipping_address.customer_name', 'Rahim Uddin')
            ->where('tracking.shipping_address.customer_phone_masked', '017****5678')
            ->has('tracking.milestones', 6)
            ->where('tracking.milestones.0.is_completed', true)
            ->where('tracking.milestones.1.is_completed', true)
            ->where('tracking.milestones.1.is_current', true)
            ->has('tracking.items', 1)
            ->where('tracking.items.0.product_title', 'Flagship Smartphone Pro')
            ->where('tracking.financials.total_bdt', '65060.00')
            ->where('tracking.financials.payable_cod_amount_bdt', '65060.00')
        );
    }

    public function test_order_tracking_handles_phone_normalization_formats(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORD-20261029-0002',
            'customer_phone' => '01899998888',
        ]);

        // Search using international +880 format
        $responseIntl = $this->get(route('storefront.order-tracking', [
            'order_number' => 'ORD-20261029-0002',
            'phone' => '+8801899998888',
        ]));
        $responseIntl->assertOk();
        $responseIntl->assertInertia(fn (Assert $page) => $page
            ->where('tracking.order.order_number', 'ORD-20261029-0002')
        );

        // Search using 880 format without plus
        $responsePrefix = $this->get(route('storefront.order-tracking', [
            'order_number' => 'ORD-20261029-0002',
            'phone' => '8801899998888',
        ]));
        $responsePrefix->assertOk();
        $responsePrefix->assertInertia(fn (Assert $page) => $page
            ->where('tracking.order.order_number', 'ORD-20261029-0002')
        );
    }

    public function test_order_tracking_displays_courier_consignment_details_and_live_link(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORD-20261029-0003',
            'customer_phone' => '01911223344',
            'status' => Order::STATUS_HANDED_TO_COURIER,
            'courier_code' => 'steadfast',
            'courier_tracking_code' => 'ST-889900',
            'shipped_at' => now(),
        ]);

        CourierConsignment::create([
            'order_id' => $order->id,
            'courier_code' => 'steadfast',
            'consignment_id' => '100293',
            'tracking_code' => 'ST-889900',
            'status' => CourierConsignment::STATUS_IN_TRANSIT,
            'delivery_zone' => 'inside_dhaka',
            'delivery_fee_bdt' => 60.00,
            'cod_amount_bdt' => 65060.00,
            'recipient_name' => 'Rahim Uddin',
            'recipient_phone' => '01911223344',
            'recipient_address' => 'House 12, Road 4, Gulshan-1',
            'dispatched_at' => now(),
        ]);

        $response = $this->get(route('storefront.order-tracking', [
            'order_number' => 'ORD-20261029-0003',
            'phone' => '01911223344',
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('tracking.courier.courier_name', 'Steadfast Courier')
            ->where('tracking.courier.tracking_code', 'ST-889900')
            ->where('tracking.courier.status', 'in_transit')
            ->where('tracking.courier.tracking_url', 'https://steadfast.com.bd/t/ST-889900')
            ->where('tracking.milestones.3.is_completed', true)
        );
    }

    public function test_order_tracking_with_invalid_credentials_returns_error_message(): void
    {
        $response = $this->get(route('storefront.order-tracking', [
            'order_number' => 'NON-EXISTENT',
            'phone' => '01700000000',
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('tracking', null)
            ->whereNot('errorMessage', null)
        );
    }

    public function test_order_tracking_api_json_endpoint_returns_json_response(): void
    {
        $this->createOrder([
            'order_number' => 'ORD-20261029-0004',
            'customer_phone' => '01511223344',
        ]);

        $response = $this->getJson(route('storefront.order-tracking', [
            'order_number' => 'ORD-20261029-0004',
            'phone' => '01511223344',
        ]));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'order' => [
                    'order_number' => 'ORD-20261029-0004',
                ],
            ],
        ]);
    }

    public function test_post_search_endpoint_validates_and_tracks(): void
    {
        $this->createOrder([
            'order_number' => 'ORD-20261029-0005',
            'customer_phone' => '01611223344',
        ]);

        // Validation fail on empty fields
        $invalid = $this->post(route('storefront.order-tracking.search'), []);
        $invalid->assertSessionHasErrors(['order_number', 'phone']);

        // Successful POST lookup
        $response = $this->post(route('storefront.order-tracking.search'), [
            'order_number' => 'ORD-20261029-0005',
            'phone' => '01611223344',
        ]);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('tracking.order.order_number', 'ORD-20261029-0005')
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createOrder(array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORD-'.uniqid(),
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'cash_on_delivery',
            'customer_name' => 'Rahim Uddin',
            'customer_phone' => '01712345678',
            'customer_email' => 'rahim@example.com',
            'division_id' => $this->division->id,
            'district_id' => $this->district->id,
            'thana_id' => $this->thana->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'House 12, Road 4, Gulshan-1',
            'subtotal_bdt' => 65000.00,
            'discount_amount_bdt' => 0.00,
            'shipping_fee_bdt' => 60.00,
            'total_bdt' => 65060.00,
            'advance_paid_bdt' => 0.00,
            'payable_cod_amount_bdt' => 65060.00,
            'fraud_risk_score' => 15,
            'fraud_risk_level' => 'low',
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'product_name' => $this->product->name,
            'sku' => $this->variant->sku,
            'quantity' => 1,
            'unit_price_bdt' => 65000.00,
            'subtotal_bdt' => 65000.00,
        ]);

        return $order;
    }
}

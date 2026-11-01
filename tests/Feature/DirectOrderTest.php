<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Models\OrderStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DirectOrderTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $activeVariant;

    private BangladeshDivision $division;

    private BangladeshDistrict $district;

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

        BangladeshDistrict::create([
            'division_id' => $this->division->id,
            'name_en' => 'Gazipur',
            'name_bn' => 'গাজীপুর',
            'zone_type' => 'dhaka_suburbs',
            'is_active' => true,
        ]);

        BangladeshDistrict::create([
            'division_id' => $this->division->id,
            'name_en' => 'Chittagong',
            'name_bn' => 'চট্টগ্রাম',
            'zone_type' => 'outside_dhaka',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Skin Care',
            'slug' => 'skin-care',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Cosrx',
            'slug' => 'cosrx',
            'country_of_origin' => 'KR',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Snail Mucin Essence 100ml',
            'slug' => 'snail-mucin-essence',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'origin_country' => 'Korea',
            'is_published' => true,
        ]);

        $this->activeVariant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'COSRX-SNAIL-100',
            'barcode_ean' => '8809482012345',
            'attributes' => ['volume' => '100ml'],
            'purchase_cost_bdt' => '650.0000',
            'selling_price_bdt' => '1150.0000',
            'is_active' => true,
        ]);
    }

    public function test_it_validates_required_fields_for_direct_fast_order(): void
    {
        $response = $this->postJson(route('storefront.fast-order'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'product_variant_id',
            'quantity',
            'customer_name',
            'customer_phone',
            'delivery_address',
            'delivery_zone',
        ]);
    }

    public function test_it_rejects_invalid_bangladesh_phone_number(): void
    {
        $response = $this->postJson(route('storefront.fast-order'), [
            'product_variant_id' => (string) $this->activeVariant->id,
            'quantity' => 1,
            'customer_name' => 'Habibur Rahman',
            'customer_phone' => '01234567890', // 012 is invalid BD mobile prefix
            'delivery_address' => 'House 12, Road 5, Dhanmondi, Dhaka',
            'delivery_zone' => 'inside_dhaka',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_phone']);
    }

    public function test_it_rejects_bot_order_when_honeypot_is_filled(): void
    {
        $response = $this->postJson(route('storefront.fast-order'), [
            'product_variant_id' => (string) $this->activeVariant->id,
            'quantity' => 1,
            'customer_name' => 'Bot Spammer',
            'customer_phone' => '01711223344',
            'delivery_address' => 'Dhaka City',
            'delivery_zone' => 'inside_dhaka',
            'user_company_fax' => 'spambot_value',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['fraud']);
    }

    public function test_it_successfully_creates_direct_order_and_redirects_to_confirmation(): void
    {
        $response = $this->post(route('storefront.fast-order'), [
            'product_variant_id' => (string) $this->activeVariant->id,
            'quantity' => 1,
            'customer_name' => 'Tanvir Ahmed',
            'customer_phone' => '+8801712345678', // Full E.164 format normalized to 01712345678
            'delivery_address' => 'House 45, Road 11, Banani, Dhaka-1213',
            'delivery_zone' => 'inside_dhaka',
            'customer_notes' => 'Please call before delivery',
        ]);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);

        $response->assertRedirect(route('storefront.order-confirmed', ['order' => $order->order_number]));

        // Total check: 1 * 1150 + 60 (inside dhaka) = 1210
        $this->assertEquals('1150.0000', $order->subtotal_bdt);
        $this->assertEquals('60.0000', $order->shipping_fee_bdt);
        $this->assertEquals('1210.0000', $order->total_bdt);
        $this->assertEquals('01712345678', $order->customer_phone);
        $this->assertEquals('cash_on_delivery', $order->payment_method);
        $this->assertEquals(Order::STATUS_PENDING, $order->status);

        // Verify order item
        $item = OrderItem::where('order_id', $order->id)->first();
        $this->assertNotNull($item);
        $this->assertEquals($this->activeVariant->id, $item->product_variant_id);
        $this->assertEquals(1, $item->quantity);
        $this->assertEquals('1150.0000', $item->unit_price_bdt);

        // Verify status history
        $this->assertTrue(
            OrderStatusHistory::where('order_id', $order->id)
                ->where('to_status', Order::STATUS_PENDING)
                ->exists()
        );
    }

    public function test_it_applies_free_shipping_for_orders_at_or_above_2000_bdt(): void
    {
        $response = $this->post(route('storefront.fast-order'), [
            'product_variant_id' => (string) $this->activeVariant->id,
            'quantity' => 2, // 2 * 1150 = 2300 >= 2000 free shipping threshold
            'customer_name' => 'Farhana Kabir',
            'customer_phone' => '01898765432',
            'delivery_address' => 'Agrabad C/A, Chittagong',
            'delivery_zone' => 'outside_dhaka',
        ]);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);

        $response->assertRedirect(route('storefront.order-confirmed', ['order' => $order->order_number]));

        // Shipping fee should be waived to 0.0000
        $this->assertEquals('2300.0000', $order->subtotal_bdt);
        $this->assertEquals('0.0000', $order->shipping_fee_bdt);
        $this->assertEquals('2300.0000', $order->total_bdt);
    }

    public function test_order_confirmation_page_renders_successfully(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-20261101-ABCDEF',
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'cash_on_delivery',
            'customer_name' => 'Sakib Al Hasan',
            'customer_phone' => '01912345678',
            'division_id' => $this->division->id,
            'district_id' => $this->district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Mirpur DOHS, Dhaka',
            'subtotal_bdt' => '1150.0000',
            'discount_amount_bdt' => '0.0000',
            'shipping_fee_bdt' => '60.0000',
            'total_bdt' => '1210.0000',
            'advance_paid_bdt' => '0.0000',
            'payable_cod_amount_bdt' => '1210.0000',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->activeVariant->product_id,
            'product_variant_id' => $this->activeVariant->id,
            'product_name' => 'Snail Mucin Essence 100ml',
            'sku' => $this->activeVariant->sku,
            'quantity' => 1,
            'unit_price_bdt' => '1150.0000',
            'subtotal_bdt' => '1150.0000',
            'unit_cogs_bdt' => '650.0000',
        ]);

        $response = $this->get(route('storefront.order-confirmed', ['order' => $order->order_number]));
        $response->assertStatus(200);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Cart\Actions\AddToCartAction;
use App\Modules\Cart\Actions\GetActiveCartAction;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Marketing\Actions\ApplyCouponToCartAction;
use App\Modules\Marketing\Models\Coupon;
use App\Modules\Order\Actions\CreateOrderFromCartAction;
use App\Modules\Order\Actions\TransitionOrderStatusAction;
use App\Modules\Order\Events\OrderStatusChangedEvent;
use App\Modules\Order\Exceptions\IllegalOrderStateTransitionException;
use App\Modules\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class OrderStateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private BangladeshDivision $division;

    private BangladeshDistrict $dhakaDistrict;

    private BangladeshDistrict $chattogramDistrict;

    private ProductVariant $variantA;

    private ProductVariant $variantB;

    private GetActiveCartAction $getCart;

    private AddToCartAction $addToCart;

    private CreateOrderFromCartAction $createOrder;

    private TransitionOrderStatusAction $transitionStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->division = BangladeshDivision::create([
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'code' => 'DHA',
        ]);

        $this->dhakaDistrict = BangladeshDistrict::create([
            'division_id' => $this->division->id,
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'code' => 'DHA-01',
            'zone_type' => 'inside_dhaka',
        ]);

        $cttgDivision = BangladeshDivision::create([
            'name_en' => 'Chattogram',
            'name_bn' => 'চট্টগ্রাম',
            'code' => 'CTG',
        ]);

        $this->chattogramDistrict = BangladeshDistrict::create([
            'division_id' => $cttgDivision->id,
            'name_en' => 'Chattogram',
            'name_bn' => 'চট্টগ্রাম',
            'code' => 'CTG-01',
            'zone_type' => 'outside_dhaka',
        ]);

        $category = Category::create([
            'name' => 'Audio',
            'slug' => 'audio',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Sony',
            'slug' => 'sony',
            'country_of_origin' => 'JP',
            'is_active' => true,
        ]);

        $productA = Product::create([
            'name' => 'Sony WH-1000XM5',
            'slug' => 'sony-wh-1000xm5',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'origin_country' => 'Japan',
            'is_published' => true,
        ]);

        $this->variantA = ProductVariant::create([
            'product_id' => $productA->id,
            'sku' => 'SNY-XM5-BLK',
            'barcode_ean' => '4905524000001',
            'attributes' => ['color' => 'Black'],
            'purchase_cost_bdt' => '28000.0000',
            'selling_price_bdt' => '35000.0000',
            'is_active' => true,
        ]);

        $productB = Product::create([
            'name' => 'Audio Cable 3.5mm',
            'slug' => 'audio-cable-35mm',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'origin_country' => 'China',
            'is_published' => true,
        ]);

        $this->variantB = ProductVariant::create([
            'product_id' => $productB->id,
            'sku' => 'SNY-CBL-1M',
            'barcode_ean' => '4905524000002',
            'attributes' => ['length' => '1m'],
            'purchase_cost_bdt' => '300.0000',
            'selling_price_bdt' => '500.0000',
            'is_active' => true,
        ]);

        $this->getCart = app(GetActiveCartAction::class);
        $this->addToCart = app(AddToCartAction::class);
        $this->createOrder = app(CreateOrderFromCartAction::class);
        $this->transitionStatus = app(TransitionOrderStatusAction::class);
    }

    public function test_can_create_order_from_cart_with_zone_shipping_fee(): void
    {
        // 1. Add low-cost cable (৳500) which does not qualify for free shipping (< ৳2,000)
        $cart = ($this->getCart)($this->user);
        $cart = ($this->addToCart)($cart, $this->variantB, 1);

        $order = ($this->createOrder)($cart, [
            'customer_name' => 'Rahim Ahmed',
            'customer_phone' => '01712345678',
            'customer_email' => 'rahim@example.com',
            'division_id' => $this->division->id,
            'district_id' => $this->dhakaDistrict->id,
            'delivery_address' => 'House 12, Road 4, Dhanmondi, Dhaka',
            'payment_method' => 'cash_on_delivery',
        ], $this->user);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertStringStartsWith('ORD-', $order->order_number);
        $this->assertEquals(Order::STATUS_PENDING, $order->status);
        $this->assertEquals('inside_dhaka', $order->delivery_zone);
        $this->assertEquals('500.0000', $order->subtotal_bdt);
        $this->assertEquals('70.0000', $order->shipping_fee_bdt); // Inside Dhaka flat rate ৳70
        $this->assertEquals('570.0000', $order->total_bdt);
        $this->assertEquals('570.0000', $order->payable_cod_amount_bdt);

        // Check order item and COGS snapshot
        $this->assertCount(1, $order->items);
        $item = $order->items->first();
        $this->assertEquals($this->variantB->id, $item->product_variant_id);
        $this->assertEquals('300.0000', $item->unit_cogs_bdt);

        // Check audit status history
        $this->assertCount(1, $order->statusHistories);
        $history = $order->statusHistories->first();
        $this->assertNull($history->from_status);
        $this->assertEquals(Order::STATUS_PENDING, $history->to_status);

        // Cart is marked as converted
        $cart->refresh();
        $this->assertEquals('converted', $cart->status);
    }

    public function test_outside_dhaka_delivery_fee_applied(): void
    {
        $cart = ($this->getCart)($this->user);
        $cart = ($this->addToCart)($cart, $this->variantB, 1);

        $order = ($this->createOrder)($cart, [
            'customer_name' => 'Tanvir Hossain',
            'customer_phone' => '01812345678',
            'division_id' => $this->chattogramDistrict->division_id,
            'district_id' => $this->chattogramDistrict->id,
            'delivery_address' => 'GEC Circle, Chattogram',
        ], $this->user);

        $this->assertEquals('outside_dhaka', $order->delivery_zone);
        $this->assertEquals('130.0000', $order->shipping_fee_bdt); // Outside Dhaka rate ৳130
        $this->assertEquals('630.0000', $order->total_bdt);
    }

    public function test_free_shipping_unlocked_when_subtotal_meets_threshold(): void
    {
        // Add Sony headphones (৳35,000) > ৳2,000 threshold
        $cart = ($this->getCart)($this->user);
        $cart = ($this->addToCart)($cart, $this->variantA, 1);

        $order = ($this->createOrder)($cart, [
            'customer_name' => 'Rahim Ahmed',
            'customer_phone' => '01712345678',
            'division_id' => $this->chattogramDistrict->division_id,
            'district_id' => $this->chattogramDistrict->id,
            'delivery_address' => 'Agrabad C/A, Chattogram',
        ], $this->user);

        $this->assertEquals('0.0000', $order->shipping_fee_bdt);
        $this->assertEquals('35000.0000', $order->total_bdt);
    }

    public function test_free_shipping_coupon_grants_zero_delivery_fee(): void
    {
        $coupon = Coupon::create([
            'code' => 'FREEDELIVERY',
            'name' => 'Free Shipping Nationwide',
            'discount_type' => Coupon::TYPE_FREE_SHIPPING,
            'is_active' => true,
        ]);

        $cart = ($this->getCart)($this->user);
        $cart = ($this->addToCart)($cart, $this->variantB, 1); // ৳500

        $applyCoupon = app(ApplyCouponToCartAction::class);
        $cart = $applyCoupon($cart, 'FREEDELIVERY', $this->user);

        $order = ($this->createOrder)($cart, [
            'customer_name' => 'Kazi Nazrul',
            'customer_phone' => '01912345678',
            'division_id' => $this->chattogramDistrict->division_id,
            'district_id' => $this->chattogramDistrict->id,
            'delivery_address' => 'Nasirabad, Chattogram',
        ], $this->user);

        $this->assertEquals('0.0000', $order->shipping_fee_bdt);
        $this->assertEquals('500.0000', $order->total_bdt);
        $this->assertEquals('FREEDELIVERY', $order->coupon_code);

        $coupon->refresh();
        $this->assertEquals(1, $coupon->total_used_count);
    }

    public function test_order_finite_state_machine_happy_path_to_delivered(): void
    {
        Event::fake([OrderStatusChangedEvent::class]);

        $cart = ($this->getCart)($this->user);
        $cart = ($this->addToCart)($cart, $this->variantA, 1);
        $order = ($this->createOrder)($cart, [
            'customer_name' => 'Shafiqul Islam',
            'customer_phone' => '01711223344',
            'division_id' => $this->division->id,
            'district_id' => $this->dhakaDistrict->id,
            'delivery_address' => 'Gulshan 2, Dhaka',
            'payment_method' => 'cash_on_delivery',
        ], $this->user);

        $this->assertEquals(Order::STATUS_PENDING, $order->status);
        $this->assertNull($order->confirmed_at);

        // 1. pending -> confirmed
        $order = ($this->transitionStatus)($order, Order::STATUS_CONFIRMED, $this->user, 'Customer confirmed via OTP');
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->status);
        $this->assertNotNull($order->confirmed_at);

        // 2. confirmed -> processing
        $order = ($this->transitionStatus)($order, Order::STATUS_PROCESSING, $this->user, 'Sent to warehouse picking list');
        $this->assertEquals(Order::STATUS_PROCESSING, $order->status);

        // 3. processing -> picked_and_packed
        $order = ($this->transitionStatus)($order, Order::STATUS_PICKED_AND_PACKED, $this->user, 'Packed with 4x6 thermal barcode');
        $this->assertEquals(Order::STATUS_PICKED_AND_PACKED, $order->status);

        // 4. picked_and_packed -> handed_to_courier
        $order = ($this->transitionStatus)($order, Order::STATUS_HANDED_TO_COURIER, $this->user, 'Consignment booked with Steadfast');
        $this->assertEquals(Order::STATUS_HANDED_TO_COURIER, $order->status);
        $this->assertNotNull($order->shipped_at);

        // 5. handed_to_courier -> out_for_delivery
        $order = ($this->transitionStatus)($order, Order::STATUS_OUT_FOR_DELIVERY, $this->user, 'Rider assigned for doorstep delivery');
        $this->assertEquals(Order::STATUS_OUT_FOR_DELIVERY, $order->status);

        // 6. out_for_delivery -> delivered
        $order = ($this->transitionStatus)($order, Order::STATUS_DELIVERED, $this->user, 'Customer received parcel and paid COD');
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertEquals(Order::PAYMENT_PAID, $order->payment_status);

        // Verify status histories count: initial + 6 transitions = 7 entries
        $this->assertCount(7, $order->statusHistories);

        Event::assertDispatched(OrderStatusChangedEvent::class, 6);
    }

    public function test_illegal_order_state_transition_throws_exception(): void
    {
        $cart = ($this->getCart)($this->user);
        $cart = ($this->addToCart)($cart, $this->variantA, 1);
        $order = ($this->createOrder)($cart, [
            'customer_name' => 'Faisal Karim',
            'customer_phone' => '01511223344',
            'division_id' => $this->division->id,
            'district_id' => $this->dhakaDistrict->id,
            'delivery_address' => 'Uttara Sector 7, Dhaka',
        ], $this->user);

        // Cannot jump from pending directly to delivered
        $this->expectException(IllegalOrderStateTransitionException::class);
        ($this->transitionStatus)($order, Order::STATUS_DELIVERED, $this->user);
    }

    public function test_order_cancellation_from_pre_shipment_states(): void
    {
        $cart = ($this->getCart)($this->user);
        $cart = ($this->addToCart)($cart, $this->variantA, 1);
        $order = ($this->createOrder)($cart, [
            'customer_name' => 'Sadia Akter',
            'customer_phone' => '01611223344',
            'division_id' => $this->division->id,
            'district_id' => $this->dhakaDistrict->id,
            'delivery_address' => 'Mirpur 10, Dhaka',
        ], $this->user);

        $order = ($this->transitionStatus)($order, Order::STATUS_CONFIRMED, $this->user);
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->status);

        $order = ($this->transitionStatus)($order, Order::STATUS_CANCELLED, $this->user, 'Customer changed mind');
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);
        $this->assertNotNull($order->cancelled_at);

        // Terminal state: cannot transition out of cancelled
        $this->expectException(IllegalOrderStateTransitionException::class);
        ($this->transitionStatus)($order, Order::STATUS_CONFIRMED, $this->user);
    }
}

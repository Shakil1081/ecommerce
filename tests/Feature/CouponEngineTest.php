<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Cart\Actions\AddToCartAction;
use App\Modules\Cart\Actions\GetActiveCartAction;
use App\Modules\Cart\Models\Cart;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Marketing\Actions\ApplyCouponToCartAction;
use App\Modules\Marketing\Actions\RecordCouponUsageAction;
use App\Modules\Marketing\Actions\RemoveCouponFromCartAction;
use App\Modules\Marketing\Models\Coupon;
use App\Modules\Marketing\Models\CouponUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CouponEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ProductVariant $variant;

    private GetActiveCartAction $getCart;

    private AddToCartAction $addToCart;

    private ApplyCouponToCartAction $applyCoupon;

    private RemoveCouponFromCartAction $removeCoupon;

    private RecordCouponUsageAction $recordUsage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $category = Category::create([
            'name' => 'Laptops',
            'slug' => 'laptops',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Asus',
            'slug' => 'asus',
            'country_of_origin' => 'TW',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'ZenBook 14 OLED',
            'slug' => 'zenbook-14-oled',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'origin_country' => 'Taiwan',
            'is_published' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'ZB14-OLED-512',
            'barcode_ean' => '8809988776655',
            'attributes' => ['color' => 'Ponder Blue'],
            'purchase_cost_bdt' => '90000.0000',
            'selling_price_bdt' => '100000.0000',
            'is_active' => true,
        ]);

        $this->getCart = app(GetActiveCartAction::class);
        $this->addToCart = app(AddToCartAction::class);
        $this->applyCoupon = app(ApplyCouponToCartAction::class);
        $this->removeCoupon = app(RemoveCouponFromCartAction::class);
        $this->recordUsage = app(RecordCouponUsageAction::class);
    }

    public function test_can_apply_percentage_coupon_and_recalculates_cart_total(): void
    {
        $coupon = Coupon::create([
            'code' => 'EID10',
            'name' => 'Eid 10% Discount',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => '10.0000',
            'min_order_amount_bdt' => '1000.0000',
            'is_active' => true,
        ]);

        $cart = ($this->getCart)($this->user);
        $cart = ($this->addToCart)($cart, $this->variant, 1); // ৳100,000 subtotal

        $updatedCart = ($this->applyCoupon)($cart, 'eid10', $this->user);

        $this->assertEquals('EID10', $updatedCart->coupon_code);
        $this->assertEquals('10000.0000', $updatedCart->discount_amount_bdt);
        $this->assertEquals('90000.0000', $updatedCart->total_bdt);
    }

    public function test_percentage_coupon_with_max_discount_cap(): void
    {
        $coupon = Coupon::create([
            'code' => 'CAP5000',
            'name' => '10% up to ৳5,000',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => '10.0000',
            'max_discount_amount_bdt' => '5000.0000',
            'is_active' => true,
        ]);

        $cart = ($this->getCart)($this->user);
        $cart = ($this->addToCart)($cart, $this->variant, 1); // ৳100,000 subtotal (10% would be ৳10,000)

        $updatedCart = ($this->applyCoupon)($cart, 'CAP5000', $this->user);

        $this->assertEquals('5000.0000', $updatedCart->discount_amount_bdt);
        $this->assertEquals('95000.0000', $updatedCart->total_bdt);
    }

    public function test_can_apply_fixed_amount_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'FLAT1500',
            'name' => 'Flat ৳1,500 Off',
            'discount_type' => Coupon::TYPE_FIXED,
            'discount_value' => '1500.0000',
            'min_order_amount_bdt' => '50000.0000',
            'is_active' => true,
        ]);

        $cart = ($this->getCart)($this->user);
        $cart = ($this->addToCart)($cart, $this->variant, 1); // ৳100,000 subtotal

        $updatedCart = ($this->applyCoupon)($cart, 'FLAT1500', $this->user);

        $this->assertEquals('1500.0000', $updatedCart->discount_amount_bdt);
        $this->assertEquals('98500.0000', $updatedCart->total_bdt);
    }

    public function test_coupon_fails_if_minimum_spend_not_met(): void
    {
        $coupon = Coupon::create([
            'code' => 'VIPONLY',
            'name' => 'VIP ৳200,000 min',
            'discount_type' => Coupon::TYPE_FIXED,
            'discount_value' => '5000.0000',
            'min_order_amount_bdt' => '200000.0000',
            'is_active' => true,
        ]);

        $cart = ($this->getCart)($this->user);
        $cart = ($this->addToCart)($cart, $this->variant, 1); // ৳100,000 subtotal

        $this->expectException(ValidationException::class);
        ($this->applyCoupon)($cart, 'VIPONLY', $this->user);
    }

    public function test_coupon_fails_if_expired_or_inactive(): void
    {
        $inactive = Coupon::create([
            'code' => 'INACTIVE',
            'name' => 'Disabled Coupon',
            'is_active' => false,
        ]);

        $expired = Coupon::create([
            'code' => 'EXPIRED',
            'name' => 'Old Coupon',
            'is_active' => true,
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $cart = ($this->getCart)($this->user);
        $cart = ($this->addToCart)($cart, $this->variant, 1);

        try {
            ($this->applyCoupon)($cart, 'INACTIVE', $this->user);
            $this->fail('Expected ValidationException for inactive coupon');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->errors());
        }

        try {
            ($this->applyCoupon)($cart, 'EXPIRED', $this->user);
            $this->fail('Expected ValidationException for expired coupon');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->errors());
        }
    }

    public function test_coupon_per_user_limit_enforced(): void
    {
        $coupon = Coupon::create([
            'code' => 'ONCEONLY',
            'name' => 'Single Use per Customer',
            'discount_type' => Coupon::TYPE_FIXED,
            'discount_value' => '1000.0000',
            'usage_limit_per_user' => 1,
            'is_active' => true,
        ]);

        // Record 1 usage by this user
        ($this->recordUsage)($coupon, $this->user, null, null, '1000.0000');

        $cart = ($this->getCart)($this->user);
        $cart = ($this->addToCart)($cart, $this->variant, 1);

        $this->expectException(ValidationException::class);
        ($this->applyCoupon)($cart, 'ONCEONLY', $this->user);
    }

    public function test_can_record_coupon_usage_and_increments_count(): void
    {
        $coupon = Coupon::create([
            'code' => 'TRACKME',
            'name' => 'Track Usage',
            'discount_type' => Coupon::TYPE_FIXED,
            'discount_value' => '500.0000',
            'is_active' => true,
        ]);

        $this->assertEquals(0, $coupon->total_used_count);

        $usage = ($this->recordUsage)($coupon, $this->user, null, 42, '500.0000');

        $this->assertInstanceOf(CouponUsage::class, $usage);
        $this->assertEquals($coupon->id, $usage->coupon_id);
        $this->assertEquals($this->user->id, $usage->user_id);
        $this->assertEquals(42, $usage->order_id);
        $this->assertEquals('500.0000', $usage->discount_amount_bdt);

        $coupon->refresh();
        $this->assertEquals(1, $coupon->total_used_count);
    }

    public function test_can_remove_coupon_and_resets_cart_totals(): void
    {
        $coupon = Coupon::create([
            'code' => 'REMOVEME',
            'name' => 'To Be Removed',
            'discount_type' => Coupon::TYPE_FIXED,
            'discount_value' => '2000.0000',
            'is_active' => true,
        ]);

        $cart = ($this->getCart)($this->user);
        $cart = ($this->addToCart)($cart, $this->variant, 1);
        $cart = ($this->applyCoupon)($cart, 'REMOVEME', $this->user);

        $this->assertEquals('2000.0000', $cart->discount_amount_bdt);

        $clearedCart = ($this->removeCoupon)($cart);

        $this->assertNull($clearedCart->coupon_code);
        $this->assertEquals('0.0000', $clearedCart->discount_amount_bdt);
        $this->assertEquals('100000.0000', $clearedCart->total_bdt);
    }

    public function test_cart_controller_coupon_endpoints_via_api(): void
    {
        Coupon::create([
            'code' => 'APITEST',
            'name' => 'API Test Coupon',
            'discount_type' => Coupon::TYPE_FIXED,
            'discount_value' => '3000.0000',
            'is_active' => true,
        ]);

        // 1. Add item to cart
        $addResponse = $this->actingAs($this->user)->postJson('/cart/items', [
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
        ]);
        $addResponse->assertOk();

        // 2. Apply coupon via POST /cart/coupon
        $applyResponse = $this->actingAs($this->user)->postJson('/cart/coupon', [
            'code' => 'APITEST',
        ]);

        $applyResponse->assertOk()
            ->assertJsonPath('data.coupon_code', 'APITEST')
            ->assertJsonPath('data.discount_amount_bdt', '3000.0000')
            ->assertJsonPath('data.total_bdt', '97000.0000');

        // 3. Remove coupon via DELETE /cart/coupon
        $removeResponse = $this->actingAs($this->user)->deleteJson('/cart/coupon');

        $removeResponse->assertOk()
            ->assertJsonPath('data.coupon_code', null)
            ->assertJsonPath('data.discount_amount_bdt', '0.0000')
            ->assertJsonPath('data.total_bdt', '100000.0000');
    }
}

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
use App\Modules\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StorefrontCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private BangladeshDivision $division;

    private BangladeshDistrict $district;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->division = BangladeshDivision::create([
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'code' => 'DHA',
            'is_active' => true,
        ]);

        $this->district = BangladeshDistrict::create([
            'division_id' => $this->division->id,
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'code' => 'DHA-01',
            'zone_type' => 'inside_dhaka',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Fashion',
            'slug' => 'fashion',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Charles & Keith',
            'slug' => 'charles-keith',
            'country_of_origin' => 'SG',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Classic Shoulder Bag',
            'slug' => 'classic-shoulder-bag',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'origin_country' => 'Singapore',
            'is_published' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'CK-BAG-001',
            'barcode_ean' => '848061999001',
            'attributes' => ['color' => 'Pink'],
            'purchase_cost_bdt' => '1500.0000',
            'selling_price_bdt' => '2500.0000',
            'is_active' => true,
        ]);
    }

    public function test_checkout_page_renders_successfully(): void
    {
        $response = $this->get('/checkout');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Storefront/Checkout')
            ->has('cart')
            ->has('divisions')
            ->has('shippingRates')
        );
    }

    public function test_checkout_submission_fails_when_cart_is_empty(): void
    {
        $response = $this->post('/checkout', [
            'customer_name' => 'Sultana Razia',
            'customer_phone' => '01712349876',
            'division_id' => $this->division->id,
            'district_id' => $this->district->id,
            'delivery_address' => 'House 5, Road 2, Dhanmondi, Dhaka',
            'payment_method' => 'cash_on_delivery',
        ]);

        $response->assertSessionHasErrors(['cart']);
    }

    public function test_user_can_submit_order_from_cart_and_redirects_to_confirmation(): void
    {
        $getCart = app(GetActiveCartAction::class);
        $addToCart = app(AddToCartAction::class);

        $cart = $getCart(user: $this->user);
        $addToCart($cart, $this->variant, 1);

        $response = $this->actingAs($this->user)->post('/checkout', [
            'customer_name' => 'Sultana Razia',
            'customer_phone' => '01712349876',
            'division_id' => $this->division->id,
            'district_id' => $this->district->id,
            'delivery_address' => 'House 5, Road 2, Dhanmondi, Dhaka',
            'payment_method' => 'cash_on_delivery',
        ]);

        $order = Order::latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals('Sultana Razia', $order->customer_name);
        $this->assertEquals('01712349876', $order->customer_phone);

        $response->assertRedirect(route('storefront.order-confirmed', ['order' => $order->order_number]));
    }

    public function test_user_can_submit_order_using_delivery_zone_without_division_or_district(): void
    {
        $getCart = app(GetActiveCartAction::class);
        $addToCart = app(AddToCartAction::class);

        $cart = $getCart(user: $this->user);
        $addToCart($cart, $this->variant, 1);

        $response = $this->actingAs($this->user)->post('/checkout', [
            'customer_name' => 'Fahim Ahmed',
            'customer_phone' => '01811223344',
            'delivery_zone' => 'outside_dhaka',
            'delivery_address' => 'GEC Circle, Nasirabad, Chattogram',
            'payment_method' => 'cash_on_delivery',
        ]);

        $order = Order::latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals('Fahim Ahmed', $order->customer_name);
        $this->assertEquals('01811223344', $order->customer_phone);
        $this->assertEquals('outside_dhaka', $order->delivery_zone);

        $response->assertRedirect(route('storefront.order-confirmed', ['order' => $order->order_number]));
    }
}

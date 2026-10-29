<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Cart\Actions\AddToCartAction;
use App\Modules\Cart\Actions\GetActiveCartAction;
use App\Modules\Cart\Actions\MergeGuestCartAction;
use App\Modules\Cart\Actions\RecalculateCartAction;
use App\Modules\Cart\Actions\RemoveFromCartAction;
use App\Modules\Cart\Actions\UpdateCartItemQuantityAction;
use App\Modules\Cart\Models\Cart;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Pricing\Actions\CalculateApplicablePriceAction;
use App\Modules\Pricing\Models\PricingTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ProductVariant $variantA;

    private ProductVariant $variantB;

    private AddToCartAction $addToCart;

    private UpdateCartItemQuantityAction $updateQuantity;

    private RemoveFromCartAction $removeFromCart;

    private MergeGuestCartAction $mergeCart;

    private GetActiveCartAction $getCart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $category = Category::create([
            'name' => 'Smartphones',
            'slug' => 'smartphones',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Samsung',
            'slug' => 'samsung',
            'country_of_origin' => 'KR',
            'is_active' => true,
        ]);

        $productA = Product::create([
            'name' => 'Galaxy S25 Ultra',
            'slug' => 'galaxy-s25-ultra',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'origin_country' => 'Vietnam',
            'is_published' => true,
        ]);

        $this->variantA = ProductVariant::create([
            'product_id' => $productA->id,
            'sku' => 'GS25U-512-BLK',
            'barcode_ean' => '8801234567891',
            'attributes' => ['color' => 'Titanium Black'],
            'purchase_cost_bdt' => '120000.0000',
            'selling_price_bdt' => '150000.0000',
            'is_active' => true,
        ]);

        // Volume Tier for Variant A: 5+ units get ৳140,000.00 (৳10,000 savings/unit)
        PricingTier::create([
            'product_variant_id' => $this->variantA->id,
            'customer_group' => PricingTier::GROUP_RETAIL,
            'min_quantity' => 5,
            'tier_price_bdt' => '140000.0000',
            'is_active' => true,
        ]);

        $productB = Product::create([
            'name' => 'Wireless Fast Charger 45W',
            'slug' => 'wireless-fast-charger-45w',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'origin_country' => 'Vietnam',
            'is_published' => true,
        ]);

        $this->variantB = ProductVariant::create([
            'product_id' => $productB->id,
            'sku' => 'WFC-45W-WHT',
            'barcode_ean' => '8801234567892',
            'attributes' => ['color' => 'White'],
            'purchase_cost_bdt' => '2500.0000',
            'selling_price_bdt' => '4000.0000',
            'is_active' => true,
        ]);

        $calcPrice = new CalculateApplicablePriceAction;
        $recalc = new RecalculateCartAction($calcPrice);
        $this->addToCart = new AddToCartAction($calcPrice, $recalc);
        $this->updateQuantity = new UpdateCartItemQuantityAction($calcPrice, $recalc);
        $this->removeFromCart = new RemoveFromCartAction($recalc);
        $this->getCart = new GetActiveCartAction;
        $this->mergeCart = new MergeGuestCartAction($this->getCart, $recalc);
    }

    public function test_can_resolve_active_cart_for_guest_and_user(): void
    {
        $guestCart = ($this->getCart)(guestToken: 'guest_session_token_123');
        $this->assertNotNull($guestCart);
        $this->assertEquals('guest_session_token_123', $guestCart->guest_token);
        $this->assertNull($guestCart->user_id);
        $this->assertEquals('active', $guestCart->status);

        $userCart = ($this->getCart)(user: $this->user);
        $this->assertNotNull($userCart);
        $this->assertEquals($this->user->id, $userCart->user_id);
        $this->assertEquals('active', $userCart->status);
    }

    public function test_can_add_item_to_cart_and_trigger_volume_tier_pricing(): void
    {
        $cart = ($this->getCart)(guestToken: 'token_abc');

        // Add 1 unit at regular price ৳150,000.00
        $cart = ($this->addToCart)($cart, $this->variantA, 1);

        $this->assertEquals(1, $cart->total_items_count);
        $this->assertEquals('150000.0000', (string) $cart->subtotal_bdt);
        $this->assertEquals('150000.0000', (string) $cart->total_bdt);

        // Add 4 more units (total 5) -> triggers volume tier at ৳140,000.00 each = ৳700,000.00
        $cart = ($this->addToCart)($cart, $this->variantA, 4);

        $this->assertEquals(5, $cart->total_items_count);
        $this->assertEquals('700000.0000', (string) $cart->subtotal_bdt);

        $item = $cart->items->first();
        $this->assertEquals(5, $item->quantity);
        $this->assertEquals('140000.0000', (string) $item->unit_price_bdt);
        $this->assertEquals('10000.0000', (string) $item->tier_savings_bdt);
    }

    public function test_can_update_cart_quantity_and_remove_when_zero(): void
    {
        $cart = ($this->getCart)(guestToken: 'token_xyz');
        $cart = ($this->addToCart)($cart, $this->variantB, 3); // 3 * ৳4,000 = ৳12,000

        $this->assertEquals(3, $cart->total_items_count);
        $this->assertEquals('12000.0000', (string) $cart->subtotal_bdt);

        // Update quantity to 2
        $cart = ($this->updateQuantity)($cart, $this->variantB->id, 2);
        $this->assertEquals(2, $cart->total_items_count);
        $this->assertEquals('8000.0000', (string) $cart->subtotal_bdt);

        // Update quantity to 0 removes the item
        $cart = ($this->updateQuantity)($cart, $this->variantB->id, 0);
        $this->assertEquals(0, $cart->total_items_count);
        $this->assertEquals('0.0000', (string) $cart->subtotal_bdt);
        $this->assertCount(0, $cart->items);
    }

    public function test_can_remove_item_from_cart(): void
    {
        $cart = ($this->getCart)(guestToken: 'token_rm');
        $cart = ($this->addToCart)($cart, $this->variantA, 1);
        $cart = ($this->addToCart)($cart, $this->variantB, 2);

        $this->assertEquals(3, $cart->total_items_count);

        $cart = ($this->removeFromCart)($cart, $this->variantA->id);

        $this->assertEquals(2, $cart->total_items_count);
        $this->assertEquals('8000.0000', (string) $cart->subtotal_bdt);
    }

    public function test_can_merge_guest_cart_into_authenticated_user_cart(): void
    {
        // 1. Guest cart with 2 Variant A and 1 Variant B
        $guestCart = ($this->getCart)(guestToken: 'guest_merge_token');
        $guestCart = ($this->addToCart)($guestCart, $this->variantA, 2);
        $guestCart = ($this->addToCart)($guestCart, $this->variantB, 1);

        // 2. Existing user cart with 1 Variant A
        $userCart = ($this->getCart)(user: $this->user);
        $userCart = ($this->addToCart)($userCart, $this->variantA, 1);

        // 3. User logs in -> merge guest cart into user cart
        $mergedCart = ($this->mergeCart)($guestCart, $this->user);

        // Total Variant A should be 2 + 1 = 3
        // Total Variant B should be 1
        $this->assertEquals(4, $mergedCart->total_items_count);
        $this->assertEquals('converted', $guestCart->fresh()->status);

        $itemA = $mergedCart->items->where('product_variant_id', $this->variantA->id)->first();
        $this->assertNotNull($itemA);
        $this->assertEquals(3, $itemA->quantity);

        $itemB = $mergedCart->items->where('product_variant_id', $this->variantB->id)->first();
        $this->assertNotNull($itemB);
        $this->assertEquals(1, $itemB->quantity);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Cart\Http\Resources\CartResource;
use App\Modules\Cart\Models\Cart;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CartDrawerTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $cheapVariant;

    private ProductVariant $expensiveVariant;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create([
            'name' => 'Accessories',
            'slug' => 'accessories',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Anker',
            'slug' => 'anker',
            'country_of_origin' => 'CN',
            'is_active' => true,
        ]);

        $productA = Product::create([
            'name' => 'USB-C Cable 100W',
            'slug' => 'usbc-cable-100w',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'origin_country' => 'China',
            'is_published' => true,
        ]);

        $this->cheapVariant = ProductVariant::create([
            'product_id' => $productA->id,
            'sku' => 'ANK-CABLE-1M',
            'barcode_ean' => '8901234567890',
            'attributes' => ['length' => '1m', 'color' => 'Black'],
            'purchase_cost_bdt' => '400.0000',
            'selling_price_bdt' => '600.0000',
            'is_active' => true,
        ]);

        $productB = Product::create([
            'name' => 'GaN Prime Charger 65W',
            'slug' => 'gan-prime-charger-65w',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'origin_country' => 'China',
            'is_published' => true,
        ]);

        $this->expensiveVariant = ProductVariant::create([
            'product_id' => $productB->id,
            'sku' => 'ANK-GAN-65W',
            'barcode_ean' => '8901234567891',
            'attributes' => ['color' => 'Silver'],
            'purchase_cost_bdt' => '2200.0000',
            'selling_price_bdt' => '3200.0000',
            'is_active' => true,
        ]);
    }

    public function test_can_fetch_empty_cart_drawer_and_receives_guest_cookie(): void
    {
        $response = $this->getJson('/cart/drawer');

        $response->assertOk()
            ->assertCookie('ecommerce_guest_token')
            ->assertJsonPath('data.total_items_count', 0)
            ->assertJsonPath('data.subtotal_bdt', '0.0000')
            ->assertJsonPath('data.free_shipping.threshold_bdt', CartResource::FREE_SHIPPING_THRESHOLD)
            ->assertJsonPath('data.free_shipping.is_unlocked', false)
            ->assertJsonPath('data.free_shipping.amount_remaining_bdt', '2000.0000')
            ->assertJsonPath('data.free_shipping.progress_percent', 0);
    }

    public function test_can_add_item_to_cart_via_api(): void
    {
        $response = $this->postJson('/cart/items', [
            'product_variant_id' => $this->cheapVariant->id,
            'quantity' => 2,
        ]);

        $response->assertOk()
            ->assertCookie('ecommerce_guest_token')
            ->assertJsonPath('data.total_items_count', 2)
            ->assertJsonPath('data.subtotal_bdt', '1200.0000')
            ->assertJsonPath('data.free_shipping.is_unlocked', false)
            ->assertJsonPath('data.free_shipping.amount_remaining_bdt', '800.0000')
            ->assertJsonPath('data.free_shipping.progress_percent', 60);

        $response->assertJsonCount(1, 'data.items');
        $this->assertEquals($this->cheapVariant->id, $response->json('data.items.0.product_variant_id'));
        $this->assertEquals(2, $response->json('data.items.0.quantity'));
    }

    public function test_can_update_quantity_and_recalculates_totals(): void
    {
        // 1. Add 1 item
        $addResponse = $this->postJson('/cart/items', [
            'product_variant_id' => $this->cheapVariant->id,
            'quantity' => 1,
        ]);
        $guestToken = $addResponse->json('data.guest_token');

        // 2. Update quantity to 3
        $updateResponse = $this->withHeader('X-Guest-Token', (string) $guestToken)
            ->patchJson('/cart/items/'.$this->cheapVariant->id, [
                'quantity' => 3,
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.total_items_count', 3)
            ->assertJsonPath('data.subtotal_bdt', '1800.0000')
            ->assertJsonPath('data.free_shipping.progress_percent', 90);
    }

    public function test_can_remove_item_from_cart(): void
    {
        // 1. Add 1 item
        $addResponse = $this->postJson('/cart/items', [
            'product_variant_id' => $this->cheapVariant->id,
            'quantity' => 1,
        ]);
        $guestToken = $addResponse->json('data.guest_token');

        // 2. Remove item
        $removeResponse = $this->withHeader('X-Guest-Token', (string) $guestToken)
            ->deleteJson('/cart/items/'.$this->cheapVariant->id);

        $removeResponse->assertOk()
            ->assertJsonPath('data.total_items_count', 0)
            ->assertJsonPath('data.subtotal_bdt', '0.0000')
            ->assertJsonCount(0, 'data.items');
    }

    public function test_free_shipping_unlocked_when_threshold_exceeded(): void
    {
        $response = $this->postJson('/cart/items', [
            'product_variant_id' => $this->expensiveVariant->id,
            'quantity' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.subtotal_bdt', '3200.0000')
            ->assertJsonPath('data.free_shipping.is_unlocked', true)
            ->assertJsonPath('data.free_shipping.amount_remaining_bdt', '0.0000')
            ->assertJsonPath('data.free_shipping.progress_percent', 100);
    }

    public function test_authenticated_user_persists_cart_under_user_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/cart/items', [
            'product_variant_id' => $this->cheapVariant->id,
            'quantity' => 1,
        ]);

        $response->assertOk();

        $cart = Cart::where('user_id', $user->id)->first();
        $this->assertNotNull($cart);
        $this->assertEquals(1, $cart->total_items_count);
    }
}

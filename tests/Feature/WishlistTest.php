<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Customer\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsStorefrontFixtures;
use Tests\TestCase;

final class WishlistTest extends TestCase
{
    use BuildsStorefrontFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildStorefrontFixtures();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->post("/account/wishlist/{$this->product->id}")->assertRedirect('/account/login');
        $this->assertSame(0, WishlistItem::query()->count());
    }

    public function test_customer_can_toggle_and_view_wishlist(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->post("/account/wishlist/{$this->product->id}")->assertSessionHas('success');
        $this->assertSame(1, $customer->wishlistItems()->count());

        $this->actingAs($customer)->get('/products/'.$this->product->slug)
            ->assertInertia(fn (Assert $page) => $page->where('isWishlisted', true));

        $this->actingAs($customer)->get('/account/wishlist')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Storefront/Account/Wishlist')
                ->has('items', 1)
                ->where('items.0.slug', $this->product->slug));

        // Second toggle removes it
        $this->actingAs($customer)->post("/account/wishlist/{$this->product->id}");
        $this->assertSame(0, $customer->wishlistItems()->count());
    }

    public function test_unpublished_products_cannot_be_wishlisted(): void
    {
        $this->product->update(['is_published' => false]);

        $this->actingAs(User::factory()->create())
            ->post("/account/wishlist/{$this->product->id}")
            ->assertNotFound();
    }
}

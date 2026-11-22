<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\ProductReview;
use App\Modules\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsStorefrontFixtures;
use Tests\TestCase;

final class ProductReviewSubmissionTest extends TestCase
{
    use BuildsStorefrontFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildStorefrontFixtures();
    }

    public function test_product_page_shows_real_zero_counts_instead_of_placeholders(): void
    {
        $this->get('/products/'.$this->product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Storefront/ProductDetail')
                ->where('product.review_count', 0)
                ->where('product.average_rating', 0)
                ->has('reviews', 0)
                ->has('shippingRates.inside_dhaka')
                ->where('seo.canonical', route('storefront.product.show', ['slug' => $this->product->slug]))
                ->missing('seo.json_ld.0.aggregateRating'));
    }

    public function test_guest_review_is_stored_as_pending_and_hidden(): void
    {
        $this->post('/products/'.$this->product->slug.'/reviews', $this->reviewPayload([
            'customer_name' => 'Farhana',
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $review = ProductReview::query()->firstOrFail();
        $this->assertSame(ProductReview::STATUS_PENDING, $review->status);
        $this->assertFalse($review->is_verified_purchase);

        $this->get('/products/'.$this->product->slug)
            ->assertInertia(fn (Assert $page) => $page->where('product.review_count', 0)->has('reviews', 0));
    }

    public function test_approved_reviews_drive_rating_and_json_ld(): void
    {
        foreach ([5, 4] as $rating) {
            ProductReview::create([
                'product_id' => $this->product->id,
                'customer_name' => 'Buyer '.$rating,
                'rating' => $rating,
                'review_text' => 'Good quality bag, arrived quickly.',
                'status' => ProductReview::STATUS_APPROVED,
            ]);
        }

        $this->get('/products/'.$this->product->slug)
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.review_count', 2)
                ->where('product.average_rating', 4.5)
                ->has('reviews', 2)
                ->where('seo.json_ld.0.aggregateRating.reviewCount', 2));
    }

    public function test_review_from_customer_with_delivered_order_is_verified_purchase(): void
    {
        $customer = User::factory()->create();
        $this->createOrder(['user_id' => $customer->id, 'status' => Order::STATUS_DELIVERED]);

        $this->actingAs($customer)
            ->post('/products/'.$this->product->slug.'/reviews', $this->reviewPayload())
            ->assertSessionHasNoErrors();

        $review = ProductReview::query()->firstOrFail();
        $this->assertTrue($review->is_verified_purchase);
        $this->assertSame($customer->id, $review->user_id);
        $this->assertSame($customer->name, $review->customer_name);
    }

    public function test_customer_cannot_review_same_product_twice(): void
    {
        $customer = User::factory()->create();
        $url = '/products/'.$this->product->slug.'/reviews';

        $this->actingAs($customer)->post($url, $this->reviewPayload())->assertSessionHasNoErrors();
        $this->actingAs($customer)->post($url, $this->reviewPayload())->assertSessionHasErrors('review_text');

        $this->assertSame(1, ProductReview::query()->count());
    }

    public function test_honeypot_blocks_bot_reviews(): void
    {
        $this->post('/products/'.$this->product->slug.'/reviews', $this->reviewPayload([
            'customer_name' => 'Bot',
            'user_company_fax' => 'spam',
        ]))->assertSessionHasErrors('bot_protection');

        $this->assertSame(0, ProductReview::query()->count());
    }

    public function test_staff_can_approve_a_pending_review(): void
    {
        $review = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_name' => 'Sadia',
            'rating' => 5,
            'review_text' => 'Beautiful finish and genuine leather.',
            'status' => ProductReview::STATUS_PENDING,
        ]);

        $this->actingAsStaffWith(['catalog.view'])->get('/admin/reviews')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Catalog/ReviewIndex')->where('counts.pending', 1));

        $this->actingAsStaffWith(['catalog.view'])
            ->post("/admin/reviews/{$review->id}/moderate", ['status' => 'approved'])
            ->assertForbidden();

        $this->actingAsStaffWith(['catalog.view', 'catalog.edit'])
            ->post("/admin/reviews/{$review->id}/moderate", ['status' => 'approved'])
            ->assertRedirect();

        $this->assertSame(ProductReview::STATUS_APPROVED, $review->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function reviewPayload(array $overrides = []): array
    {
        return array_merge([
            'rating' => 5,
            'title' => 'Love it',
            'review_text' => 'Excellent stitching and the colour is exactly as shown.',
            'user_company_fax' => '',
            '_form_timing_token' => $this->humanTimingToken(),
        ], $overrides);
    }
}

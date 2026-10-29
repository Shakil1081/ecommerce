<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Actions\ModerateProductReviewAction;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProductReviewTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'Audio', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Sony', 'origin_country' => 'Japan', 'is_active' => true]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Sony WH-1000XM5 Wireless Headphones',
            'hs_code' => '8518.30.00',
            'is_published' => true,
        ]);
    }

    public function test_can_submit_review_and_moderate_approval(): void
    {
        $moderator = User::factory()->create();
        $moderateAction = new ModerateProductReviewAction;

        // 1. Customer submits a 5-star review
        $review = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_name' => 'Tanvir Rahman',
            'customer_email' => 'tanvir@gmail.com',
            'rating' => 5,
            'title' => 'Unbelievable noise cancelling!',
            'review_text' => 'Direct genuine import unit. Sound quality and ANC are unmatched.',
            'is_verified_purchase' => true,
            'status' => ProductReview::STATUS_PENDING,
        ]);

        $this->assertEquals(ProductReview::STATUS_PENDING, $review->status);
        $this->assertTrue($review->is_verified_purchase);

        // Pending review must NOT be included in approvedReviews
        $this->assertEquals(0, $this->product->review_count);
        $this->assertEquals(0.0, $this->product->average_rating);

        // 2. Staff moderator approves the review
        $approvedReview = $moderateAction($review, ProductReview::STATUS_APPROVED, $moderator->id);

        $this->assertEquals(ProductReview::STATUS_APPROVED, $approvedReview->status);
        $this->assertEquals($moderator->id, $approvedReview->moderated_by_user_id);
        $this->assertNotNull($approvedReview->moderated_at);

        // Now review appears on product
        $this->product->refresh();
        $this->assertEquals(1, $this->product->review_count);
        $this->assertEquals(5.0, $this->product->average_rating);
    }

    public function test_average_rating_and_review_count_calculation(): void
    {
        $moderateAction = new ModerateProductReviewAction;

        // Review 1: 5 stars
        $r1 = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_name' => 'Shohel Rana',
            'rating' => 5,
            'review_text' => 'Outstanding battery life.',
            'status' => ProductReview::STATUS_PENDING,
        ]);
        $moderateAction($r1, ProductReview::STATUS_APPROVED);

        // Review 2: 4 stars
        $r2 = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_name' => 'Sadia Islam',
            'rating' => 4,
            'review_text' => 'A bit pricey but genuine import.',
            'status' => ProductReview::STATUS_PENDING,
        ]);
        $moderateAction($r2, ProductReview::STATUS_APPROVED);

        // Review 3: 1 star rejected (e.g. spam)
        $r3 = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_name' => 'Spammer',
            'rating' => 1,
            'review_text' => 'Free coins visit scam-site.com',
            'status' => ProductReview::STATUS_PENDING,
        ]);
        $moderateAction($r3, ProductReview::STATUS_REJECTED);

        $this->product->refresh();

        // Only 2 approved reviews (5 + 4) / 2 = 4.5
        $this->assertEquals(2, $this->product->review_count);
        $this->assertEquals(4.5, $this->product->average_rating);
    }

    public function test_moderation_rejects_invalid_status(): void
    {
        $review = ProductReview::create([
            'product_id' => $this->product->id,
            'customer_name' => 'Test User',
            'rating' => 3,
            'review_text' => 'Average product.',
            'status' => ProductReview::STATUS_PENDING,
        ]);

        $action = new ModerateProductReviewAction;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid review moderation status');

        $action($review, 'invalid_status');
    }
}


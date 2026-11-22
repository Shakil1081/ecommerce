<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductReview;
use App\Modules\Order\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Storefront review submission. Reviews are always stored as `pending` and only appear on the
 * product page after a staff member approves them (Admin > Catalog > Product Reviews).
 */
final class ProductReviewController extends Controller
{
    public function store(Request $request, string $slug): RedirectResponse
    {
        $product = Product::query()->where('slug', $slug)->where('is_published', true)->firstOrFail();

        /** @var User|null $user */
        $user = $request->user();

        $validated = $request->validate([
            'customer_name' => [$user ? 'nullable' : 'required', 'string', 'min:2', 'max:100'],
            'customer_email' => ['nullable', 'email', 'max:150'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:150'],
            'review_text' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        if ($user !== null) {
            $alreadyReviewed = ProductReview::query()
                ->where('product_id', $product->id)
                ->where('user_id', $user->id)
                ->where('status', '!=', ProductReview::STATUS_REJECTED)
                ->exists();

            if ($alreadyReviewed) {
                throw ValidationException::withMessages([
                    'review_text' => 'You have already reviewed this product.',
                ]);
            }
        }

        $deliveredOrderId = $user !== null ? $this->deliveredOrderIdFor($user, $product) : null;

        ProductReview::create([
            'product_id' => $product->id,
            'user_id' => $user?->id,
            'order_id' => $deliveredOrderId,
            'customer_name' => $user?->name ?? $validated['customer_name'],
            'customer_email' => $user?->email ?? ($validated['customer_email'] ?? null),
            'rating' => (int) $validated['rating'],
            'title' => $validated['title'] ?? null,
            'review_text' => $validated['review_text'],
            'is_verified_purchase' => $deliveredOrderId !== null,
            'status' => ProductReview::STATUS_PENDING,
        ]);

        return back()->with('success', 'Thank you! Your review will appear after our team checks it.');
    }

    /**
     * A review is a "verified purchase" only when the customer has a delivered order containing the product.
     */
    private function deliveredOrderIdFor(User $user, Product $product): ?int
    {
        $orderId = Order::query()
            ->where('user_id', $user->id)
            ->where('status', Order::STATUS_DELIVERED)
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->latest('id')
            ->value('id');

        return $orderId !== null ? (int) $orderId : null;
    }
}

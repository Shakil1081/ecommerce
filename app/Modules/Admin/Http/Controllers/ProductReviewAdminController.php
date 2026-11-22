<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\LogAuditTrailAction;
use App\Modules\Catalog\Actions\ModerateProductReviewAction;
use App\Modules\Catalog\Models\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProductReviewAdminController extends Controller
{
    private const STATUSES = [
        ProductReview::STATUS_PENDING,
        ProductReview::STATUS_APPROVED,
        ProductReview::STATUS_REJECTED,
    ];

    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', ProductReview::STATUS_PENDING);
        if (! in_array($status, self::STATUSES, true)) {
            $status = ProductReview::STATUS_PENDING;
        }

        $reviews = ProductReview::query()
            ->with(['product:id,name,slug', 'moderatedBy:id,name'])
            ->where('status', $status)
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ProductReview $review): array => [
                'id' => $review->id,
                'product_name' => $review->product?->name,
                'product_slug' => $review->product?->slug,
                'customer_name' => $review->customer_name,
                'customer_email' => $review->customer_email,
                'rating' => $review->rating,
                'title' => $review->title,
                'review_text' => $review->review_text,
                'is_verified_purchase' => $review->is_verified_purchase,
                'status' => $review->status,
                'created_at' => $review->created_at?->format('d M Y, h:i A'),
                'moderated_by' => $review->moderatedBy?->name,
            ]);

        $counts = ProductReview::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return Inertia::render('Admin/Catalog/ReviewIndex', [
            'reviews' => $reviews,
            'status' => $status,
            'counts' => [
                'pending' => (int) ($counts[ProductReview::STATUS_PENDING] ?? 0),
                'approved' => (int) ($counts[ProductReview::STATUS_APPROVED] ?? 0),
                'rejected' => (int) ($counts[ProductReview::STATUS_REJECTED] ?? 0),
            ],
        ]);
    }

    public function moderate(
        Request $request,
        ProductReview $review,
        ModerateProductReviewAction $moderate,
        LogAuditTrailAction $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.ProductReview::STATUS_APPROVED.','.ProductReview::STATUS_REJECTED],
        ]);

        $oldStatus = $review->status;
        $moderate($review, $validated['status'], $request->user()?->id);

        $audit(
            event: 'catalog.review.moderated',
            description: "Product review {$review->id} marked {$validated['status']}",
            auditable: $review,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $validated['status']],
        );

        return back()->with('success', $validated['status'] === ProductReview::STATUS_APPROVED
            ? 'Review approved and now visible on the product page.'
            : 'Review rejected.');
    }
}

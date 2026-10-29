<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ProductReview;
use InvalidArgumentException;

class ModerateProductReviewAction
{
    /**
     * Approve or reject a customer product review.
     *
     * @param  string  $status  'approved' or 'rejected'
     *
     * @throws InvalidArgumentException
     */
    public function __invoke(
        ProductReview $review,
        string $status,
        ?int $moderatorUserId = null
    ): ProductReview {
        $allowedStatuses = [ProductReview::STATUS_APPROVED, ProductReview::STATUS_REJECTED];

        if (! in_array($status, $allowedStatuses, true)) {
            throw new InvalidArgumentException("Invalid review moderation status: '{$status}'. Allowed: ".implode(', ', $allowedStatuses));
        }

        $review->update([
            'status' => $status,
            'moderated_by_user_id' => $moderatorUserId,
            'moderated_at' => now(),
        ]);

        return $review->refresh();
    }
}


<?php

declare(strict_types=1);

namespace App\Modules\AI\DTOs;

final readonly class RestockInsightDto
{
    public function __construct(
        public string $sku,
        public string $productName,
        public int $currentStock,
        public float $dailyVelocity,
        public int $daysRemaining,
        public int $suggestedReorderQuantity,
        public string $urgencyLevel,
        public string $aiRationale,
    ) {}
}

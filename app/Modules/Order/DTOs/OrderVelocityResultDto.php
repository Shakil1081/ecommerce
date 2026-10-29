<?php

declare(strict_types=1);

namespace App\Modules\Order\DTOs;

final readonly class OrderVelocityResultDto
{
    /**
     * @param  array<string>  $violations
     */
    public function __construct(
        public bool $isAllowed,
        public int $activeCodOrdersCount,
        public int $dailyOrdersFromIpCount,
        public int $dailyOrdersFromDeviceCount,
        public array $violations = [],
        public ?string $primaryReason = null
    ) {}
}

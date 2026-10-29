<?php

declare(strict_types=1);

namespace App\Modules\Customer\DTOs;

use App\Modules\Customer\Models\CustomerFraudList;

final readonly class CustomerFraudListCheckResultDto
{
    public function __construct(
        public bool $isBlacklisted,
        public bool $isWhitelisted,
        public ?CustomerFraudList $matchedBlacklistEntry = null,
        public ?CustomerFraudList $matchedWhitelistEntry = null,
        public ?string $reason = null
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTOs;

readonly class SmsBalanceResultDto
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public bool $success,
        public ?float $balanceBdt,
        public string $message,
        public array $details = [],
    ) {}
}

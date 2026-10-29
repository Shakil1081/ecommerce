<?php

declare(strict_types=1);

namespace App\Modules\Common\DTOs;

final readonly class CaptchaVerificationResultDto
{
    /**
     * @param  array<string>  $errorCodes
     */
    public function __construct(
        public bool $success,
        public ?float $score = null,
        public ?string $provider = null,
        public ?string $hostname = null,
        public array $errorCodes = [],
        public ?string $errorMessage = null
    ) {}
}

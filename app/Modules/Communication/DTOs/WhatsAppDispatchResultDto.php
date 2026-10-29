<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTOs;

readonly class WhatsAppDispatchResultDto
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public bool $success,
        public ?string $messageId,
        public string $message,
        public array $rawResponse = [],
    ) {}
}

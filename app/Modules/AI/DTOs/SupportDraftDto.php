<?php

declare(strict_types=1);

namespace App\Modules\AI\DTOs;

final readonly class SupportDraftDto
{
    public function __construct(
        public string $subject,
        public string $body,
        public string $suggestedAction,
        public bool $requiresHumanReview,
        public string $tone = 'professional_empathetic',
    ) {}
}

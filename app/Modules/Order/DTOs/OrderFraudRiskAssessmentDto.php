<?php

declare(strict_types=1);

namespace App\Modules\Order\DTOs;

final readonly class OrderFraudRiskAssessmentDto
{
    public const LEVEL_LOW = 'low';

    public const LEVEL_MEDIUM = 'medium';

    public const LEVEL_HIGH = 'high';

    public const LEVEL_BLACKLISTED = 'blacklisted';

    /**
     * @param  array<string, mixed>  $signals
     * @param  array<string>  $reasons
     */
    public function __construct(
        public int $score,
        public string $riskLevel,
        public bool $isCodAllowed,
        public bool $requiresOtp,
        public bool $requiresAdvanceDeliveryCharge,
        public array $signals = [],
        public array $reasons = []
    ) {}

    /**
     * Determine risk level from numeric score and courier return intelligence.
     */
    public static function determineRiskLevel(int $score, bool $isCourierHighRisk = false): string
    {
        if ($score >= 100) {
            return self::LEVEL_BLACKLISTED;
        }

        if ($score >= 70 || $isCourierHighRisk) {
            return self::LEVEL_HIGH;
        }

        if ($score >= 30) {
            return self::LEVEL_MEDIUM;
        }

        return self::LEVEL_LOW;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Common\Exceptions;

use Illuminate\Validation\ValidationException;

final class BotSubmissionDetectedException extends ValidationException
{
    public static function honeypotTriggered(string $field = 'user_company_fax'): self
    {
        return self::withMessages([
            'bot_protection' => ["Automated bot submission detected via honeypot trap [{$field}]."],
        ]);
    }

    public static function tooFast(float $elapsed, float $minimum): self
    {
        $elapsedFormatted = number_format($elapsed, 2);

        return self::withMessages([
            'bot_protection' => ["Form submission completed unnaturally fast ({$elapsedFormatted}s, minimum required is {$minimum}s). Automated script suspected."],
        ]);
    }

    public static function invalidToken(): self
    {
        return self::withMessages([
            'bot_protection' => ['Invalid or expired form security timing token.'],
        ]);
    }
}

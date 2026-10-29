<?php

declare(strict_types=1);

namespace App\Modules\Customer\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class BangladeshPhoneNumberRule implements ValidationRule
{
    /**
     * Map of 3-digit prefixes to Bangladesh telecom operators.
     */
    public const OPERATORS = [
        '013' => 'Grameenphone',
        '017' => 'Grameenphone',
        '014' => 'Banglalink',
        '019' => 'Banglalink',
        '015' => 'Teletalk',
        '016' => 'Robi / Airtel',
        '018' => 'Robi / Airtel',
    ];

    /**
     * Known dummy / placeholder test numbers commonly submitted by bots or test scripts.
     */
    private const KNOWN_DUMMY_NUMBERS = [
        '01700000000',
        '01800000000',
        '01900000000',
        '01500000000',
        '01600000000',
        '01300000000',
        '01400000000',
        '01712345678',
        '01812345678',
        '01912345678',
        '01512345678',
        '01612345678',
        '01312345678',
        '01412345678',
        '01787654321',
        '01887654321',
        '01987654321',
        '01711111111',
        '01888888888',
        '01999999999',
    ];

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail("The {$attribute} must be a valid string.");

            return;
        }

        $cleaned = self::cleanNumber($value);

        if (! self::isValidFormat($cleaned)) {
            $fail("The {$attribute} must be a valid Bangladesh mobile number (e.g., 017XXXXXXXX or +88017XXXXXXXX).");

            return;
        }

        $localNumber = self::toLocalFormat($cleaned);

        if (self::isDummyOrRepeatingNumber($localNumber)) {
            $fail("The {$attribute} appears to be an invalid or dummy test phone number.");
        }
    }

    /**
     * Clean phone string by stripping non-digit characters except leading plus.
     */
    public static function cleanNumber(string $phone): string
    {
        $hasPlus = str_starts_with(trim($phone), '+');
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return $hasPlus ? '+'.$digits : $digits;
    }

    /**
     * Validate whether phone number matches Bangladesh operator formats.
     */
    public static function isValidFormat(string $cleaned): bool
    {
        // Standard patterns: +8801[3-9]\d{8}, 8801[3-9]\d{8}, 01[3-9]\d{8}
        return (bool) preg_match('/^(?:\+?8801|01)[3-9]\d{8}$/', $cleaned);
    }

    /**
     * Normalize any valid BD phone number to standard 11-digit local format: 01XXXXXXXXX.
     */
    public static function toLocalFormat(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '8801')) {
            return substr($digits, 2);
        }

        if (str_starts_with($digits, '01')) {
            return $digits;
        }

        return $digits;
    }

    /**
     * Normalize any valid BD phone number to international E.164 format: +8801XXXXXXXXX.
     */
    public static function toE164Format(string $phone): string
    {
        $local = self::toLocalFormat($phone);

        return '+88'.$local;
    }

    /**
     * Identify the Bangladesh mobile telecom operator from phone number.
     */
    public static function getOperator(string $phone): ?string
    {
        $local = self::toLocalFormat($phone);

        if (strlen($local) < 3) {
            return null;
        }

        $prefix = substr($local, 0, 3);

        return self::OPERATORS[$prefix] ?? null;
    }

    /**
     * Check if phone number is completely valid (format + non-dummy).
     */
    public static function isValid(string $phone): bool
    {
        $cleaned = self::cleanNumber($phone);

        if (! self::isValidFormat($cleaned)) {
            return false;
        }

        $local = self::toLocalFormat($cleaned);

        return ! self::isDummyOrRepeatingNumber($local);
    }

    /**
     * Detect dummy repeating digits, simple sequential test patterns, or known dummy numbers.
     */
    public static function isDummyOrRepeatingNumber(string $localNumber): bool
    {
        if (in_array($localNumber, self::KNOWN_DUMMY_NUMBERS, true)) {
            return true;
        }

        if (strlen($localNumber) !== 11) {
            return true;
        }

        // Check unique digits: real numbers almost never have <= 2 unique characters
        $uniqueDigits = count(array_unique(str_split($localNumber)));
        if ($uniqueDigits <= 2) {
            return true;
        }

        // Subscriber part: 8 digits after the 3-digit prefix (e.g. for 01711111111, subscriber is 11111111)
        $subscriber = substr($localNumber, 3);

        // 1. All identical digits in subscriber portion (e.g. 11111111, 00000000, 99999999)
        if (preg_match('/^(\d)\1{7}$/', $subscriber)) {
            return true;
        }

        // 2. 6 or more identical digits consecutively anywhere in the number (e.g. 01700000012, 01711111123)
        if (preg_match('/(\d)\1{5,}/', $localNumber)) {
            return true;
        }

        // 3. Sequential ascending patterns (e.g. 12345678, 23456789, 01234567)
        if (self::isSequentialSequence($subscriber, true)) {
            return true;
        }

        // 4. Sequential descending patterns (e.g. 87654321, 98765432, 76543210)
        if (self::isSequentialSequence($subscriber, false)) {
            return true;
        }

        return false;
    }

    /**
     * Check if 8-digit subscriber part is an incremental or decremental sequence.
     */
    private static function isSequentialSequence(string $digits, bool $ascending): bool
    {
        if (strlen($digits) !== 8) {
            return false;
        }

        $chars = str_split($digits);
        for ($i = 0; $i < count($chars) - 1; $i++) {
            $current = (int) $chars[$i];
            $next = (int) $chars[$i + 1];

            $expected = $ascending ? ($current + 1) % 10 : ($current - 1 + 10) % 10;
            if ($next !== $expected) {
                return false;
            }
        }

        return true;
    }
}

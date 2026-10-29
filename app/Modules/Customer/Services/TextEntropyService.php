<?php

declare(strict_types=1);

namespace App\Modules\Customer\Services;

final class TextEntropyService
{
    /**
     * Common keyboard walk sequences indicating random keyboard spam.
     */
    private const KEYBOARD_PATTERNS = [
        'asdf',
        'dfgh',
        'ghjk',
        'hjkl',
        'qwerty',
        'werty',
        'ertyu',
        'rtyui',
        'tyuio',
        'yuio',
        'uiop',
        'zxcv',
        'xcvb',
        'cvbn',
        'vbnm',
        '1234',
        '2345',
        '3456',
        '4567',
        '5678',
        '6789',
        '7890',
    ];

    /**
     * Explicit keywords used by fake orders and automated bots.
     */
    private const DUMMY_KEYWORDS = [
        'test',
        'testing',
        'fake',
        'none',
        'no address',
        'na',
        'n/a',
        'null',
        'dummy',
        'asdf',
        'xyz',
        'unknown',
        'nothing',
    ];

    /**
     * Calculate the Shannon entropy of a string (in bits per character).
     *
     * H(X) = - SUM( P(x) * log2(P(x)) )
     */
    public function calculateEntropy(string $text): float
    {
        $normalized = trim($text);
        $length = mb_strlen($normalized, 'UTF-8');

        if ($length === 0) {
            return 0.0;
        }

        // Count frequency of each character (multi-byte safe)
        $charCounts = [];
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($normalized, $i, 1, 'UTF-8');
            $charCounts[$char] = ($charCounts[$char] ?? 0) + 1;
        }

        $entropy = 0.0;
        foreach ($charCounts as $count) {
            $p = $count / $length;
            $entropy -= $p * log($p, 2);
        }

        return round($entropy, 4);
    }

    /**
     * Compute a gibberish risk score between 0 (completely natural) and 100 (definite gibberish/spam).
     */
    public function calculateGibberishScore(string $text): int
    {
        $evaluation = $this->evaluate($text);

        return $evaluation['gibberish_score'];
    }

    /**
     * Determine if a given string is considered gibberish or fake input.
     */
    public function isGibberish(string $text, int $thresholdScore = 50): bool
    {
        return $this->calculateGibberishScore($text) >= $thresholdScore;
    }

    /**
     * Comprehensive multi-factor evaluation of text input.
     *
     * @return array{
     *     text: string,
     *     entropy: float,
     *     gibberish_score: int,
     *     is_gibberish: bool,
     *     reasons: array<string>
     * }
     */
    public function evaluate(string $text): array
    {
        $raw = trim($text);
        $lower = mb_strtolower($raw, 'UTF-8');
        $length = mb_strlen($lower, 'UTF-8');
        $entropy = $this->calculateEntropy($raw);

        $score = 0;
        $reasons = [];

        if ($length === 0) {
            return [
                'text' => $raw,
                'entropy' => 0.0,
                'gibberish_score' => 100,
                'is_gibberish' => true,
                'reasons' => ['Text is empty'],
            ];
        }

        // 1. Check known dummy keywords
        foreach (self::DUMMY_KEYWORDS as $keyword) {
            if ($lower === $keyword || preg_match('/\b'.preg_quote($keyword, '/').'\b/u', $lower)) {
                $score += 80;
                $reasons[] = "Contains dummy keyword: '{$keyword}'";
                break;
            }
        }

        // 2. Too short (< 3 characters for names or addresses)
        if ($length < 3) {
            $score += 45;
            $reasons[] = 'Text length is under 3 characters';
        }

        // 3. Low unique character ratio (e.g. 'aaaaaa', '111111', 'abababab')
        $chars = [];
        for ($i = 0; $i < $length; $i++) {
            $chars[] = mb_substr($lower, $i, 1, 'UTF-8');
        }
        $uniqueCount = count(array_unique($chars));
        $uniqueRatio = $uniqueCount / $length;

        if ($length >= 5 && $uniqueRatio < 0.35) {
            $score += 50;
            $reasons[] = 'Extremely low unique character ratio (< 35%)';
        }

        // 4. Repeated consecutive identical characters (>= 4 in a row, e.g. 'aaaa', 'xxxx')
        if (preg_match('/(.)\1{3,}/u', $lower)) {
            $score += 60;
            $reasons[] = 'Contains 4 or more consecutive identical characters';
        }

        // 5. Keyboard walk detection (e.g. 'asdfgh', 'qwerty', 'zxcvbn')
        foreach (self::KEYBOARD_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                $score += 55;
                $reasons[] = "Contains keyboard walk pattern: '{$pattern}'";
                break;
            }
        }

        // 6. Latin alphabetic consonant-to-vowel ratio
        // If string contains mostly English Latin characters (length >= 5) and 0 vowels
        $latinLetters = preg_replace('/[^a-z]/', '', $lower) ?? '';
        $latinCount = strlen($latinLetters);
        if ($latinCount >= 5) {
            $vowelCount = preg_match_all('/[aeiouy]/', $latinLetters);
            if ($vowelCount === 0) {
                $score += 70;
                $reasons[] = 'Latin text of 5+ characters contains zero vowels (consonant mash)';
            } elseif (($vowelCount / $latinCount) < 0.15 && $latinCount >= 8) {
                $score += 40;
                $reasons[] = 'Abnormally low vowel ratio (< 15%) in Latin text';
            }
        }

        // 7. Shannon Entropy checks
        // Long strings with abnormally low entropy (e.g., repeating loops like 'abababab')
        if ($length >= 8 && $entropy < 1.6) {
            $score += 45;
            $reasons[] = "Abnormally low Shannon entropy ({$entropy} bits/char)";
        }

        // Cap score at 100
        $finalScore = min(100, $score);

        return [
            'text' => $raw,
            'entropy' => $entropy,
            'gibberish_score' => $finalScore,
            'is_gibberish' => $finalScore >= 50,
            'reasons' => $reasons,
        ];
    }
}

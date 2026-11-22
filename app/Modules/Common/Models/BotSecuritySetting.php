<?php

declare(strict_types=1);

namespace App\Modules\Common\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $provider
 * @property string $site_key
 * @property string $secret_key
 * @property bool $is_active
 * @property string $score_threshold
 * @property array<string> $pages_enabled
 * @property CarbonInterface|null $last_tested_at
 * @property string|null $last_test_status
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class BotSecuritySetting extends Model
{
    use HasFactory;

    public const PROVIDER_RECAPTCHA_V2 = 'recaptcha_v2';

    public const PROVIDER_RECAPTCHA_V3 = 'recaptcha_v3';

    public const PROVIDER_TURNSTILE = 'turnstile';

    public const PAGE_CHECKOUT = 'checkout';

    public const PAGE_LOGIN = 'login';

    public const PAGE_REGISTER = 'register';

    public const PAGE_CONTACT = 'contact';

    public const PAGE_REVIEWS = 'reviews';

    public const ALL_PAGES = [
        self::PAGE_CHECKOUT,
        self::PAGE_LOGIN,
        self::PAGE_REGISTER,
        self::PAGE_CONTACT,
        self::PAGE_REVIEWS,
    ];

    protected $fillable = [
        'provider',
        'site_key',
        'secret_key',
        'is_active',
        'score_threshold',
        'pages_enabled',
        'last_tested_at',
        'last_test_status',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'score_threshold' => 'decimal:2',
            'pages_enabled' => 'array',
            'secret_key' => 'encrypted',
            'last_tested_at' => 'datetime',
        ];
    }

    /** Google's public reCAPTCHA v2 test keys: every challenge passes. Development only. */
    private const GOOGLE_TEST_SITE_KEY = '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI';

    private const GOOGLE_TEST_SECRET_KEY = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe';

    /**
     * Keys for the first bot-security record. Outside production, missing keys fall back to Google's
     * test keys. In production they never do: test keys always pass, so they would look like
     * protection without providing any. Returns null when nothing real is configured.
     *
     * @return array{provider: string, site_key: string, secret_key: string}|null
     */
    public static function initialKeys(): ?array
    {
        $siteKey = (string) config('security.captcha.site_key');
        $secretKey = (string) config('security.captcha.secret_key');

        if ($siteKey === '' || $secretKey === '') {
            if (app()->environment('production')) {
                return null;
            }

            $siteKey = self::GOOGLE_TEST_SITE_KEY;
            $secretKey = self::GOOGLE_TEST_SECRET_KEY;
        }

        return [
            'provider' => (string) config('security.captcha.provider', self::PROVIDER_RECAPTCHA_V2),
            'site_key' => $siteKey,
            'secret_key' => $secretKey,
        ];
    }

    /**
     * Get the currently active bot security configuration if enabled.
     */
    public static function getActive(): ?self
    {
        /** @var self|null $active */
        $active = self::query()->where('is_active', true)->first();

        if ($active !== null) {
            return $active;
        }

        // If the table is completely empty (first run), initialize from config/security.php
        $initialKeys = self::initialKeys();
        if ($initialKeys !== null && self::query()->count() === 0) {
            try {
                return self::create([
                    'provider' => $initialKeys['provider'],
                    'site_key' => $initialKeys['site_key'],
                    'secret_key' => $initialKeys['secret_key'],
                    'is_active' => true,
                    'score_threshold' => 0.50,
                    'pages_enabled' => [self::PAGE_LOGIN, self::PAGE_CHECKOUT, self::PAGE_REGISTER],
                ]);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Check if a given frontend page key is configured for bot protection.
     */
    public function isPageProtected(string $page): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return in_array($page, $this->pages_enabled ?? [], true);
    }

    /**
     * Return a masked secret key suitable for display in admin UI (e.g. ••••••••••••abcd).
     */
    public function maskedSecretKey(): string
    {
        $raw = (string) $this->secret_key;
        $len = strlen($raw);

        if ($len <= 4) {
            return '••••••••';
        }

        $lastFour = substr($raw, -4);

        return str_repeat('•', 12).$lastFour;
    }
}

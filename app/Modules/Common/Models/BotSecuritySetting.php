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

    /**
     * Get the currently active bot security configuration if enabled.
     */
    public static function getActive(): ?self
    {
        /** @var self|null $active */
        $active = self::query()->where('is_active', true)->first();

        return $active;
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

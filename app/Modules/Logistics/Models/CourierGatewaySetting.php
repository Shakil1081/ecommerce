<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CourierGatewaySetting extends Model
{
    public const CODE_STEADFAST = 'steadfast';

    public const CODE_PATHAO = 'pathao';

    public const CODE_REDX = 'redx';

    public const CODE_PAPERFLY = 'paperfly';

    public const CODE_SUNDARBAN = 'sundarban';

    public const SUPPORTED_COURIERS = [
        self::CODE_STEADFAST => 'Steadfast Courier',
        self::CODE_PATHAO => 'Pathao Courier',
        self::CODE_REDX => 'RedX Delivery',
        self::CODE_PAPERFLY => 'Paperfly Courier',
        self::CODE_SUNDARBAN => 'Sundarban Courier Service',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'code',
        'name',
        'is_active',
        'is_sandbox',
        'credentials',
        'base_url',
        'webhook_secret',
        'supported_features',
        'sort_order',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_sandbox' => 'boolean',
        'credentials' => 'encrypted:array',
        'supported_features' => 'array',
        'sort_order' => 'integer',
        'last_tested_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CourierGatewaySetting $model): void {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc');
    }

    public function getCredential(string $key, mixed $default = null): mixed
    {
        $creds = $this->credentials;
        if (! is_array($creds)) {
            return $default;
        }

        return $creds[$key] ?? $default;
    }

    /**
     * Check if minimum required credentials exist for the courier.
     */
    public function isConfigured(): bool
    {
        $creds = $this->credentials;
        if (! is_array($creds) || empty($creds)) {
            return false;
        }

        return match ($this->code) {
            self::CODE_STEADFAST => ! empty($creds['api_key']) && ! empty($creds['secret_key']),
            self::CODE_PATHAO => ! empty($creds['client_id']) && ! empty($creds['client_secret']) && ! empty($creds['username']) && ! empty($creds['password']),
            self::CODE_REDX => ! empty($creds['token']),
            self::CODE_PAPERFLY => ! empty($creds['username']) && ! empty($creds['password']),
            self::CODE_SUNDARBAN => ! empty($creds['api_key']),
            default => false,
        };
    }

    /**
     * Return credentials with sensitive values masked for UI display.
     *
     * @return array<string, mixed>
     */
    public function getMaskedCredentials(): array
    {
        $creds = $this->credentials;
        if (! is_array($creds)) {
            return [];
        }

        $masked = [];
        foreach ($creds as $key => $value) {
            if (! is_string($value) || $value === '') {
                $masked[$key] = '';

                continue;
            }

            // Mask everything except the last 4 characters for sensitive fields
            $len = strlen($value);
            if ($len <= 4) {
                $masked[$key] = '••••';
            } else {
                $masked[$key] = str_repeat('•', 8).substr($value, -4);
            }
        }

        return $masked;
    }

    public function consignments(): HasMany
    {
        return $this->hasMany(CourierConsignment::class, 'courier_code', 'code');
    }
}

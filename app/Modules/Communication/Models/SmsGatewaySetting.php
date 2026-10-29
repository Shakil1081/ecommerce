<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SmsGatewaySetting extends Model
{
    public const CODE_GREENWEB = 'greenweb';

    public const CODE_REVE = 'reve';

    public const CODE_TWILIO = 'twilio';

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
        'sender_id',
        'balance_bdt',
        'balance_updated_at',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
        'sort_order',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_sandbox' => 'boolean',
        'credentials' => 'encrypted:array',
        'balance_bdt' => 'decimal:4',
        'balance_updated_at' => 'datetime',
        'last_tested_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (SmsGatewaySetting $model): void {
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
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Retrieve a specific credential value.
     */
    public function getCredential(string $key, mixed $default = null): mixed
    {
        $creds = $this->credentials ?? [];

        return $creds[$key] ?? $default;
    }

    /**
     * Return credentials with sensitive values masked.
     *
     * @return array<string, string>
     */
    public function getMaskedCredentials(): array
    {
        $creds = $this->credentials ?? [];
        $masked = [];

        foreach ($creds as $key => $value) {
            if (! is_string($value) || empty($value)) {
                $masked[$key] = '';

                continue;
            }

            $len = strlen($value);
            if ($len <= 4) {
                $masked[$key] = '••••';
            } else {
                $masked[$key] = '••••••••'.substr($value, -4);
            }
        }

        return $masked;
    }

    public function isConfigured(): bool
    {
        $creds = $this->credentials ?? [];

        return match ($this->code) {
            self::CODE_GREENWEB => ! empty($creds['token']),
            self::CODE_REVE => ! empty($creds['api_key']) && ! empty($creds['secret_key']),
            self::CODE_TWILIO => ! empty($creds['account_sid']) && ! empty($creds['auth_token']) && ! empty($creds['from_number']),
            default => ! empty($creds),
        };
    }
}

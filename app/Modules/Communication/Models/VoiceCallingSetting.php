<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VoiceCallingSetting extends Model
{
    public const PROVIDER_TWILIO = 'twilio';

    public const PROVIDER_RETELL = 'retell';

    public const PROVIDER_VAPI = 'vapi';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'provider',
        'is_active',
        'is_sandbox',
        'credentials',
        'caller_id',
        'language',
        'dtmf_enabled',
        'speech_enabled',
        'retry_attempts',
        'script_template_bn',
        'script_template_en',
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
        'dtmf_enabled' => 'boolean',
        'speech_enabled' => 'boolean',
        'retry_attempts' => 'integer',
        'last_tested_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (VoiceCallingSetting $model): void {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getCredential(string $key, mixed $default = null): mixed
    {
        $creds = $this->credentials ?? [];

        return $creds[$key] ?? $default;
    }

    /**
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

        return match ($this->provider) {
            self::PROVIDER_TWILIO => ! empty($creds['account_sid']) && ! empty($creds['auth_token']) && ! empty($creds['caller_id']),
            self::PROVIDER_RETELL => ! empty($creds['api_key']) && ! empty($creds['agent_id']),
            self::PROVIDER_VAPI => ! empty($creds['api_key']) && ! empty($creds['assistant_id']),
            default => ! empty($creds),
        };
    }
}

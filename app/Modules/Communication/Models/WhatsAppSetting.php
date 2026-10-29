<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WhatsAppSetting extends Model
{
    protected $table = 'whatsapp_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'phone_number_id',
        'business_account_id',
        'access_token',
        'webhook_verify_token',
        'app_secret',
        'is_active',
        'is_sandbox',
        'api_version',
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
        'access_token' => 'encrypted',
        'app_secret' => 'encrypted',
        'last_tested_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (WhatsAppSetting $model): void {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isConfigured(): bool
    {
        return ! empty($this->phone_number_id) && ! empty($this->access_token);
    }

    public function getMaskedToken(): string
    {
        if (empty($this->access_token)) {
            return '';
        }

        $len = strlen($this->access_token);
        if ($len <= 4) {
            return '••••';
        }

        return '••••••••'.substr($this->access_token, -4);
    }
}

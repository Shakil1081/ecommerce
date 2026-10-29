<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MailSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'driver',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_address',
        'from_name',
        'is_active',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'port' => 'integer',
        'is_active' => 'boolean',
        'password' => 'encrypted',
        'last_tested_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (MailSetting $model): void {
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
        return ! empty($this->host) && ! empty($this->username) && ! empty($this->from_address);
    }

    public function getMaskedPassword(): string
    {
        if (empty($this->password)) {
            return '';
        }

        $len = strlen($this->password);
        if ($len <= 4) {
            return '••••';
        }

        return '••••••••'.substr($this->password, -4);
    }
}

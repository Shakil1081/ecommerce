<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SmsOtp extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'phone_number',
        'otp_code_hash',
        'purpose',
        'order_id',
        'expires_at',
        'verified_at',
        'attempts',
        'max_attempts',
        'ip_address',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (SmsOtp $model): void {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function hasExceededAttempts(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    public function verifyCode(string $code): bool
    {
        return Hash::check($code, $this->otp_code_hash);
    }
}

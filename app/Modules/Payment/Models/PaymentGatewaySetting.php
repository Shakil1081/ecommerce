<?php

declare(strict_types=1);

namespace App\Modules\Payment\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentGatewaySetting extends Model
{
    public const CODE_BKASH = 'bkash';

    public const CODE_NAGAD = 'nagad';

    public const CODE_SSLCOMMERZ = 'sslcommerz';

    public const CODE_STRIPE = 'stripe';

    public const CODE_COD = 'cod';

    public const CODE_BANK_TRANSFER = 'bank_transfer';

    public const SUPPORTED_GATEWAYS = [
        self::CODE_BKASH => 'bKash Tokenized Checkout',
        self::CODE_NAGAD => 'Nagad Digital Payment',
        self::CODE_SSLCOMMERZ => 'SSLCommerz Hosted Checkout',
        self::CODE_STRIPE => 'Stripe Credit / Debit Card',
        self::CODE_COD => 'Cash on Delivery (COD)',
        self::CODE_BANK_TRANSFER => 'Manual Bank Transfer',
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
        'surcharge_percentage',
        'min_order_amount',
        'max_order_amount',
        'sort_order',
        'instructions',
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
        'surcharge_percentage' => 'decimal:2',
        'min_order_amount' => 'decimal:4',
        'max_order_amount' => 'decimal:4',
        'sort_order' => 'integer',
        'last_tested_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PaymentGatewaySetting $model): void {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    /**
     * Scope to only active gateways.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by priority.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * Retrieve a specific credential key.
     */
    public function getCredential(string $key, mixed $default = null): mixed
    {
        $creds = $this->credentials;
        if (! is_array($creds)) {
            return $default;
        }

        return $creds[$key] ?? $default;
    }

    /**
     * Check if minimum required credentials exist for the gateway code.
     */
    public function isConfigured(): bool
    {
        $creds = $this->credentials;
        if (! is_array($creds) || empty($creds)) {
            return in_array($this->code, [self::CODE_COD, self::CODE_BANK_TRANSFER], true);
        }

        return match ($this->code) {
            self::CODE_BKASH => ! empty($creds['app_key']) && ! empty($creds['app_secret']) && ! empty($creds['username']) && ! empty($creds['password']),
            self::CODE_NAGAD => ! empty($creds['merchant_id']) && ! empty($creds['merchant_private_key']),
            self::CODE_SSLCOMMERZ => ! empty($creds['store_id']) && ! empty($creds['store_passwd']),
            self::CODE_STRIPE => ! empty($creds['secret_key']),
            default => true,
        };
    }

    /**
     * Return credentials with sensitive values masked for secure UI output.
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
}

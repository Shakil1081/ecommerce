<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use App\Models\User;
use App\Modules\Customer\Rules\BangladeshPhoneNumberRule;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $type
 * @property string|null $phone_number
 * @property string|null $ip_address
 * @property string|null $device_fingerprint
 * @property string|null $customer_name
 * @property string|null $reason
 * @property int|null $blocked_by_user_id
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property User|null $blockedBy
 */
class CustomerFraudList extends Model
{
    use HasFactory;

    public const TYPE_BLACKLIST = 'blacklist';

    public const TYPE_WHITELIST = 'whitelist';

    protected $fillable = [
        'type',
        'phone_number',
        'ip_address',
        'device_fingerprint',
        'customer_name',
        'reason',
        'blocked_by_user_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by_user_id');
    }

    /**
     * Scope for blacklist entries.
     *
     * @param  Builder<CustomerFraudList>  $query
     * @return Builder<CustomerFraudList>
     */
    public function scopeBlacklist(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_BLACKLIST);
    }

    /**
     * Scope for whitelist entries.
     *
     * @param  Builder<CustomerFraudList>  $query
     * @return Builder<CustomerFraudList>
     */
    public function scopeWhitelist(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_WHITELIST);
    }

    /**
     * Scope to match any provided customer identifier (phone, IP, or device fingerprint).
     *
     * @param  Builder<CustomerFraudList>  $query
     * @return Builder<CustomerFraudList>
     */
    public function scopeMatchIdentifier(
        Builder $query,
        ?string $phone = null,
        ?string $ip = null,
        ?string $device = null
    ): Builder {
        return $query->where(function (Builder $subQuery) use ($phone, $ip, $device) {
            $hasCondition = false;

            if (! empty($phone)) {
                $hasCondition = true;
                $local = BangladeshPhoneNumberRule::toLocalFormat($phone);
                $e164 = '+88'.$local;

                $subQuery->where(function (Builder $phoneQuery) use ($phone, $local, $e164) {
                    $phoneQuery->where('phone_number', $phone)
                        ->orWhere('phone_number', $local)
                        ->orWhere('phone_number', $e164);
                });
            }

            if (! empty($ip)) {
                if ($hasCondition) {
                    $subQuery->orWhere('ip_address', $ip);
                } else {
                    $hasCondition = true;
                    $subQuery->where('ip_address', $ip);
                }
            }

            if (! empty($device)) {
                if ($hasCondition) {
                    $subQuery->orWhere('device_fingerprint', $device);
                } else {
                    $subQuery->where('device_fingerprint', $device);
                }
            }
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Admin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    use HasFactory;

    public const EVENT_PRICE_UPDATED = 'price_updated';

    public const EVENT_STOCK_ADJUSTED = 'stock_adjusted';

    public const EVENT_ORDER_STATUS_CHANGED = 'order_status_changed';

    public const EVENT_MUSHAK_ISSUED = 'mushak_issued';

    public const EVENT_FRAUD_LIST_UPDATED = 'fraud_list_updated';

    public const EVENT_SECURITY_SETTING_CHANGED = 'security_setting_changed';

    public const EVENT_ROLE_UPDATED = 'role_updated';

    protected $table = 'audit_logs';

    protected $fillable = [
        'ulid',
        'event',
        'auditable_type',
        'auditable_id',
        'description',
        'old_values',
        'new_values',
        'user_id',
        'user_name',
        'user_email',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            if (empty($log->ulid)) {
                $log->ulid = (string) Str::ulid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}


<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VoiceCallLog extends Model
{
    public const STATUS_INITIATED = 'initiated';

    public const STATUS_RINGING = 'ringing';

    public const STATUS_ANSWERED = 'answered';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_BUSY = 'busy';

    public const STATUS_NO_ANSWER = 'no_answer';

    public const STATUS_FAILED = 'failed';

    public const RESULT_CONFIRMED = 'confirmed';

    public const RESULT_CANCELLED = 'cancelled';

    public const RESULT_UNATTENDED = 'unattended';

    public const RESULT_FAILED = 'failed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'order_id',
        'phone_number',
        'call_sid',
        'provider',
        'status',
        'verification_result',
        'response_mode',
        'dtmf_digit',
        'speech_intent',
        'recording_url',
        'duration_seconds',
        'notes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'duration_seconds' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (VoiceCallLog $model): void {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}

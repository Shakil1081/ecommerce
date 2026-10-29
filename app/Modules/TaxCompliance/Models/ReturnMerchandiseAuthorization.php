<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Models;

use App\Models\User;
use App\Modules\Order\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $ulid
 * @property string $rma_number
 * @property int $order_id
 * @property int|null $user_id
 * @property string $status
 * @property string $reason_category
 * @property string|null $customer_notes
 * @property string|null $admin_notes
 * @property string|null $inspection_outcome
 * @property string $refund_amount_bdt
 * @property string $refund_status
 * @property string|null $refund_method
 * @property string|null $refund_transaction_reference
 * @property CarbonInterface|null $approved_at
 * @property CarbonInterface|null $inspected_at
 * @property CarbonInterface|null $refunded_at
 * @property int|null $created_by_user_id
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property Order $order
 * @property User|null $user
 * @property Collection<int, RmaItem> $items
 * @property Mushak67CreditNote|null $creditNote
 * @property User|null $createdByUser
 */
class ReturnMerchandiseAuthorization extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'return_merchandise_authorizations';

    protected $fillable = [
        'rma_number',
        'order_id',
        'user_id',
        'status',
        'reason_category',
        'customer_notes',
        'admin_notes',
        'inspection_outcome',
        'refund_amount_bdt',
        'refund_status',
        'refund_method',
        'refund_transaction_reference',
        'approved_at',
        'inspected_at',
        'refunded_at',
        'created_by_user_id',
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'inspected_at' => 'datetime',
            'refunded_at' => 'datetime',
            'refund_amount_bdt' => 'string',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RmaItem::class, 'rma_id');
    }

    public function creditNote(): HasOne
    {
        return $this->hasOne(Mushak67CreditNote::class, 'rma_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Models;

use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierDisbursementItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'disbursement_id',
        'consignment_id',
        'order_number',
        'order_id',
        'collected_amount_bdt',
        'expected_amount_bdt',
        'courier_fee_bdt',
        'discrepancy_amount_bdt',
        'is_reconciled',
        'notes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'collected_amount_bdt' => 'decimal:4',
        'expected_amount_bdt' => 'decimal:4',
        'courier_fee_bdt' => 'decimal:4',
        'discrepancy_amount_bdt' => 'decimal:4',
        'is_reconciled' => 'boolean',
    ];

    public function disbursement(): BelongsTo
    {
        return $this->belongsTo(CourierDisbursement::class, 'disbursement_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}

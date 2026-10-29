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

/**
 * @property int $id
 * @property string $ulid
 * @property string $credit_note_number
 * @property string $tax_period
 * @property CarbonInterface $issue_date
 * @property int $order_id
 * @property int $mushak_6_3_invoice_id
 * @property string $mushak_6_3_invoice_number
 * @property CarbonInterface $mushak_6_3_invoice_date
 * @property int|null $rma_id
 * @property string $seller_name
 * @property string $seller_bin
 * @property string $seller_address
 * @property string $customer_name
 * @property string|null $customer_bin
 * @property string $customer_phone
 * @property string $customer_address
 * @property string $reason_for_return
 * @property string $original_invoice_total_bdt
 * @property string $returned_value_exclusive_bdt
 * @property string $returned_sd_amount_bdt
 * @property string $returned_vat_amount_bdt
 * @property string $total_credit_amount_bdt
 * @property bool $is_adjusted_in_mushak_9_1
 * @property int|null $mushak_9_1_return_id
 * @property string $status
 * @property string $integrity_hash
 * @property int|null $created_by_user_id
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property Order $order
 * @property Mushak63Invoice $invoice
 * @property ReturnMerchandiseAuthorization|null $rma
 * @property Collection<int, Mushak67Item> $items
 * @property Mushak91Return|null $mushak91Return
 * @property User|null $createdByUser
 */
class Mushak67CreditNote extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'mushak_6_7_credit_notes';

    protected $fillable = [
        'credit_note_number',
        'tax_period',
        'issue_date',
        'order_id',
        'mushak_6_3_invoice_id',
        'mushak_6_3_invoice_number',
        'mushak_6_3_invoice_date',
        'rma_id',
        'seller_name',
        'seller_bin',
        'seller_address',
        'customer_name',
        'customer_bin',
        'customer_phone',
        'customer_address',
        'reason_for_return',
        'original_invoice_total_bdt',
        'returned_value_exclusive_bdt',
        'returned_sd_amount_bdt',
        'returned_vat_amount_bdt',
        'total_credit_amount_bdt',
        'is_adjusted_in_mushak_9_1',
        'mushak_9_1_return_id',
        'status',
        'integrity_hash',
        'created_by_user_id',
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected function casts(): array
    {
        return [
            'issue_date' => 'datetime',
            'mushak_6_3_invoice_date' => 'datetime',
            'is_adjusted_in_mushak_9_1' => 'boolean',
            'original_invoice_total_bdt' => 'string',
            'returned_value_exclusive_bdt' => 'string',
            'returned_sd_amount_bdt' => 'string',
            'returned_vat_amount_bdt' => 'string',
            'total_credit_amount_bdt' => 'string',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Mushak63Invoice::class, 'mushak_6_3_invoice_id');
    }

    public function rma(): BelongsTo
    {
        return $this->belongsTo(ReturnMerchandiseAuthorization::class, 'rma_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Mushak67Item::class, 'mushak_6_7_credit_note_id');
    }

    public function mushak91Return(): BelongsTo
    {
        return $this->belongsTo(Mushak91Return::class, 'mushak_9_1_return_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

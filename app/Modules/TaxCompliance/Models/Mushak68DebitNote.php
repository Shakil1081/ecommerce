<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Models;

use App\Models\User;
use App\Modules\Procurement\Models\LocalPurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
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
 * @property string $debit_note_number
 * @property string $tax_period
 * @property CarbonInterface $issue_date
 * @property int $supplier_id
 * @property string $supplier_name
 * @property string $supplier_bin
 * @property string|null $supplier_address
 * @property string $buyer_name
 * @property string $buyer_bin
 * @property string $buyer_address
 * @property int|null $local_purchase_order_id
 * @property string $original_invoice_number
 * @property CarbonInterface $original_invoice_date
 * @property string $reason_for_return
 * @property string $returned_value_exclusive_bdt
 * @property string $returned_sd_amount_bdt
 * @property string $returned_vat_amount_bdt
 * @property string $total_debit_amount_bdt
 * @property bool $is_adjusted_in_mushak_9_1
 * @property string $status
 * @property string $integrity_hash
 * @property int|null $created_by_user_id
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property Supplier $supplier
 * @property LocalPurchaseOrder|null $localPurchaseOrder
 * @property Collection<int, Mushak68Item> $items
 * @property User|null $createdByUser
 */
class Mushak68DebitNote extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'mushak_6_8_debit_notes';

    protected $fillable = [
        'debit_note_number',
        'tax_period',
        'issue_date',
        'supplier_id',
        'supplier_name',
        'supplier_bin',
        'supplier_address',
        'buyer_name',
        'buyer_bin',
        'buyer_address',
        'local_purchase_order_id',
        'original_invoice_number',
        'original_invoice_date',
        'reason_for_return',
        'returned_value_exclusive_bdt',
        'returned_sd_amount_bdt',
        'returned_vat_amount_bdt',
        'total_debit_amount_bdt',
        'is_adjusted_in_mushak_9_1',
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
            'original_invoice_date' => 'date',
            'is_adjusted_in_mushak_9_1' => 'boolean',
            'returned_value_exclusive_bdt' => 'string',
            'returned_sd_amount_bdt' => 'string',
            'returned_vat_amount_bdt' => 'string',
            'total_debit_amount_bdt' => 'string',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function localPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(LocalPurchaseOrder::class, 'local_purchase_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Mushak68Item::class, 'mushak_6_8_debit_note_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

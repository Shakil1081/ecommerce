<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property string $po_number
 * @property int $supplier_id
 * @property CarbonInterface $order_date
 * @property CarbonInterface|null $delivery_deadline
 * @property string $status
 * @property string|null $mushak_6_3_invoice_number
 * @property CarbonInterface|null $mushak_6_3_invoice_date
 * @property string $subtotal_bdt
 * @property string $vat_amount_bdt
 * @property string $vds_rate_percent
 * @property string $vds_amount_bdt
 * @property string|null $mushak_6_6_certificate_number
 * @property CarbonInterface|null $mushak_6_6_issue_date
 * @property string $tds_rate_percent
 * @property string $tds_amount_bdt
 * @property string $total_payable_to_supplier_bdt
 * @property string|null $payment_terms
 * @property string|null $notes
 * @property Supplier $supplier
 * @property Collection<int, LocalPoItem> $items
 */
class LocalPurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'ulid',
        'po_number',
        'supplier_id',
        'order_date',
        'delivery_deadline',
        'status',
        'mushak_6_3_invoice_number',
        'mushak_6_3_invoice_date',
        'subtotal_bdt',
        'vat_amount_bdt',
        'vds_rate_percent',
        'vds_amount_bdt',
        'mushak_6_6_certificate_number',
        'mushak_6_6_issue_date',
        'tds_rate_percent',
        'tds_amount_bdt',
        'total_payable_to_supplier_bdt',
        'payment_terms',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'delivery_deadline' => 'date',
            'mushak_6_3_invoice_date' => 'date',
            'mushak_6_6_issue_date' => 'date',
            'subtotal_bdt' => 'decimal:4',
            'vat_amount_bdt' => 'decimal:4',
            'vds_rate_percent' => 'decimal:2',
            'vds_amount_bdt' => 'decimal:4',
            'tds_rate_percent' => 'decimal:2',
            'tds_amount_bdt' => 'decimal:4',
            'total_payable_to_supplier_bdt' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LocalPurchaseOrder $po): void {
            if (empty($po->ulid)) {
                $po->ulid = (string) Str::ulid();
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LocalPoItem::class, 'local_purchase_order_id');
    }

    /**
     * Recalculate totals, VDS deduction, and TDS withholding.
     */
    public function recalculateTotals(): void
    {
        $subtotal = '0.0000';
        $vatTotal = '0.0000';

        foreach ($this->items as $item) {
            $subtotal = bcadd($subtotal, (string) $item->subtotal_bdt, 4);
            $vatTotal = bcadd($vatTotal, (string) $item->vat_amount_bdt, 4);
        }

        // VDS Calculation
        $vdsAmount = '0.0000';
        if ((float) $this->vds_rate_percent > 0) {
            $vdsAmount = bcdiv(bcmul($subtotal, (string) $this->vds_rate_percent, 6), '100', 4);
        }

        // TDS Calculation under Income Tax Act 2023
        $tdsAmount = '0.0000';
        if ((float) $this->tds_rate_percent > 0) {
            $tdsAmount = bcdiv(bcmul($subtotal, (string) $this->tds_rate_percent, 6), '100', 4);
        }

        // Net Payable to Supplier = Subtotal + VAT - VDS - TDS
        $payable = bcsub(
            bcsub(bcadd($subtotal, $vatTotal, 4), $vdsAmount, 4),
            $tdsAmount,
            4
        );

        $this->update([
            'subtotal_bdt' => $subtotal,
            'vat_amount_bdt' => $vatTotal,
            'vds_amount_bdt' => $vdsAmount,
            'tds_amount_bdt' => $tdsAmount,
            'total_payable_to_supplier_bdt' => $payable,
        ]);
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('status', 'issued');
    }

    public function scopeReceived(Builder $query): Builder
    {
        return $query->where('status', 'received');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }
}

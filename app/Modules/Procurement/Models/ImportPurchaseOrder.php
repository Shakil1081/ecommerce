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
 * @property string $status
 * @property string|null $proforma_invoice_number
 * @property CarbonInterface|null $proforma_invoice_date
 * @property string|null $commercial_invoice_number
 * @property CarbonInterface|null $commercial_invoice_date
 * @property string $payment_method
 * @property string|null $lc_number
 * @property string|null $lc_issuing_bank
 * @property CarbonInterface|null $lc_issue_date
 * @property string $lc_margin_percentage
 * @property string $currency
 * @property string $exchange_rate_to_bdt
 * @property string $total_fob_foreign
 * @property string $total_fob_bdt
 * @property string|null $port_of_loading
 * @property string $port_of_discharge
 * @property string|null $bill_of_lading_number
 * @property string|null $vessel_or_flight_name
 * @property CarbonInterface|null $estimated_arrival_date
 * @property CarbonInterface|null $actual_arrival_date
 * @property string|null $notes
 * @property Supplier $supplier
 * @property Collection<int, ImportPoItem> $items
 */
class ImportPurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'ulid',
        'po_number',
        'supplier_id',
        'status',
        'proforma_invoice_number',
        'proforma_invoice_date',
        'commercial_invoice_number',
        'commercial_invoice_date',
        'payment_method',
        'lc_number',
        'lc_issuing_bank',
        'lc_issue_date',
        'lc_margin_percentage',
        'currency',
        'exchange_rate_to_bdt',
        'total_fob_foreign',
        'total_fob_bdt',
        'port_of_loading',
        'port_of_discharge',
        'bill_of_lading_number',
        'vessel_or_flight_name',
        'estimated_arrival_date',
        'actual_arrival_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'proforma_invoice_date' => 'date',
            'commercial_invoice_date' => 'date',
            'lc_issue_date' => 'date',
            'estimated_arrival_date' => 'date',
            'actual_arrival_date' => 'date',
            'lc_margin_percentage' => 'decimal:2',
            'exchange_rate_to_bdt' => 'decimal:4',
            'total_fob_foreign' => 'decimal:4',
            'total_fob_bdt' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ImportPurchaseOrder $order): void {
            if (empty($order->ulid)) {
                $order->ulid = (string) Str::ulid();
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ImportPoItem::class, 'import_purchase_order_id');
    }

    /**
     * Recalculate order total FOB values based on items.
     */
    public function recalculateTotals(): void
    {
        $foreignTotal = '0.0000';
        $bdtTotal = '0.0000';

        foreach ($this->items as $item) {
            $foreignTotal = bcadd($foreignTotal, (string) $item->total_fob_foreign, 4);
            $bdtTotal = bcadd($bdtTotal, (string) $item->total_fob_bdt, 4);
        }

        $this->update([
            'total_fob_foreign' => $foreignTotal,
            'total_fob_bdt' => $bdtTotal,
        ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['cancelled']);
    }

    public function scopeInTransit(Builder $query): Builder
    {
        return $query->where('status', 'in_transit');
    }

    public function scopeCustomsClearing(Builder $query): Builder
    {
        return $query->where('status', 'customs_clearing');
    }
}

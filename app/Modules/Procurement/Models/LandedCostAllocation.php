<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property string $allocation_number
 * @property CarbonInterface $allocation_date
 * @property string $reference_type
 * @property int|null $import_purchase_order_id
 * @property int|null $bill_of_entry_id
 * @property int|null $local_purchase_order_id
 * @property string $base_purchase_cost_bdt
 * @property string $freight_cost_bdt
 * @property string $insurance_cost_bdt
 * @property string $port_cnf_cost_bdt
 * @property string $customs_duties_bdt
 * @property string $transport_carrying_bdt
 * @property string $handling_cost_bdt
 * @property string $other_costs_bdt
 * @property string $total_landed_cost_bdt
 * @property array|null $allocated_items_breakdown
 * @property string $status
 * @property string|null $notes
 * @property ImportPurchaseOrder|null $importPurchaseOrder
 * @property BillOfEntry|null $billOfEntry
 * @property LocalPurchaseOrder|null $localPurchaseOrder
 */
class LandedCostAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'ulid',
        'allocation_number',
        'allocation_date',
        'reference_type',
        'import_purchase_order_id',
        'bill_of_entry_id',
        'local_purchase_order_id',
        'base_purchase_cost_bdt',
        'freight_cost_bdt',
        'insurance_cost_bdt',
        'port_cnf_cost_bdt',
        'customs_duties_bdt',
        'transport_carrying_bdt',
        'handling_cost_bdt',
        'other_costs_bdt',
        'total_landed_cost_bdt',
        'allocated_items_breakdown',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'allocation_date' => 'date',
            'base_purchase_cost_bdt' => 'decimal:4',
            'freight_cost_bdt' => 'decimal:4',
            'insurance_cost_bdt' => 'decimal:4',
            'port_cnf_cost_bdt' => 'decimal:4',
            'customs_duties_bdt' => 'decimal:4',
            'transport_carrying_bdt' => 'decimal:4',
            'handling_cost_bdt' => 'decimal:4',
            'other_costs_bdt' => 'decimal:4',
            'total_landed_cost_bdt' => 'decimal:4',
            'allocated_items_breakdown' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LandedCostAllocation $allocation): void {
            if (empty($allocation->ulid)) {
                $allocation->ulid = (string) Str::ulid();
            }
        });
    }

    public function importPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ImportPurchaseOrder::class, 'import_purchase_order_id');
    }

    public function billOfEntry(): BelongsTo
    {
        return $this->belongsTo(BillOfEntry::class, 'bill_of_entry_id');
    }

    public function localPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(LocalPurchaseOrder::class, 'local_purchase_order_id');
    }

    public function scopeImport(Builder $query): Builder
    {
        return $query->where('reference_type', 'import');
    }

    public function scopeLocal(Builder $query): Builder
    {
        return $query->where('reference_type', 'local');
    }

    public function scopeApplied(Builder $query): Builder
    {
        return $query->where('status', 'applied_to_inventory');
    }
}

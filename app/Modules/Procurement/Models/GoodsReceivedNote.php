<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Models\User;
use App\Modules\Inventory\Models\Warehouse;
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
 * @property string $grn_number
 * @property CarbonInterface $grn_date
 * @property string $source_type
 * @property int|null $import_purchase_order_id
 * @property int|null $local_purchase_order_id
 * @property int $warehouse_id
 * @property int|null $received_by_user_id
 * @property string|null $supplier_challan_number
 * @property string|null $truck_or_container_number
 * @property string|null $driver_name
 * @property string|null $driver_phone
 * @property string $status
 * @property int $total_ordered_quantity
 * @property int $total_received_quantity
 * @property int $total_damaged_quantity
 * @property string|null $notes
 * @property Warehouse $warehouse
 * @property User|null $receivedBy
 * @property ImportPurchaseOrder|null $importPurchaseOrder
 * @property LocalPurchaseOrder|null $localPurchaseOrder
 * @property Collection<int, GrnItem> $items
 */
class GoodsReceivedNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'ulid',
        'grn_number',
        'grn_date',
        'source_type',
        'import_purchase_order_id',
        'local_purchase_order_id',
        'warehouse_id',
        'received_by_user_id',
        'supplier_challan_number',
        'truck_or_container_number',
        'driver_name',
        'driver_phone',
        'status',
        'total_ordered_quantity',
        'total_received_quantity',
        'total_damaged_quantity',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'grn_date' => 'date',
            'total_ordered_quantity' => 'integer',
            'total_received_quantity' => 'integer',
            'total_damaged_quantity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GoodsReceivedNote $grn): void {
            if (empty($grn->ulid)) {
                $grn->ulid = (string) Str::ulid();
            }
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function importPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ImportPurchaseOrder::class, 'import_purchase_order_id');
    }

    public function localPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(LocalPurchaseOrder::class, 'local_purchase_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GrnItem::class, 'goods_received_note_id');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeDiscrepancy(Builder $query): Builder
    {
        return $query->where('status', 'discrepancy_flagged');
    }
}

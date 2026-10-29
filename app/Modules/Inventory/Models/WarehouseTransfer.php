<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Inter-Warehouse Depot Stock Transfer with NBR Statutory Mushak 6.5 Challan.
 */
class WarehouseTransfer extends Model
{
    use HasUlids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'warehouse_transfers';

    protected $fillable = [
        'transfer_number',
        'mushak_6_5_serial',
        'source_warehouse_id',
        'destination_warehouse_id',
        'status',
        'vehicle_number',
        'driver_name',
        'driver_contact',
        'dispatched_at',
        'received_at',
        'initiated_by_user_id',
        'received_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /**
     * Source dispatching warehouse.
     */
    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    /**
     * Destination receiving warehouse.
     */
    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    /**
     * Items in this transfer.
     */
    public function items(): HasMany
    {
        return $this->hasMany(WarehouseTransferItem::class, 'transfer_id');
    }

    /**
     * Staff member who initiated the transfer.
     */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    /**
     * Staff member who received the shipment.
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    /**
     * Scope in-transit transfers.
     */
    public function scopeInTransit(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_IN_TRANSIT);
    }

    /**
     * Scope received completed transfers.
     */
    public function scopeReceived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RECEIVED);
    }
}


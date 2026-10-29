<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Models;

use App\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseTransfer;
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
 * @property string $challan_number
 * @property string $tax_period
 * @property CarbonInterface $issue_date
 * @property string|null $warehouse_transfer_id
 * @property int $source_warehouse_id
 * @property string $source_warehouse_name
 * @property string $source_warehouse_bin
 * @property string $source_warehouse_address
 * @property int $destination_warehouse_id
 * @property string $destination_warehouse_name
 * @property string $destination_warehouse_bin
 * @property string $destination_warehouse_address
 * @property string $vehicle_type_and_number
 * @property string|null $driver_name
 * @property string|null $driver_contact
 * @property CarbonInterface $dispatch_time
 * @property CarbonInterface|null $arrival_time
 * @property int $total_quantity
 * @property string $total_valuation_bdt
 * @property string $status
 * @property string $integrity_hash
 * @property string|null $notes
 * @property int|null $created_by_user_id
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property Warehouse $sourceWarehouse
 * @property Warehouse $destinationWarehouse
 * @property WarehouseTransfer|null $warehouseTransfer
 * @property Collection<int, Mushak65Item> $items
 * @property User|null $createdByUser
 */
class Mushak65Challan extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'mushak_6_5_challans';

    protected $fillable = [
        'challan_number',
        'tax_period',
        'issue_date',
        'warehouse_transfer_id',
        'source_warehouse_id',
        'source_warehouse_name',
        'source_warehouse_bin',
        'source_warehouse_address',
        'destination_warehouse_id',
        'destination_warehouse_name',
        'destination_warehouse_bin',
        'destination_warehouse_address',
        'vehicle_type_and_number',
        'driver_name',
        'driver_contact',
        'dispatch_time',
        'arrival_time',
        'total_quantity',
        'total_valuation_bdt',
        'status',
        'integrity_hash',
        'notes',
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
            'dispatch_time' => 'datetime',
            'arrival_time' => 'datetime',
            'total_quantity' => 'integer',
            'total_valuation_bdt' => 'string',
        ];
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function warehouseTransfer(): BelongsTo
    {
        return $this->belongsTo(WarehouseTransfer::class, 'warehouse_transfer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Mushak65Item::class, 'mushak_6_5_challan_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

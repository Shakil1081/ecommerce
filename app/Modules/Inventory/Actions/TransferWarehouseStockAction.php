<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseTransfer;
use App\Modules\Inventory\Models\WarehouseTransferItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransferWarehouseStockAction
{
    public function __construct(
        private readonly AdjustInventoryStockAction $adjustAction
    ) {}

    /**
     * Dispatch an inter-depot stock transfer and issue a statutory NBR Mushak 6.5 Challan.
     *
     * @param  array<int, array{variant: ProductVariant, quantity: int}>  $items
     *
     * @throws InvalidArgumentException
     */
    public function __invoke(
        Warehouse $sourceWarehouse,
        Warehouse $destinationWarehouse,
        array $items,
        ?string $vehicleNumber = null,
        ?string $driverName = null,
        ?string $driverContact = null,
        ?int $userId = null,
        ?string $notes = null
    ): WarehouseTransfer {
        if ($sourceWarehouse->id === $destinationWarehouse->id) {
            throw new InvalidArgumentException('Source and destination warehouse cannot be the same.');
        }

        if (empty($items)) {
            throw new InvalidArgumentException('Transfer must contain at least one line item.');
        }

        return DB::transaction(function () use (
            $sourceWarehouse,
            $destinationWarehouse,
            $items,
            $vehicleNumber,
            $driverName,
            $driverContact,
            $userId,
            $notes
        ) {
            $monthPrefix = date('Ym');
            $transferCount = WarehouseTransfer::whereRaw("transfer_number LIKE 'TR-{$monthPrefix}-%'")->count() + 1;
            $transferNumber = sprintf('TR-%s-%06d', $monthPrefix, $transferCount);
            $mushakSerial = sprintf('M65-%s-%06d', $monthPrefix, $transferCount);

            $transfer = WarehouseTransfer::create([
                'transfer_number' => $transferNumber,
                'mushak_6_5_serial' => $mushakSerial,
                'source_warehouse_id' => $sourceWarehouse->id,
                'destination_warehouse_id' => $destinationWarehouse->id,
                'status' => WarehouseTransfer::STATUS_IN_TRANSIT,
                'vehicle_number' => $vehicleNumber,
                'driver_name' => $driverName,
                'driver_contact' => $driverContact,
                'dispatched_at' => now(),
                'initiated_by_user_id' => $userId,
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                /** @var ProductVariant $variant */
                $variant = $item['variant'];
                $quantity = (int) $item['quantity'];

                if ($quantity <= 0) {
                    throw new InvalidArgumentException("Transfer quantity must be positive for SKU: {$variant->sku}");
                }

                // 1. Atomically deduct physical stock from source warehouse
                ($this->adjustAction)(
                    $sourceWarehouse,
                    $variant,
                    -$quantity,
                    'mushak_6_5_dispatch'
                );

                // 2. Create transfer line item
                WarehouseTransferItem::create([
                    'transfer_id' => $transfer->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => $quantity,
                ]);
            }

            return $transfer->load(['items.variant', 'sourceWarehouse', 'destinationWarehouse']);
        });
    }
}

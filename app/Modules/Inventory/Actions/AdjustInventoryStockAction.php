<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdjustInventoryStockAction
{
    /**
     * Atomically adjust warehouse physical inventory with concurrency lock.
     *
     * @param  int  $quantityChange  Positive to add stock, negative to deduct stock
     * @param  string  $reasonCode  e.g. 'grn_receipt', 'cycle_count', 'damaged_writeoff'
     *
     * @throws InvalidArgumentException
     */
    public function __invoke(
        Warehouse $warehouse,
        ProductVariant $variant,
        int $quantityChange,
        string $reasonCode = 'manual_adjustment',
        ?string $binLocation = null
    ): InventoryStock {
        return DB::transaction(function () use ($warehouse, $variant, $quantityChange, $binLocation) {
            /** @var InventoryStock $stock */
            $stock = InventoryStock::where('warehouse_id', $warehouse->id)
                ->where('product_variant_id', $variant->id)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                if ($quantityChange < 0) {
                    throw new InvalidArgumentException('Cannot deduct stock from a non-existent warehouse inventory record.');
                }

                $stock = new InventoryStock([
                    'warehouse_id' => $warehouse->id,
                    'product_variant_id' => $variant->id,
                    'physical_quantity' => 0,
                    'reserved_quantity' => 0,
                    'bin_location' => $binLocation,
                ]);
            }

            $newPhysical = $stock->physical_quantity + $quantityChange;

            if ($newPhysical < 0) {
                throw new InvalidArgumentException("Insufficient physical stock in warehouse {$warehouse->code}. Available: {$stock->physical_quantity}, Requested deduction: ".abs($quantityChange));
            }

            $stock->physical_quantity = $newPhysical;

            if ($binLocation !== null) {
                $stock->bin_location = $binLocation;
            }

            $stock->save();

            return $stock;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Order\Models\Order;
use Illuminate\Support\Facades\DB;

final class RestockReturnedOrderAction
{
    /**
     * Restock returned order items back into physical warehouse inventory.
     */
    public function __invoke(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            // 1. Process committed reservations (stock was decremented when handed to courier)
            $committedReservations = StockReservation::query()
                ->where('order_id', $order->id)
                ->where('status', StockReservation::STATUS_COMMITTED)
                ->lockForUpdate()
                ->get();

            if ($committedReservations->isNotEmpty()) {
                foreach ($committedReservations as $reservation) {
                    $stock = InventoryStock::query()
                        ->where('warehouse_id', $reservation->warehouse_id)
                        ->where('product_variant_id', $reservation->product_variant_id)
                        ->lockForUpdate()
                        ->first();

                    if ($stock) {
                        $stock->increment('physical_quantity', $reservation->quantity);
                    }

                    $reservation->update([
                        'status' => StockReservation::STATUS_RELEASED,
                    ]);
                }

                return;
            }

            // 2. If reservations were still in reserved status
            $reservedReservations = StockReservation::query()
                ->where('order_id', $order->id)
                ->where('status', StockReservation::STATUS_RESERVED)
                ->lockForUpdate()
                ->get();

            if ($reservedReservations->isNotEmpty()) {
                foreach ($reservedReservations as $reservation) {
                    $stock = InventoryStock::query()
                        ->where('warehouse_id', $reservation->warehouse_id)
                        ->where('product_variant_id', $reservation->product_variant_id)
                        ->lockForUpdate()
                        ->first();

                    if ($stock) {
                        $stock->decrement('reserved_quantity', min($stock->reserved_quantity, $reservation->quantity));
                    }

                    $reservation->update([
                        'status' => StockReservation::STATUS_RELEASED,
                    ]);
                }

                return;
            }

            // 3. Fallback: Directly increment warehouse stock for order items
            $order->loadMissing('items.variant');
            $defaultWarehouse = Warehouse::query()->where('is_active', true)->first();

            if ($defaultWarehouse) {
                foreach ($order->items as $item) {
                    if ($item->variant) {
                        $stock = InventoryStock::query()
                            ->where('warehouse_id', $defaultWarehouse->id)
                            ->where('product_variant_id', $item->variant->id)
                            ->lockForUpdate()
                            ->first();

                        if ($stock) {
                            $stock->increment('physical_quantity', $item->quantity);
                        }
                    }
                }
            }
        });
    }
}

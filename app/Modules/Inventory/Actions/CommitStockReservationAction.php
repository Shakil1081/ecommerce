<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Order\Models\Order;
use Illuminate\Support\Facades\DB;

final class CommitStockReservationAction
{
    /**
     * Commit active stock reservations upon order dispatch, fulfilling reserved stock and decrementing physical count.
     */
    public function __invoke(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $reservations = StockReservation::query()
                ->where('order_id', $order->id)
                ->where('status', StockReservation::STATUS_RESERVED)
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                /** @var InventoryStock|null $stock */
                $stock = InventoryStock::query()
                    ->where('warehouse_id', $reservation->warehouse_id)
                    ->where('product_variant_id', $reservation->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $stock->decrement('reserved_quantity', min($stock->reserved_quantity, $reservation->quantity));
                    $stock->decrement('physical_quantity', min($stock->physical_quantity, $reservation->quantity));
                }

                $reservation->update([
                    'status' => StockReservation::STATUS_COMMITTED,
                ]);
            }
        });
    }
}

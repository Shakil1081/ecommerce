<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Order\Models\Order;
use Illuminate\Support\Facades\DB;

final class ReleaseStockReservationAction
{
    /**
     * Release active reservations back into available stock upon order cancellation or timeout.
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
                }

                $reservation->update([
                    'status' => StockReservation::STATUS_RELEASED,
                ]);
            }
        });
    }
}

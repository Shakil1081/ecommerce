<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\StockReservation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReleaseExpiredStockReservationsAction
{
    /**
     * Find and release all stock reservations that have exceeded their TTL.
     *
     * @return int Number of released reservations
     */
    public function __invoke(): int
    {
        return DB::transaction(function (): int {
            $expiredReservations = StockReservation::query()
                ->where('status', StockReservation::STATUS_RESERVED)
                ->where('expires_at', '<=', Carbon::now())
                ->lockForUpdate()
                ->get();

            $count = 0;

            foreach ($expiredReservations as $reservation) {
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

                $count++;
            }

            return $count;
        });
    }
}

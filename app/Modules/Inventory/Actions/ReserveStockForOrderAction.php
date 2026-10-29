<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReserveStockForOrderAction
{
    /**
     * Atomically reserve inventory stock for an order across its items using row-level locking.
     *
     * @return Collection<int, StockReservation>
     *
     * @throws InsufficientStockException
     */
    public function __invoke(Order $order, ?Warehouse $warehouse = null, int $ttlMinutes = 15): Collection
    {
        $order->loadMissing('items');

        $targetWarehouse = $warehouse ?? Warehouse::query()->where('is_primary', true)->first()
            ?? Warehouse::query()->firstOrFail();

        return DB::transaction(function () use ($order, $targetWarehouse, $ttlMinutes): Collection {
            $reservations = new Collection;
            $now = Carbon::now();
            $expiresAt = $now->copy()->addMinutes($ttlMinutes);

            foreach ($order->items as $item) {
                /** @var InventoryStock|null $stock */
                $stock = InventoryStock::query()
                    ->where('warehouse_id', $targetWarehouse->id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                $available = $stock ? max(0, $stock->physical_quantity - $stock->reserved_quantity) : 0;

                if ($available < $item->quantity) {
                    throw InsufficientStockException::forVariant(
                        sku: $item->sku,
                        requested: $item->quantity,
                        available: $available
                    );
                }

                // Atomically increment reserved stock
                $stock->increment('reserved_quantity', $item->quantity);

                // Create reservation record
                /** @var StockReservation $reservation */
                $reservation = StockReservation::create([
                    'order_id' => $order->id,
                    'cart_id' => null,
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $targetWarehouse->id,
                    'quantity' => $item->quantity,
                    'status' => StockReservation::STATUS_RESERVED,
                    'expires_at' => $expiresAt,
                ]);

                $reservations->push($reservation);
            }

            return $reservations;
        });
    }
}

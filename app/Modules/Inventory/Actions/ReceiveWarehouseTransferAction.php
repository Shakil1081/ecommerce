<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\WarehouseTransfer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReceiveWarehouseTransferAction
{
    public function __construct(
        private readonly AdjustInventoryStockAction $adjustAction
    ) {}

    /**
     * Confirm inward receipt of an inter-depot stock transfer at destination depot.
     *
     * @throws InvalidArgumentException
     */
    public function __invoke(
        WarehouseTransfer $transfer,
        ?int $userId = null
    ): WarehouseTransfer {
        if ($transfer->status !== WarehouseTransfer::STATUS_IN_TRANSIT) {
            throw new InvalidArgumentException("Cannot receive transfer with status: '{$transfer->status}'. Only 'in_transit' shipments can be received.");
        }

        return DB::transaction(function () use ($transfer, $userId) {
            $transfer->loadMissing(['items.variant', 'destinationWarehouse']);

            foreach ($transfer->items as $item) {
                // Atomically commit physical stock to destination warehouse
                ($this->adjustAction)(
                    $transfer->destinationWarehouse,
                    $item->variant,
                    $item->quantity,
                    'mushak_6_5_receipt'
                );
            }

            $transfer->update([
                'status' => WarehouseTransfer::STATUS_RECEIVED,
                'received_at' => now(),
                'received_by_user_id' => $userId,
            ]);

            return $transfer->refresh();
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Commands;

use App\Modules\Inventory\Actions\ReleaseExpiredStockReservationsAction;
use Illuminate\Console\Command;

final class ReleaseExpiredReservationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:release-expired-reservations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release expired inventory stock reservations back into available stock';

    /**
     * Execute the console command.
     */
    public function handle(ReleaseExpiredStockReservationsAction $releaseAction): int
    {
        $count = $releaseAction();

        $this->info("Successfully released {$count} expired inventory reservation(s).");

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Providers;

use App\Modules\Inventory\Commands\ReleaseExpiredReservationsCommand;
use Illuminate\Support\ServiceProvider;

final class InventoryServiceProvider extends ServiceProvider
{
    /**
     * Register any module services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any module services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ReleaseExpiredReservationsCommand::class,
            ]);
        }
    }
}

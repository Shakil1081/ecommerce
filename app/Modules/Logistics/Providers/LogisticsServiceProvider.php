<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Providers;

use App\Modules\Logistics\Services\CourierGatewayManager;
use Illuminate\Support\ServiceProvider;

class LogisticsServiceProvider extends ServiceProvider
{
    /**
     * Register any module services.
     */
    public function register(): void
    {
        $this->app->singleton(CourierGatewayManager::class, function () {
            return new CourierGatewayManager;
        });
    }

    /**
     * Bootstrap any module services.
     */
    public function boot(): void
    {
        //
    }
}

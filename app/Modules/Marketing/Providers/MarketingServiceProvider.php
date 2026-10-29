<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Providers;

use App\Modules\Marketing\Commands\ProcessCartRecoveryCommand;
use Illuminate\Support\ServiceProvider;

class MarketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessCartRecoveryCommand::class,
            ]);
        }
    }
}

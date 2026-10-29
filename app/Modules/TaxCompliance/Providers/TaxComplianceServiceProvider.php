<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Providers;

use App\Modules\TaxCompliance\Services\MushakSequenceService;
use Illuminate\Support\ServiceProvider;

class TaxComplianceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MushakSequenceService::class, function (): MushakSequenceService {
            return new MushakSequenceService;
        });

        $this->mergeConfigFrom(__DIR__.'/../../../../config/tax.php', 'tax');
    }

    public function boot(): void
    {
        // Boot module services and routes when added
    }
}

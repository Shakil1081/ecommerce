<?php

declare(strict_types=1);

namespace App\Modules\AI\Providers;

use App\Modules\AI\Actions\RecordAiUsageAction;
use App\Modules\AI\Actions\TestAiConnectionAction;
use App\Modules\AI\Services\AiManager;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiManager::class, function ($app) {
            return new AiManager(
                $app->make(RecordAiUsageAction::class)
            );
        });

        $this->app->singleton(TestAiConnectionAction::class);
    }

    public function boot(): void {}
}

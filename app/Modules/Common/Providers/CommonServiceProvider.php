<?php

declare(strict_types=1);

namespace App\Modules\Common\Providers;

use App\Modules\Common\Commands\MigrateMediaDiskCommand;
use App\Modules\Common\Models\MediaStorageSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class CommonServiceProvider extends ServiceProvider
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
        $this->bootDynamicMediaDisk();

        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateMediaDiskCommand::class,
            ]);
        }
    }

    /**
     * Bind the active media storage provider disk dynamically at runtime.
     */
    protected function bootDynamicMediaDisk(): void
    {
        try {
            if (Schema::hasTable('media_storage_settings')) {
                $active = MediaStorageSetting::query()->where('is_active', true)->first();
                if ($active) {
                    Config::set('filesystems.disks.media', $active->toDiskConfig());

                    return;
                }
            }
        } catch (\Throwable) {
            // Fallback during early migrations or CLI operations
        }

        // Default Local Media Disk Fallback
        Config::set('filesystems.disks.media', [
            'driver' => 'local',
            'root' => storage_path('app/public/media'),
            'url' => config('app.url').'/storage/media',
            'visibility' => 'public',
        ]);
    }
}

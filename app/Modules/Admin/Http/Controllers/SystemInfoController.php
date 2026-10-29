<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\ChangelogParserService;
use App\Modules\Common\Models\MediaStorageSetting;
use Inertia\Inertia;
use Inertia\Response;

class SystemInfoController extends Controller
{
    /**
     * Display system info and visual release notes timeline.
     */
    public function index(ChangelogParserService $parser): Response
    {
        $activeStorage = MediaStorageSetting::where('is_active', true)->first();

        $systemInfo = [
            'appName' => (string) config('app.name', 'TradingHub E-Commerce'),
            'appVersion' => '0.1.0-alpha',
            'environment' => app()->environment(),
            'debug' => (bool) config('app.debug', false),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'dbConnection' => (string) config('database.default', 'sqlite'),
            'cacheDriver' => (string) config('cache.default', 'file'),
            'queueDriver' => (string) config('queue.default', 'sync'),
            'storageDisk' => (string) config('media-library.disk_name', 'media'),
            'activeStorageDriver' => $activeStorage?->name ?? 'Local Server Storage',
        ];

        $changelogPath = base_path('CHANGELOG.md');
        $releases = $parser->parse($changelogPath);

        return Inertia::render('Admin/Settings/SystemUpdates', [
            'systemInfo' => $systemInfo,
            'releases' => $releases,
        ]);
    }
}

<?php

declare(strict_types=1);

use App\Modules\Admin\Http\Controllers\MediaStorageController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::prefix('admin/settings')->name('admin.settings.')->group(function () {
    Route::get('/media-storage', [MediaStorageController::class, 'index'])->name('media-storage.index');
    Route::post('/media-storage', [MediaStorageController::class, 'store'])->name('media-storage.store');
    Route::post('/media-storage/test', [MediaStorageController::class, 'test'])->name('media-storage.test');
    Route::delete('/media-storage/{setting}', [MediaStorageController::class, 'destroy'])->name('media-storage.destroy');
});

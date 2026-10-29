<?php

declare(strict_types=1);

use App\Modules\Admin\Http\Controllers\MediaStorageController;
use App\Modules\Admin\Http\Controllers\RoleController;
use App\Modules\Admin\Http\Controllers\StaffUserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::resource('roles', RoleController::class);
    Route::resource('staff', StaffUserController::class);

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/media-storage', [MediaStorageController::class, 'index'])->name('media-storage.index');
        Route::post('/media-storage', [MediaStorageController::class, 'store'])->name('media-storage.store');
        Route::post('/media-storage/test', [MediaStorageController::class, 'test'])->name('media-storage.test');
        Route::delete('/media-storage/{setting}', [MediaStorageController::class, 'destroy'])->name('media-storage.destroy');
    });
});

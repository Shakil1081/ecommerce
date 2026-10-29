<?php

declare(strict_types=1);

use App\Modules\Admin\Http\Controllers\BotSecuritySettingsController;
use App\Modules\Admin\Http\Controllers\CatalogController;
use App\Modules\Admin\Http\Controllers\FraudDirectoryAdminController;
use App\Modules\Admin\Http\Controllers\MediaStorageController;
use App\Modules\Admin\Http\Controllers\OrderAdminController;
use App\Modules\Admin\Http\Controllers\ProcurementController;
use App\Modules\Admin\Http\Controllers\RoleController;
use App\Modules\Admin\Http\Controllers\StaffUserController;
use App\Modules\Admin\Http\Controllers\SystemInfoController;
use App\Modules\Cart\Http\Controllers\CartController;
use App\Modules\Payment\Http\Controllers\BkashCallbackController;
use App\Modules\Seo\Http\Controllers\SitemapController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Shopping Cart & Slide-Out Drawer Endpoints
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::get('/cart/drawer', [CartController::class, 'index'])->name('cart.drawer');
Route::post('/cart/items', [CartController::class, 'addItem'])->name('cart.items.add');
Route::patch('/cart/items/{variantId}', [CartController::class, 'updateQuantity'])->name('cart.items.update');
Route::delete('/cart/items/{variantId}', [CartController::class, 'removeItem'])->name('cart.items.remove');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

// Dynamic SEO Endpoints
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('seo.robots');

// Storefront Checkout & Order Confirmation
Route::get('/checkout', function () {
    return Inertia::render('Storefront/Checkout');
})->name('storefront.checkout');

Route::get('/orders/confirmed/{order}', function (string $order) {
    return Inertia::render('Storefront/OrderConfirmed', ['orderNumber' => $order]);
})->name('storefront.order-confirmed');

// Payment Callbacks & Webhooks
Route::prefix('payment')->name('payment.')->group(function () {
    Route::get('/bkash/callback', BkashCallbackController::class)->name('bkash.callback');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::resource('roles', RoleController::class);
    Route::resource('staff', StaffUserController::class);

    // Catalog & Matrix Variant Management
    Route::resource('products', CatalogController::class);
    Route::get('products/{product}/matrix', [CatalogController::class, 'matrix'])->name('products.matrix');
    Route::post('products/{product}/generate-variants', [CatalogController::class, 'generateVariants'])->name('products.generate-variants');

    // Order Lifecycle Management & Dispatch
    Route::get('/orders', [OrderAdminController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderAdminController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/transition', [OrderAdminController::class, 'transitionStatus'])->name('orders.transition');

    // Merchant Anti-Fraud Directory (Blacklist & Whitelist)
    Route::get('/fraud-directory', [FraudDirectoryAdminController::class, 'index'])->name('fraud.index');
    Route::post('/fraud-directory', [FraudDirectoryAdminController::class, 'store'])->name('fraud.store');
    Route::delete('/fraud-directory/{customerFraudList}', [FraudDirectoryAdminController::class, 'destroy'])->name('fraud.destroy');

    // Dual Sourcing Procurement (Import & Local Domestic)
    Route::prefix('procurement')->name('procurement.')->group(function () {
        Route::get('/suppliers', [ProcurementController::class, 'suppliers'])->name('suppliers');
        Route::post('/suppliers', [ProcurementController::class, 'storeSupplier'])->name('suppliers.store');
        Route::get('/import-orders', [ProcurementController::class, 'importOrders'])->name('import-orders');
        Route::post('/import-orders', [ProcurementController::class, 'storeImportOrder'])->name('import-orders.store');
        Route::get('/local-pos', [ProcurementController::class, 'localPos'])->name('local-pos');
        Route::post('/local-pos', [ProcurementController::class, 'storeLocalPo'])->name('local-pos.store');
        Route::post('/local-pos/{po}/issue-mushak66', [ProcurementController::class, 'issueMushak66'])->name('local-pos.issue-mushak66');
        Route::get('/grn', [ProcurementController::class, 'grnIndex'])->name('grn');
        Route::post('/grn/{grn}/receive', [ProcurementController::class, 'receiveGrn'])->name('grn.receive');
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/media-storage', [MediaStorageController::class, 'index'])->name('media-storage.index');
        Route::post('/media-storage', [MediaStorageController::class, 'store'])->name('media-storage.store');
        Route::post('/media-storage/test', [MediaStorageController::class, 'test'])->name('media-storage.test');
        Route::delete('/media-storage/{setting}', [MediaStorageController::class, 'destroy'])->name('media-storage.destroy');

        // Bot Protection & CAPTCHA Settings
        Route::get('/bot-security', [BotSecuritySettingsController::class, 'index'])->name('bot-security.index');
        Route::post('/bot-security', [BotSecuritySettingsController::class, 'save'])->name('bot-security.save');
        Route::post('/bot-security/test', [BotSecuritySettingsController::class, 'testConnection'])->name('bot-security.test');

        Route::get('/system', [SystemInfoController::class, 'index'])->name('system');
    });
});

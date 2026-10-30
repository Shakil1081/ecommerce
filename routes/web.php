<?php

declare(strict_types=1);

use App\Modules\Admin\Http\Controllers\AiAdminController;
use App\Modules\Admin\Http\Controllers\AuditLogAdminController;
use App\Modules\Admin\Http\Controllers\BotSecuritySettingsController;
use App\Modules\Admin\Http\Controllers\CatalogController;
use App\Modules\Admin\Http\Controllers\CommunicationAdminController;
use App\Modules\Admin\Http\Controllers\CourierAdminController;
use App\Modules\Admin\Http\Controllers\FraudDirectoryAdminController;
use App\Modules\Admin\Http\Controllers\MediaStorageController;
use App\Modules\Admin\Http\Controllers\OrderAdminController;
use App\Modules\Admin\Http\Controllers\PaymentAdminController;
use App\Modules\Admin\Http\Controllers\ProcurementController;
use App\Modules\Admin\Http\Controllers\RoleController;
use App\Modules\Admin\Http\Controllers\StaffUserController;
use App\Modules\Admin\Http\Controllers\SystemInfoController;
use App\Modules\Admin\Http\Controllers\TaxComplianceAdminController;
use App\Modules\Admin\Http\Controllers\TradingPnLAdminController;
use App\Modules\Cart\Http\Controllers\CartController;
use App\Modules\Communication\Http\Controllers\SocialCommerceFeedController;
use App\Modules\Communication\Http\Controllers\VoiceCallWebhookController;
use App\Modules\Communication\Http\Controllers\WhatsAppWebhookController;
use App\Modules\Customer\Http\Controllers\OrderTrackingController;
use App\Modules\Logistics\Http\Controllers\PathaoWebhookController;
use App\Modules\Logistics\Http\Controllers\ShippingLabelController;
use App\Modules\Logistics\Http\Controllers\SteadfastWebhookController;
use App\Modules\Payment\Http\Controllers\BkashCallbackController;
use App\Modules\Payment\Http\Controllers\NagadCallbackController;
use App\Modules\Payment\Http\Controllers\ProcessAdvanceDeliveryPaymentController;
use App\Modules\Payment\Http\Controllers\SslcommerzCallbackController;
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

// Social Commerce Product Feeds
Route::prefix('feeds')->name('feeds.')->group(function () {
    Route::get('/google-merchant.xml', [SocialCommerceFeedController::class, 'googleMerchant'])->name('google-merchant');
    Route::get('/facebook-catalog.csv', [SocialCommerceFeedController::class, 'facebookCatalog'])->name('facebook-catalog');
    Route::get('/tiktok-shop.csv', [SocialCommerceFeedController::class, 'tiktokCatalog'])->name('tiktok-shop');
});

// Storefront Self-Service Order Tracking Portal
Route::get('/track-order', [OrderTrackingController::class, 'index'])->name('storefront.order-tracking');
Route::post('/track-order', [OrderTrackingController::class, 'search'])->name('storefront.order-tracking.search');

// Storefront Checkout & Order Confirmation
Route::get('/checkout', function () {
    return Inertia::render('Storefront/Checkout');
})->name('storefront.checkout');

Route::get('/orders/confirmed/{order}', function (string $order) {
    return Inertia::render('Storefront/OrderConfirmed', ['orderNumber' => $order]);
})->name('storefront.order-confirmed');

Route::post('/orders/{order}/pay-advance', ProcessAdvanceDeliveryPaymentController::class)->name('orders.pay-advance');

// Payment Callbacks & Webhooks
Route::prefix('payment')->name('payment.')->group(function () {
    Route::get('/bkash/callback', BkashCallbackController::class)->name('bkash.callback');
    Route::get('/nagad/callback', NagadCallbackController::class)->name('nagad.callback');

    // SSLCommerz Return & IPN Routes
    Route::match(['GET', 'POST'], '/sslcommerz/success', [SslcommerzCallbackController::class, 'success'])->name('sslcommerz.success');
    Route::match(['GET', 'POST'], '/sslcommerz/fail', [SslcommerzCallbackController::class, 'fail'])->name('sslcommerz.fail');
    Route::match(['GET', 'POST'], '/sslcommerz/cancel', [SslcommerzCallbackController::class, 'cancel'])->name('sslcommerz.cancel');
    Route::post('/sslcommerz/ipn', [SslcommerzCallbackController::class, 'ipn'])->name('sslcommerz.ipn');
});

// Logistics Courier Webhooks
Route::prefix('webhooks/courier')->name('webhooks.courier.')->group(function () {
    Route::post('/steadfast', SteadfastWebhookController::class)->name('steadfast');
    Route::post('/pathao', PathaoWebhookController::class)->name('pathao');
});

// Omnichannel WhatsApp Webhook
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])->name('webhooks.whatsapp.handle');

// AI Automated Voice Calling Webhooks
Route::prefix('webhooks/voice')->name('webhooks.voice.')->group(function () {
    Route::match(['GET', 'POST'], '/twiml', [VoiceCallWebhookController::class, 'twiml'])->name('twiml');
    Route::post('/gather', [VoiceCallWebhookController::class, 'gather'])->name('gather');
    Route::post('/status', [VoiceCallWebhookController::class, 'status'])->name('status');
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
    Route::post('/orders/{order}/book-courier', [ShippingLabelController::class, 'book'])->name('orders.book-courier');
    Route::post('/orders/bulk-book-courier', [ShippingLabelController::class, 'bulkBook'])->name('orders.bulk-book-courier');
    Route::get('/orders/{order}/shipping-label', [ShippingLabelController::class, 'show'])->name('orders.shipping-label');
    Route::match(['GET', 'POST'], '/orders/bulk-shipping-labels', [ShippingLabelController::class, 'bulk'])->name('orders.bulk-shipping-labels');

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

        // Dynamic Payment Gateway Settings
        Route::get('/payment-gateways', [PaymentAdminController::class, 'index'])->name('payment-gateways.index');
        Route::post('/payment-gateways', [PaymentAdminController::class, 'save'])->name('payment-gateways.save');
        Route::post('/payment-gateways/test', [PaymentAdminController::class, 'testConnection'])->name('payment-gateways.test');

        // Dynamic Courier Logistics Settings
        Route::get('/couriers', [CourierAdminController::class, 'index'])->name('couriers.index');
        Route::post('/couriers/test', [CourierAdminController::class, 'testConnection'])->name('couriers.test');
        Route::post('/couriers/{courierGatewaySetting}', [CourierAdminController::class, 'update'])->name('couriers.update');

        Route::get('/system', [SystemInfoController::class, 'index'])->name('system');
    });

    // Courier Logistics Hub & Payout Reconciliation
    Route::prefix('logistics')->name('logistics.')->group(function () {
        Route::get('/zones', [CourierAdminController::class, 'zones'])->name('zones');
        Route::post('/zones/{zone}/rate', [CourierAdminController::class, 'updateZoneRate'])->name('zones.rate');
        Route::get('/cod-reconciliation', [CourierAdminController::class, 'codReconciliation'])->name('reconciliation');
        Route::post('/cod-reconciliation', [CourierAdminController::class, 'storeDisbursement'])->name('reconciliation.store');
    });

    // Omnichannel Communication Settings (SMTP, SMS, Voice AI)
    Route::prefix('communication')->name('communication.')->group(function () {
        Route::get('/mail-settings', [CommunicationAdminController::class, 'mailSettings'])->name('mail');
        Route::post('/mail-settings', [CommunicationAdminController::class, 'updateMailSettings'])->name('mail.update');
        Route::post('/mail-settings/test', [CommunicationAdminController::class, 'testMailSettings'])->name('mail.test');

        Route::get('/sms-settings', [CommunicationAdminController::class, 'smsSettings'])->name('sms');
        Route::post('/sms-settings/test', [CommunicationAdminController::class, 'testSmsConnection'])->name('sms.test');
        Route::post('/sms-settings/balance', [CommunicationAdminController::class, 'checkSmsBalance'])->name('sms.balance');
        Route::post('/sms-settings/{setting}', [CommunicationAdminController::class, 'updateSmsSetting'])->name('sms.update');

        Route::get('/voice-settings', [CommunicationAdminController::class, 'voiceSettings'])->name('voice');
        Route::post('/voice-settings/test', [CommunicationAdminController::class, 'testVoiceCall'])->name('voice.test');
        Route::post('/voice-settings/{setting}', [CommunicationAdminController::class, 'updateVoiceSetting'])->name('voice.update');
    });

    // Marketing & Abandoned Cart Recovery
    Route::prefix('marketing')->name('marketing.')->group(function () {
        Route::get('/cart-recovery', [CommunicationAdminController::class, 'cartRecoveryIndex'])->name('cart-recovery');
        Route::post('/cart-recovery/trigger', [CommunicationAdminController::class, 'triggerManualRecovery'])->name('cart-recovery.trigger');
    });

    // Tax & NBR Statutory Compliance
    Route::prefix('tax-compliance')->name('tax-compliance.')->group(function () {
        Route::get('/', [TaxComplianceAdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/mushak-6-3', [TaxComplianceAdminController::class, 'mushak63Index'])->name('mushak-6-3.index');
        Route::get('/mushak-6-3/{invoice}', [TaxComplianceAdminController::class, 'mushak63Show'])->name('mushak-6-3.show');
        Route::get('/mushak-9-1', [TaxComplianceAdminController::class, 'mushak91Index'])->name('mushak-9-1.index');
        Route::post('/mushak-9-1/compile', [TaxComplianceAdminController::class, 'mushak91Compile'])->name('mushak-9-1.compile');
        Route::post('/mushak-9-1/{return}/submit', [TaxComplianceAdminController::class, 'mushak91Submit'])->name('mushak-9-1.submit');
        Route::get('/mushak-6-6', [TaxComplianceAdminController::class, 'mushak66Index'])->name('mushak-6-6.index');
        Route::get('/rma', [TaxComplianceAdminController::class, 'rmaIndex'])->name('rma.index');
        Route::get('/registers', [TaxComplianceAdminController::class, 'registers'])->name('registers.index');
    });

    // AI Intelligence Engine Settings
    Route::prefix('ai-settings')->name('ai.')->group(function () {
        Route::get('/', [AiAdminController::class, 'index'])->name('index');
        Route::post('/test', [AiAdminController::class, 'test'])->name('test');
        Route::post('/{setting}', [AiAdminController::class, 'update'])->name('update');
    });

    // Executive Trading Profit & Loss (P&L) Analytics
    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/trading-pnl', [TradingPnLAdminController::class, 'index'])->name('pnl');
    });

    // Immutable Enterprise Audit Trail
    Route::prefix('audit-logs')->name('audit.')->group(function () {
        Route::get('/', [AuditLogAdminController::class, 'index'])->name('index');
    });
});

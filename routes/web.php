<?php

declare(strict_types=1);

use App\Modules\Admin\Http\Controllers\AiAdminController;
use App\Modules\Admin\Http\Controllers\AuditLogAdminController;
use App\Modules\Admin\Http\Controllers\AuthController;
use App\Modules\Admin\Http\Controllers\BotSecuritySettingsController;
use App\Modules\Admin\Http\Controllers\CatalogController;
use App\Modules\Admin\Http\Controllers\CategoryAdminController;
use App\Modules\Admin\Http\Controllers\CommunicationAdminController;
use App\Modules\Admin\Http\Controllers\CourierAdminController;
use App\Modules\Admin\Http\Controllers\FraudDirectoryAdminController;
use App\Modules\Admin\Http\Controllers\MediaStorageController;
use App\Modules\Admin\Http\Controllers\OrderAdminController;
use App\Modules\Admin\Http\Controllers\PaymentAdminController;
use App\Modules\Admin\Http\Controllers\ProcurementController;
use App\Modules\Admin\Http\Controllers\ProductReviewAdminController;
use App\Modules\Admin\Http\Controllers\RoleController;
use App\Modules\Admin\Http\Controllers\StaffUserController;
use App\Modules\Admin\Http\Controllers\StorePageAdminController;
use App\Modules\Admin\Http\Controllers\SystemInfoController;
use App\Modules\Admin\Http\Controllers\TaxComplianceAdminController;
use App\Modules\Admin\Http\Controllers\TradingPnLAdminController;
use App\Modules\Cart\Http\Controllers\CartController;
use App\Modules\Catalog\Http\Controllers\ProductReviewController;
use App\Modules\Catalog\Http\Controllers\StorefrontController;
use App\Modules\Communication\Http\Controllers\SocialCommerceFeedController;
use App\Modules\Communication\Http\Controllers\VoiceCallWebhookController;
use App\Modules\Communication\Http\Controllers\WhatsAppWebhookController;
use App\Modules\Content\Http\Controllers\StorePageController;
use App\Modules\Content\Models\StorePage;
use App\Modules\Customer\Http\Controllers\AccountController;
use App\Modules\Customer\Http\Controllers\CustomerAddressController;
use App\Modules\Customer\Http\Controllers\CustomerAuthController;
use App\Modules\Customer\Http\Controllers\OrderTrackingController;
use App\Modules\Customer\Http\Controllers\WishlistController;
use App\Modules\Logistics\Http\Controllers\PathaoWebhookController;
use App\Modules\Logistics\Http\Controllers\ShippingLabelController;
use App\Modules\Logistics\Http\Controllers\SteadfastWebhookController;
use App\Modules\Order\Http\Controllers\CheckoutController;
use App\Modules\Order\Http\Controllers\DirectOrderController;
use App\Modules\Order\Http\Controllers\OrderVerificationController;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Http\Controllers\BkashCallbackController;
use App\Modules\Payment\Http\Controllers\NagadCallbackController;
use App\Modules\Payment\Http\Controllers\ProcessAdvanceDeliveryPaymentController;
use App\Modules\Payment\Http\Controllers\SslcommerzCallbackController;
use App\Modules\Seo\Http\Controllers\SitemapController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Authentication (Login & Logout)
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->name('login.store');
Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
Route::post('/admin/logout', [AuthController::class, 'destroy'])->name('admin.logout');
Route::get('/logout', [AuthController::class, 'destroy']);

// Customer-Facing Storefront & Shop Catalog
Route::get('/', [StorefrontController::class, 'home'])->name('storefront.home');
Route::get('/shop', [StorefrontController::class, 'index'])->name('storefront.shop');
Route::get('/products/{slug}', [StorefrontController::class, 'show'])->name('storefront.product.show');
Route::post('/products/{slug}/reviews', [ProductReviewController::class, 'store'])->middleware(['ensure.human', 'throttle:reviews'])->name('storefront.product.reviews.store');

// Store information & policy pages (admin-editable: Admin > Content > Store Pages)
Route::get('/{slug}', [StorePageController::class, 'show'])->whereIn('slug', StorePage::SYSTEM_SLUGS)->name('storefront.page');

// Customer accounts (storefront). Staff use /login.
Route::prefix('account')->name('account.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [CustomerAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [CustomerAuthController::class, 'login'])->middleware('throttle:customer-auth')->name('login.store');
        Route::get('/register', [CustomerAuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [CustomerAuthController::class, 'register'])->middleware(['ensure.human', 'throttle:customer-auth'])->name('register.store');
        Route::get('/forgot-password', [CustomerAuthController::class, 'showForgotPassword'])->name('password.request');
        Route::post('/forgot-password', [CustomerAuthController::class, 'sendResetLink'])->middleware(['ensure.human', 'throttle:customer-auth'])->name('password.email');
        Route::post('/reset-password', [CustomerAuthController::class, 'resetPassword'])->middleware('throttle:customer-auth')->name('password.update');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');
        Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');
        Route::get('/orders', [AccountController::class, 'orders'])->name('orders.index');
        Route::get('/orders/{order}', [AccountController::class, 'showOrder'])->name('orders.show');
        Route::post('/orders/{order}/returns', [AccountController::class, 'requestReturn'])->middleware('throttle:customer-auth')->name('orders.returns.store');
        Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
        Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
        Route::put('/password', [AccountController::class, 'updatePassword'])->name('password.change');
        Route::get('/addresses', [CustomerAddressController::class, 'index'])->name('addresses.index');
        Route::post('/addresses', [CustomerAddressController::class, 'store'])->name('addresses.store');
        Route::put('/addresses/{address}', [CustomerAddressController::class, 'update'])->name('addresses.update');
        Route::delete('/addresses/{address}', [CustomerAddressController::class, 'destroy'])->name('addresses.destroy');
        Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
        Route::post('/wishlist/{product}', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    });
});

// Laravel's password reset email links to the route named `password.reset`
Route::get('/account/reset-password/{token}', [CustomerAuthController::class, 'showResetPassword'])->middleware('guest')->name('password.reset');

// Shopping Cart & Slide-Out Drawer Endpoints
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::get('/cart/drawer', [CartController::class, 'index'])->name('cart.drawer');
Route::post('/cart/items', [CartController::class, 'addItem'])->name('cart.items.add');
Route::patch('/cart/items/{variantId}', [CartController::class, 'updateQuantity'])->name('cart.items.update');
Route::delete('/cart/items/{variantId}', [CartController::class, 'removeItem'])->name('cart.items.remove');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->middleware('throttle:storefront-lookup')->name('cart.coupon.apply');
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
Route::post('/track-order', [OrderTrackingController::class, 'search'])->middleware('throttle:storefront-lookup')->name('storefront.order-tracking.search');

// Storefront Checkout & Fast Funnel Order
Route::get('/checkout', [CheckoutController::class, 'index'])->name('storefront.checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware(['ensure.human', 'throttle:checkout'])->name('storefront.checkout.store');
Route::post('/checkout/fast-order', [DirectOrderController::class, 'store'])->middleware(['ensure.human', 'throttle:checkout'])->name('storefront.fast-order');

// SMS OTP confirmation for medium/high-risk orders (AGENTS.md §8)
Route::get('/orders/{order}/verify', [OrderVerificationController::class, 'show'])->name('storefront.order-verify');
Route::post('/orders/{order}/verify', [OrderVerificationController::class, 'verify'])->middleware('throttle:otp-verify')->name('storefront.order-verify.submit');
Route::post('/orders/{order}/verify/resend', [OrderVerificationController::class, 'resend'])->middleware('throttle:otp-resend')->name('storefront.order-verify.resend');

Route::get('/orders/confirmed/{order}', function (string $order) {
    $orderModel = Order::with(['items', 'district'])->where('order_number', $order)->first();

    if ($orderModel?->requiresOtpVerification()) {
        return redirect()->route('storefront.order-verify', ['order' => $orderModel->order_number]);
    }

    return Inertia::render('Storefront/OrderConfirmed', [
        'orderNumber' => $order,
        'order' => $orderModel ? [
            'order_number' => $orderModel->order_number,
            'customer_name' => $orderModel->customer_name,
            'customer_phone' => $orderModel->customer_phone,
            'delivery_address' => $orderModel->delivery_address,
            'delivery_zone' => $orderModel->delivery_zone,
            'payment_method' => $orderModel->payment_method,
            'subtotal_bdt' => (float) $orderModel->subtotal_bdt,
            'shipping_fee_bdt' => (float) $orderModel->shipping_fee_bdt,
            'total_bdt' => (float) $orderModel->total_bdt,
            'items' => $orderModel->items->map(fn ($item) => [
                'name' => $item->product_name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'price' => (float) $item->unit_price_bdt,
                'total' => (float) $item->subtotal_bdt,
            ]),
        ] : null,
    ]);
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

/*
|--------------------------------------------------------------------------
| Back-Office (Admin) Routes
|--------------------------------------------------------------------------
| Every admin route requires: an authenticated session (`auth`), a staff account holding
| at least one role (`staff`), and the module permission for the action (`permission:`).
| Reads require the module's `.view` permission; mutations require the specific write permission.
| Super Admin bypasses permission checks via Gate::before (AppServiceProvider).
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'staff'])->group(function () {
    // Land each staff member on the first back-office area their role can access
    Route::get('/', function (Request $request) {
        $user = $request->user();

        $landing = collect([
            'analytics.view' => 'admin.analytics.pnl',
            'orders.view' => 'admin.orders.index',
            'catalog.view' => 'admin.products.index',
            'procurement.view' => 'admin.procurement.suppliers',
            'tax.view' => 'admin.tax-compliance.dashboard',
            'logistics.view' => 'admin.logistics.delivery-overview',
            'settings.view' => 'admin.settings.system',
        ])->first(fn (string $route, string $permission): bool => $user->can($permission));

        abort_if($landing === null, 403, 'Your role has no back-office modules assigned.');

        return redirect()->route($landing);
    })->name('dashboard');
    Route::redirect('/dashboard', '/admin');
    Route::redirect('/catalog/products', '/admin/products');
    Route::redirect('/catalog', '/admin/products');

    // Staff, Roles & Permissions (RBAC)
    Route::middleware('permission:rbac.manage')->group(function () {
        Route::resource('roles', RoleController::class);
        Route::resource('staff', StaffUserController::class);
    });

    // Categories Management
    Route::resource('categories', CategoryAdminController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', 'permission:catalog.view')
        ->middlewareFor('store', 'permission:catalog.create')
        ->middlewareFor('update', 'permission:catalog.edit')
        ->middlewareFor('destroy', 'permission:catalog.delete');

    // Customer product review moderation
    Route::get('reviews', [ProductReviewAdminController::class, 'index'])->middleware('permission:catalog.view')->name('reviews.index');
    Route::post('reviews/{review}/moderate', [ProductReviewAdminController::class, 'moderate'])->middleware('permission:catalog.edit')->name('reviews.moderate');

    // Storefront content & policy pages (About, Contact, Privacy, Terms, Returns, Shipping)
    Route::get('pages', [StorePageAdminController::class, 'index'])->middleware('permission:settings.view')->name('pages.index');
    Route::get('pages/{page}/edit', [StorePageAdminController::class, 'edit'])->middleware('permission:settings.view')->name('pages.edit');
    Route::put('pages/{page}', [StorePageAdminController::class, 'update'])->middleware('permission:settings.edit')->name('pages.update');

    // Catalog & Matrix Variant Management
    Route::resource('products', CatalogController::class)
        ->middlewareFor(['index', 'show'], 'permission:catalog.view')
        ->middlewareFor(['create', 'store'], 'permission:catalog.create')
        ->middlewareFor(['edit', 'update'], 'permission:catalog.edit')
        ->middlewareFor('destroy', 'permission:catalog.delete');
    Route::get('products/{product}/matrix', [CatalogController::class, 'matrix'])->middleware('permission:catalog.view')->name('products.matrix');
    Route::post('products/{product}/generate-variants', [CatalogController::class, 'generateVariants'])->middleware('permission:catalog.edit')->name('products.generate-variants');
    Route::delete('products/{product}/variants/{variant}', [CatalogController::class, 'deleteVariant'])->middleware('permission:catalog.delete')->name('products.variants.destroy');
    Route::delete('products/{product}/media/{media}', [CatalogController::class, 'destroyMedia'])->middleware('permission:catalog.edit')->name('products.media.destroy');

    // Order Lifecycle Management & Dispatch
    Route::middleware('permission:orders.view')->group(function () {
        Route::get('/orders', [OrderAdminController::class, 'index'])->name('orders.index');
        Route::get('/orders/scan-returns', [OrderAdminController::class, 'scanReturnsView'])->name('orders.scan-returns');
        Route::get('/orders/scan-handover', [OrderAdminController::class, 'scanHandoverView'])->name('orders.scan-handover');
    });
    Route::middleware('permission:orders.edit')->group(function () {
        Route::post('/orders/bulk-confirm', [OrderAdminController::class, 'bulkConfirm'])->name('orders.bulk-confirm');
        Route::post('/orders/bulk-transition', [OrderAdminController::class, 'bulkTransition'])->name('orders.bulk-transition');
        Route::post('/orders/scan-returns/process', [OrderAdminController::class, 'processBarcodeScanReturn'])->name('orders.scan-returns.process');
    });
    Route::middleware('permission:logistics.book')->group(function () {
        Route::match(['GET', 'POST'], '/orders/bag-tickets', [OrderAdminController::class, 'printBagTickets'])->name('orders.bag-tickets');
        Route::post('/orders/bulk-book-courier', [ShippingLabelController::class, 'bulkBook'])->name('orders.bulk-book-courier');
        Route::match(['GET', 'POST'], '/orders/bulk-shipping-labels', [ShippingLabelController::class, 'bulk'])->name('orders.bulk-shipping-labels');
        Route::post('/orders/scan-handover/process', [OrderAdminController::class, 'processBarcodeScanHandover'])->name('orders.scan-handover.process');
        Route::get('/orders/scan-handover/manifest', [OrderAdminController::class, 'printHandoverManifest'])->name('orders.scan-handover.manifest');
    });
    Route::get('/orders/{order}', [OrderAdminController::class, 'show'])->middleware('permission:orders.view')->name('orders.show');
    Route::post('/orders/{order}/transition', [OrderAdminController::class, 'transitionStatus'])->middleware('permission:orders.edit')->name('orders.transition');
    Route::post('/orders/{order}/book-courier', [ShippingLabelController::class, 'book'])->middleware('permission:logistics.book')->name('orders.book-courier');
    Route::get('/orders/{order}/shipping-label', [ShippingLabelController::class, 'show'])->middleware('permission:logistics.book')->name('orders.shipping-label');

    // Merchant Anti-Fraud Directory (Blacklist & Whitelist)
    Route::middleware('permission:orders.fraud_review')->group(function () {
        Route::get('/fraud-directory', [FraudDirectoryAdminController::class, 'index'])->name('fraud.index');
        Route::post('/fraud-directory', [FraudDirectoryAdminController::class, 'store'])->name('fraud.store');
        Route::delete('/fraud-directory/{customerFraudList}', [FraudDirectoryAdminController::class, 'destroy'])->name('fraud.destroy');
    });

    // Dual Sourcing Procurement (Import & Local Domestic)
    Route::prefix('procurement')->name('procurement.')->group(function () {
        Route::middleware('permission:procurement.view')->group(function () {
            Route::get('/suppliers', [ProcurementController::class, 'suppliers'])->name('suppliers');
            Route::get('/import-orders', [ProcurementController::class, 'importOrders'])->name('import-orders');
            Route::get('/local-pos', [ProcurementController::class, 'localPos'])->name('local-pos');
            Route::get('/grn', [ProcurementController::class, 'grnIndex'])->name('grn');
        });
        Route::middleware('permission:procurement.create')->group(function () {
            Route::post('/suppliers', [ProcurementController::class, 'storeSupplier'])->name('suppliers.store');
            Route::post('/import-orders', [ProcurementController::class, 'storeImportOrder'])->name('import-orders.store');
            Route::post('/local-pos', [ProcurementController::class, 'storeLocalPo'])->name('local-pos.store');
        });
        Route::post('/local-pos/{po}/issue-mushak66', [ProcurementController::class, 'issueMushak66'])->middleware('permission:tax.mushak_6_6')->name('local-pos.issue-mushak66');
        Route::post('/grn/{grn}/receive', [ProcurementController::class, 'receiveGrn'])->middleware('permission:inventory.adjust')->name('grn.receive');
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::middleware('permission:settings.view')->group(function () {
            Route::get('/media-storage', [MediaStorageController::class, 'index'])->name('media-storage.index');
            Route::get('/bot-security', [BotSecuritySettingsController::class, 'index'])->name('bot-security.index');
            Route::get('/payment-gateways', [PaymentAdminController::class, 'index'])->name('payment-gateways.index');
            Route::get('/system', [SystemInfoController::class, 'index'])->name('system');
        });

        Route::middleware('permission:settings.edit')->group(function () {
            // Multi-Cloud Media Storage
            Route::post('/media-storage', [MediaStorageController::class, 'store'])->name('media-storage.store');
            Route::post('/media-storage/test', [MediaStorageController::class, 'test'])->name('media-storage.test');
            Route::delete('/media-storage/{setting}', [MediaStorageController::class, 'destroy'])->name('media-storage.destroy');

            // Bot Protection & CAPTCHA Settings
            Route::post('/bot-security', [BotSecuritySettingsController::class, 'save'])->name('bot-security.save');
            Route::post('/bot-security/test', [BotSecuritySettingsController::class, 'testConnection'])->name('bot-security.test');

            // Dynamic Payment Gateway Settings
            Route::post('/payment-gateways', [PaymentAdminController::class, 'save'])->name('payment-gateways.save');
            Route::post('/payment-gateways/test', [PaymentAdminController::class, 'testConnection'])->name('payment-gateways.test');
        });

        // Dynamic Courier Logistics Settings
        Route::middleware('permission:logistics.manage_couriers')->group(function () {
            Route::get('/couriers', [CourierAdminController::class, 'index'])->name('couriers.index');
            Route::post('/couriers/test', [CourierAdminController::class, 'testConnection'])->name('couriers.test');
            Route::post('/couriers/{courierGatewaySetting}', [CourierAdminController::class, 'update'])->name('couriers.update');
        });
    });

    // Courier Logistics Hub & Payout Reconciliation
    Route::prefix('logistics')->name('logistics.')->group(function () {
        Route::middleware('permission:logistics.view')->group(function () {
            Route::get('/delivery-overview', [CourierAdminController::class, 'deliveryOverview'])->name('delivery-overview');
            Route::get('/delivery-overview/export-csv', [CourierAdminController::class, 'exportDeliveryCsv'])->name('delivery-overview.export-csv');
            Route::get('/zones', [CourierAdminController::class, 'zones'])->name('zones');
            Route::get('/cod-reconciliation', [CourierAdminController::class, 'codReconciliation'])->name('reconciliation');
        });
        Route::middleware('permission:logistics.manage_couriers')->group(function () {
            Route::post('/zones/{zone}/rate', [CourierAdminController::class, 'updateZoneRate'])->name('zones.rate');
            Route::post('/cod-reconciliation', [CourierAdminController::class, 'storeDisbursement'])->name('reconciliation.store');
        });
    });

    // Omnichannel Communication Settings (SMTP, SMS, Voice AI)
    Route::prefix('communication')->name('communication.')->group(function () {
        Route::middleware('permission:settings.view')->group(function () {
            Route::get('/mail-settings', [CommunicationAdminController::class, 'mailSettings'])->name('mail');
            Route::get('/sms-settings', [CommunicationAdminController::class, 'smsSettings'])->name('sms');
            Route::get('/voice-settings', [CommunicationAdminController::class, 'voiceSettings'])->name('voice');
        });
        Route::middleware('permission:settings.edit')->group(function () {
            Route::post('/mail-settings', [CommunicationAdminController::class, 'updateMailSettings'])->name('mail.update');
            Route::post('/mail-settings/test', [CommunicationAdminController::class, 'testMailSettings'])->name('mail.test');

            Route::post('/sms-settings/test', [CommunicationAdminController::class, 'testSmsConnection'])->name('sms.test');
            Route::post('/sms-settings/balance', [CommunicationAdminController::class, 'checkSmsBalance'])->name('sms.balance');
            Route::post('/sms-settings/{setting}', [CommunicationAdminController::class, 'updateSmsSetting'])->name('sms.update');

            Route::post('/voice-settings/test', [CommunicationAdminController::class, 'testVoiceCall'])->name('voice.test');
            Route::post('/voice-settings/{setting}', [CommunicationAdminController::class, 'updateVoiceSetting'])->name('voice.update');
        });
    });

    // Marketing & Abandoned Cart Recovery
    Route::prefix('marketing')->name('marketing.')->group(function () {
        Route::get('/cart-recovery', [CommunicationAdminController::class, 'cartRecoveryIndex'])->middleware('permission:orders.view')->name('cart-recovery');
        Route::post('/cart-recovery/trigger', [CommunicationAdminController::class, 'triggerManualRecovery'])->middleware('permission:orders.edit')->name('cart-recovery.trigger');
    });

    // Tax & NBR Statutory Compliance
    Route::prefix('tax-compliance')->name('tax-compliance.')->group(function () {
        Route::middleware('permission:tax.view')->group(function () {
            Route::get('/', [TaxComplianceAdminController::class, 'dashboard'])->name('dashboard');
            Route::get('/mushak-6-3', [TaxComplianceAdminController::class, 'mushak63Index'])->name('mushak-6-3.index');
            Route::get('/mushak-6-3/{invoice}', [TaxComplianceAdminController::class, 'mushak63Show'])->name('mushak-6-3.show');
            Route::get('/mushak-9-1', [TaxComplianceAdminController::class, 'mushak91Index'])->name('mushak-9-1.index');
            Route::get('/mushak-6-6', [TaxComplianceAdminController::class, 'mushak66Index'])->name('mushak-6-6.index');
            Route::get('/rma', [TaxComplianceAdminController::class, 'rmaIndex'])->name('rma.index');
            Route::get('/mushak-6-7/{creditNote}', [TaxComplianceAdminController::class, 'mushak67Show'])->name('mushak-6-7.show');
            Route::get('/registers', [TaxComplianceAdminController::class, 'registers'])->name('registers.index');
        });
        Route::middleware('permission:tax.mushak_9_1')->group(function () {
            Route::post('/mushak-9-1/compile', [TaxComplianceAdminController::class, 'mushak91Compile'])->name('mushak-9-1.compile');
            Route::post('/mushak-9-1/{return}/submit', [TaxComplianceAdminController::class, 'mushak91Submit'])->name('mushak-9-1.submit');
        });
        // RMA inspection issues the Mushak 6.7 credit note (statutory tax document)
        Route::post('/rma/{rma}/inspect', [TaxComplianceAdminController::class, 'rmaInspect'])->middleware('permission:tax.mushak_6_3')->name('rma.inspect');
    });

    // AI Intelligence Engine Settings
    Route::prefix('ai-settings')->name('ai.')->group(function () {
        Route::get('/', [AiAdminController::class, 'index'])->middleware('permission:settings.view')->name('index');
        Route::post('/test', [AiAdminController::class, 'test'])->middleware('permission:settings.edit')->name('test');
        Route::post('/{setting}', [AiAdminController::class, 'update'])->middleware('permission:settings.edit')->name('update');
    });

    // Executive Trading Profit & Loss (P&L) Analytics
    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/trading-pnl', [TradingPnLAdminController::class, 'index'])->middleware('permission:analytics.view')->name('pnl');
    });

    // Immutable Enterprise Audit Trail
    Route::prefix('audit-logs')->name('audit.')->group(function () {
        Route::get('/', [AuditLogAdminController::class, 'index'])->middleware('permission:audit.view')->name('index');
    });
});

<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Implicitly grant 'Super Admin' role all permissions
        // Checks return true for Super Admin, or null to fall through to other gates/policies
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'hasRole') && $user->hasRole('Super Admin') ? true : null;
        });

        $this->configureRateLimiting();
        $this->configureTrustedProxies();
    }

    /**
     * Resolve the real client IP behind Cloudflare / nginx / load balancers (config/security.php).
     */
    private function configureTrustedProxies(): void
    {
        $proxies = config('security.trusted_proxies');

        if (! is_string($proxies) || trim($proxies) === '') {
            return;
        }

        TrustProxies::at(trim($proxies) === '*' ? '*' : array_map('trim', explode(',', $proxies)));
    }

    /**
     * Anti-abuse throttles for public storefront write endpoints.
     */
    private function configureRateLimiting(): void
    {
        // Order submission: blocks scripted fake-order floods and stock-exhaustion attacks
        RateLimiter::for('checkout', fn (Request $request): Limit => Limit::perMinute(6)->by($request->ip()));

        // Coupon brute-force and order-tracking enumeration (order ID + phone guessing)
        RateLimiter::for('storefront-lookup', fn (Request $request): Limit => Limit::perMinute(15)->by($request->ip()));

        // Order OTP: code guessing is also capped at 3 attempts per code by VerifyOtpAction
        RateLimiter::for('otp-verify', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        // OTP resend costs an SMS: 3 per 10 minutes per order + IP (plus a 60s cooldown in the controller)
        RateLimiter::for('otp-resend', fn (Request $request): Limit => Limit::perMinutes(10, 3)
            ->by($request->ip().'|'.(string) $request->route('order')));

        // Customer sign-up, login, password reset and return requests
        RateLimiter::for('customer-auth', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        // Product review spam
        RateLimiter::for('reviews', fn (Request $request): Limit => Limit::perHour(5)->by($request->ip()));
    }
}

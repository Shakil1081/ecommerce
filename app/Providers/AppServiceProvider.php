<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
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
    }
}

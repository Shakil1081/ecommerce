<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Admin\Http\Middleware\EnsureUserIsStaff;
use App\Modules\Common\Http\Middleware\EnsureHumanSubmission;
use App\Modules\Common\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'ensure.human' => EnsureHumanSubmission::class,
            'staff' => EnsureUserIsStaff::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Storefront customers sign in at /account/login; staff keep the hardened /login screen.
        $middleware->redirectGuestsTo(fn (Request $request): string => $request->is('account', 'account/*')
            ? route('account.login')
            : route('login'));
        $middleware->redirectUsersTo(fn (Request $request): string => $request->user()?->isStaff()
            ? route('admin.dashboard')
            : route('account.dashboard'));

        $middleware->validateCsrfTokens(except: [
            'payment/sslcommerz/*',
            'webhooks/courier/*',
            'webhooks/whatsapp',
            'webhooks/voice/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

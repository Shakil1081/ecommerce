<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Common\Http\Middleware\EnsureHumanSubmission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'ensure.human' => EnsureHumanSubmission::class,
        ]);

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

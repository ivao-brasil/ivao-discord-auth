<?php

use App\Exceptions\InactiveAccountException;
use App\Exceptions\InvalidIVAOTokenException;
use App\Exceptions\InvalidPermissionException;
use App\Infrastructure\Http\Middleware\Admin;
use App\Infrastructure\Http\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Cloudflare, so URLs are generated as https
        $middleware->trustProxies(at: '*');

        // Discord signs interaction requests instead of sending a CSRF token
        $middleware->validateCsrfTokens(except: ['discord/interactions']);

        $middleware->alias([
            'auth' => Authenticate::class,
            'admin' => Admin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Expected refusals shown to the member; the cause is logged where it happens
        $exceptions->dontReport([
            InactiveAccountException::class,
            InvalidIVAOTokenException::class,
            InvalidPermissionException::class,
        ]);
    })->create();

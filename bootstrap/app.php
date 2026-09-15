<?php

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

        $middleware->alias([
            'auth' => Authenticate::class,
            'admin' => Admin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})->create();

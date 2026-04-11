<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies (Cloudflare, load balancers, nginx reverse proxy).
        // In production, restrict to specific proxy IPs.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            // HTTP/2 push Link headers — must run before response is sent.
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            // Security headers on every response.
            \App\Http\Middleware\SecurityHeaders::class,
            // Share Inertia props. Authentication is handled by EnsureGuest and EnsureAuthenticated middleware.
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Rate limiting backed by Redis — accurate sliding window counters.
        $middleware->throttleWithRedis();

        // CSRF exceptions — broadcasting auth is proxied server-side.
        $middleware->validateCsrfTokens(except: [
            'broadcasting/auth',
        ]);

        // Register middleware aliases for route groups
        $middleware->alias([
            'guest' => \App\Http\Middleware\EnsureGuest::class,
            'auth' => \App\Http\Middleware\EnsureAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

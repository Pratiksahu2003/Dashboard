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
            // Resolve authenticated user from API (cached per session).
            \App\Http\Middleware\SyncApiUser::class,
            // Share Inertia props.
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Rate limiting backed by Redis — accurate sliding window counters.
        $middleware->throttleWithRedis();

        // CSRF exceptions — broadcasting auth is proxied server-side.
        $middleware->validateCsrfTokens(except: [
            'broadcasting/auth',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

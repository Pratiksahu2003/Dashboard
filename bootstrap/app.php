<?php

use App\Http\Middleware\EnsureAuthenticated;
use App\Http\Middleware\EnsureGuest;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HydrateInertiaApiUser;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

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
            AddLinkHeadersForPreloadedAssets::class,
            // Security headers on every response.
            SecurityHeaders::class,
            // Resolve API session user for Inertia `auth.user` on public pages (before shared props).
            HydrateInertiaApiUser::class,
            // Share Inertia props. Authentication is handled by EnsureGuest and EnsureAuthenticated middleware.
            HandleInertiaRequests::class,
        ]);

        // Rate limiting backed by Redis — accurate sliding window counters.
        $middleware->throttleWithRedis();

        // CSRF exceptions — broadcasting auth is proxied server-side.
        $middleware->validateCsrfTokens(except: [
            'broadcasting/auth',
        ]);

        // Register middleware aliases for route groups
        $middleware->alias([
            'guest' => EnsureGuest::class,
            'auth' => EnsureAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response) {
            $status = $response->getStatusCode();
            $errorStatuses = [404, 403, 500, 503, 419, 429];

            if (in_array($status, $errorStatuses) && !request()->expectsJson()) {
                return \Inertia\Inertia::render('Error', ['status' => $status])
                    ->toResponse(request())
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();

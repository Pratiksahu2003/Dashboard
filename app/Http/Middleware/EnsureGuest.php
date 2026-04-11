<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the user is NOT authenticated (guest).
 * If authenticated, redirects to the dashboard.
 * 
 * Uses ResolvesAuthState trait to check authentication status
 * from the /auth/user endpoint with caching.
 */
class EnsureGuest
{
    use ResolvesAuthState;

    public function handle(Request $request, Closure $next): Response
    {
        // Resolve authentication state from cache or /auth/user endpoint
        $authState = $this->resolveAuthState($request);

        // If authenticated, redirect to dashboard
        if ($authState['authenticated']) {
            return redirect()->route('dashboard');
        }

        // Not authenticated, allow access
        return $next($request);
    }
}

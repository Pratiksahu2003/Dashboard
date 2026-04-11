<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the user IS authenticated.
 * If not authenticated, redirects to the login page.
 * 
 * Uses ResolvesAuthState trait to resolve authentication state
 * from the /auth/user endpoint with caching.
 */
class EnsureAuthenticated
{
    use ResolvesAuthState;

    public function handle(Request $request, Closure $next): Response
    {
        $authState = $this->resolveAuthState($request);

        // If not authenticated, redirect to login
        if (!$authState['authenticated']) {
            return redirect()->route('login');
        }

        // Store user data for downstream use
        $request->attributes->set('api_user', $authState['user']);

        // Authenticated, allow access
        return $next($request);
    }
}

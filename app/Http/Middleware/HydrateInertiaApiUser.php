<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets `api_user` on the request for Inertia's shared `auth.user` closure on **public** routes.
 *
 * {@see EnsureAuthenticated} only runs on `auth` routes; without this, teacher profile (and other
 * public Inertia pages) always see `auth.user === null` even when the SPA session is valid.
 */
class HydrateInertiaApiUser
{
    use ResolvesAuthState;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->has('api_user')) {
            return $next($request);
        }

        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        $authState = $this->resolveAuthState($request, false);

        if (($authState['authenticated'] ?? false) && is_array($authState['user'] ?? null) && $authState['user'] !== []) {
            $request->attributes->set('api_user', $authState['user']);
        }

        return $next($request);
    }
}

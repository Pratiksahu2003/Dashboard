<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the authenticated user from the API server on every Inertia page request
 * and stores it in the request for HandleInertiaRequests to share as auth.user.
 *
 * The SPA has no local auth — Auth::user() is always null because login happens
 * on api.suganta.com. This middleware proxies the session cookie to the API to
 * resolve who is logged in, then makes the user available to Inertia shared props.
 */
class SyncApiUser
{
    /** Fields we expose to the frontend — never expose password, tokens, etc. */
    private const SAFE_FIELDS = [
        'id', 'name', 'first_name', 'last_name', 'email', 'role',
        'phone', 'profile_pic', 'email_verified_at',
        'registration_fee_status', 'payment_required', 'verification_status',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $apiOrigin = rtrim(config('services.suganta.api_origin', 'https://api.suganta.com'), '/');

        try {
            $response = Http::withCookies(
                    $request->cookies->all(),
                    parse_url($apiOrigin, PHP_URL_HOST)
                )
                ->withHeaders([
                    'Accept'           => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->timeout(5)
                ->get($apiOrigin . '/api/v1/profile');

            if ($response->successful()) {
                // Profile endpoint returns data.user
                $userData = $response->json('data.user') ?? $response->json('data') ?? null;

                if (is_array($userData) && !empty($userData)) {
                    // Whitelist safe fields before storing
                    $safe = array_intersect_key($userData, array_flip(self::SAFE_FIELDS));
                    $request->attributes->set('api_user', $safe);
                }
            }
        } catch (\Throwable) {
            // Silently fail — unauthenticated state is handled by the frontend guard.
        }

        return $next($request);
    }
}

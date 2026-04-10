<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the authenticated user from the API server and caches the result
 * per session cookie so the external API is only called once per session.
 *
 * Performance:
 *  - Skips non-GET and Inertia partial requests entirely.
 *  - Skips if no session cookie present (unauthenticated visitors).
 *  - Cache hit: ~0ms (file read). Cache miss: one HTTP call to API, then cached.
 *  - Unauthenticated state cached for 60s to prevent API hammering.
 *  - Authenticated state cached for 5 minutes.
 *
 * Security:
 *  - Cache key is SHA-256 of the raw session cookie — never stored in plaintext.
 *  - Only whitelisted fields are stored and forwarded to the frontend.
 *  - Sensitive fields (password, tokens, hashes) are never exposed.
 */
class SyncApiUser
{
    private const SAFE_FIELDS = [
        'id', 'name', 'first_name', 'last_name', 'email', 'role',
        'phone', 'profile_pic', 'email_verified_at',
        'registration_fee_status', 'payment_required', 'verification_status',
    ];

    private const TTL_AUTHENTICATED   = 300; // 5 min
    private const TTL_UNAUTHENTICATED = 60;  // 1 min
    private const API_TIMEOUT         = 3;   // seconds

    public function handle(Request $request, Closure $next): Response
    {
        // Only resolve user on full browser page loads (non-Inertia GET requests).
        // Inertia XHR navigations (X-Inertia header) already have auth.user from
        // the initial page load shared props — no need to re-resolve.
        // Partial requests (X-Inertia-Partial-Data) also skip for the same reason.
        if (
            $request->method() !== 'GET' ||
            $request->header('X-Inertia') ||
            $request->header('X-Inertia-Partial-Data')
        ) {
            return $next($request);
        }

        $sessionCookie = $this->sessionCookieValue($request);

        if (!$sessionCookie) {
            return $next($request);
        }

        $cacheKey = 'spa_user:' . hash('sha256', $sessionCookie);
        $cached   = Cache::get($cacheKey);

        if ($cached !== null) {
            if (is_array($cached) && !empty($cached)) {
                $request->attributes->set('api_user', $cached);
            }
            return $next($request);
        }

        // Cache miss — call the API once.
        $apiOrigin = rtrim(config('services.suganta.api_origin', 'https://api.suganta.com'), '/');

        try {
            $response = Http::withCookies($request->cookies->all(), parse_url($apiOrigin, PHP_URL_HOST))
                ->withHeaders([
                    'Accept'           => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->timeout(self::API_TIMEOUT)
                ->get($apiOrigin . '/api/v1/profile');

            if ($response->successful()) {
                $raw  = $response->json('data.user') ?? $response->json('data') ?? null;
                $safe = is_array($raw) && !empty($raw)
                    ? array_intersect_key($raw, array_flip(self::SAFE_FIELDS))
                    : [];

                Cache::put($cacheKey, $safe ?: [], $safe ? self::TTL_AUTHENTICATED : self::TTL_UNAUTHENTICATED);

                if (!empty($safe)) {
                    $request->attributes->set('api_user', $safe);
                }
            } else {
                Cache::put($cacheKey, [], self::TTL_UNAUTHENTICATED);
            }
        } catch (\Throwable) {
            // Network error — don't cache, retry on next request.
        }

        return $next($request);
    }

    /** Bust the user cache on logout. */
    public static function forgetUser(string $sessionCookieValue): void
    {
        Cache::forget('spa_user:' . hash('sha256', $sessionCookieValue));
    }

    private function sessionCookieValue(Request $request): ?string
    {
        $value = $request->cookie(config('session.cookie'));
        return is_string($value) && $value !== '' ? $value : null;
    }
}

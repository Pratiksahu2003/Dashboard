<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Trait ResolvesAuthState
 * 
 * Shared authentication resolution logic for EnsureGuest and EnsureAuthenticated middleware.
 * Resolves user authentication state from the /auth/user endpoint with caching.
 * 
 * Performance:
 *  - Skips non-GET requests (web auth gates are GET page loads / Inertia visits).
 *  - Skips when no session cookie and no Bearer token (nothing to resolve).
 *  - Cache hit: ~0ms (file read). Cache miss: one HTTP call to API, then cached.
 *  - Inertia GET visits (X-Inertia) are resolved like full page loads so SPA navigations stay authorized.
 *  - Unauthenticated state cached for 60s to prevent API hammering.
 *  - Authenticated state cached for 5 minutes.
 * 
 * Security:
 *  - Cache keys hash opaque material (session id and/or bearer); never store raw secrets in cache keys.
 *  - Only whitelisted fields are stored and forwarded to the frontend.
 *  - The upstream API origin is trusted; we forward the browser Cookie header only to that host.
 */
trait ResolvesAuthState
{
    /**
     * Whitelisted user fields that are safe to cache and expose.
     */
    private const SAFE_FIELDS = [
        'id',
        'name',
        'first_name',
        'last_name',
        'email',
        'role',
        'phone',
        'profile_pic',
        'email_verified_at',
        'phone_verified_at',
        'registration_fee_status',
        'payment_required',
        'verification_status',
    ];

    /**
     * Cache TTL for authenticated state (5 minutes).
     */
    private const TTL_AUTHENTICATED = 300;

    /**
     * Cache TTL for unauthenticated state (1 minute).
     */
    private const TTL_UNAUTHENTICATED = 60;

    /**
     * API call timeout (3 seconds).
     */
    private const API_TIMEOUT = 3;

    /**
     * Resolve authentication state from cache or /auth/user endpoint.
     * 
     * @param Request $request
     * @return array ['authenticated' => bool, 'user' => array|null, 'auth_mode' => string|null]
     */
    protected function resolveAuthState(Request $request): array
    {
        // Check if should skip resolution (Requirement 5.1-5.4)
        if ($this->shouldSkipResolution($request)) {
            return [
                'authenticated' => false,
                'user' => null,
                'auth_mode' => null,
            ];
        }

        // Get cache key (Requirement 4.2)
        $cacheKey = $this->getCacheKey($request);
        
        if ($cacheKey === null) {
            return [
                'authenticated' => false,
                'user' => null,
                'auth_mode' => null,
            ];
        }

        // Check cache first (Requirement 4.5)
        try {
            $cached = Cache::get($cacheKey);
            
            if (is_array($cached) && isset($cached['authenticated'])) {
                return $cached;
            }
        } catch (\Exception $e) {
            // Cache read failure - fall through to API call
            Log::debug('Auth cache read failed', [
                'key' => $cacheKey,
                'exception' => $e->getMessage(),
            ]);
        }

        // Call API on cache miss (Requirement 4.1)
        $response = $this->callAuthUserEndpoint($request);
        
        // Handle API errors (Requirement 6.1-6.3)
        if ($response === null) {
            // Don't cache errors (Requirement 6.4)
            return [
                'authenticated' => false,
                'user' => null,
                'auth_mode' => null,
            ];
        }

        // Parse response (Requirement 10.1-10.5)
        $authState = $this->parseAuthResponse($response);

        // Cache result with appropriate TTL (Requirement 4.3, 4.4)
        try {
            $ttl = $authState['authenticated'] 
                ? self::TTL_AUTHENTICATED 
                : self::TTL_UNAUTHENTICATED;
            
            Cache::put($cacheKey, $authState, $ttl);
        } catch (\Exception $e) {
            // Cache write failure - continue without caching
            Log::debug('Auth cache write failed', [
                'key' => $cacheKey,
                'exception' => $e->getMessage(),
            ]);
        }

        // Return array with authenticated, user, and auth_mode
        return $authState;
    }

    /**
     * Check if authentication resolution should be skipped for performance.
     * 
     * Skips resolution for:
     * - Non-GET requests
     * - Requests with no session cookie and no Authorization: Bearer token
     * 
     * @param Request $request
     * @return bool
     */
    protected function shouldSkipResolution(Request $request): bool
    {
        // Skip non-GET requests (Requirement 5.1)
        if (!$request->isMethod('GET')) {
            return true;
        }

        if (!$this->hasResolvableCredentials($request)) {
            return true;
        }

        return false;
    }

    /**
     * Generate cache key from session / bearer.
     * 
     * Returns SHA-256 of session and/or bearer material, prefixed with 'spa_user:'.
     * Returns null if neither session cookie nor Bearer token is present.
     * 
     * @param Request $request
     * @return string|null
     */
    protected function getCacheKey(Request $request): ?string
    {
        $sessionCookieValue = $this->sessionCookieValue($request);
        $bearer = $this->bearerTokenValue($request);

               $cookieLine = $this->cookieHeaderForApiProxy($request) ?? '';

        if ($sessionCookieValue === null && $bearer === null && $cookieLine === '') {
            return null;
        }

        // Session/bearer alone identify the cache entry; cookie-only probes use the full header hash.
        if ($sessionCookieValue !== null || $bearer !== null) {
            $material = ($sessionCookieValue ?? '') . "\0" . ($bearer ?? '');
        } else {
            $material = "\0\0" . hash('sha256', $cookieLine);
        }

        return 'spa_user:' . hash('sha256', $material);
    }

    /**
     * Call the /auth/user endpoint to check authentication status.
     * 
     * Makes HTTP GET request to /auth/user with session cookies or bearer token.
     * Returns the parsed JSON response or null on error.
     * 
     * @param Request $request
     * @return array|null
     */
    protected function callAuthUserEndpoint(Request $request): ?array
    {
        $apiOrigin = config('services.suganta.api_origin', 'https://api.suganta.com');
        $apiUrl = rtrim($apiOrigin, '/') . '/auth/user';

        try {
            $headers = [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ];

            $cookieHeader = $this->cookieHeaderForApiProxy($request);
            if ($cookieHeader !== null && $cookieHeader !== '') {
                $headers['Cookie'] = $cookieHeader;
            }

            $httpClient = Http::timeout(self::API_TIMEOUT)->withHeaders($headers);

            $bearer = $this->bearerTokenValue($request);
            if ($bearer !== null) {
                $httpClient = $httpClient->withToken($bearer);
            }

            $response = $httpClient->get($apiUrl);

            // Handle non-200 responses (Requirement 6.2)
            if (!$response->successful()) {
                Log::warning('Auth user endpoint returned non-200 status', [
                    'status' => $response->status(),
                    'url' => $apiUrl,
                ]);
                return null;
            }

            // Parse JSON response
            return $response->json();

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Handle timeout (Requirement 6.1)
            Log::warning('Auth user endpoint connection failed', [
                'exception' => $e->getMessage(),
                'url' => $apiUrl,
            ]);
            return null;
        } catch (\Exception $e) {
            // Handle other exceptions (Requirement 6.3)
            Log::error('Auth user endpoint exception', [
                'exception' => $e->getMessage(),
                'url' => $apiUrl,
            ]);
            return null;
        }
    }

    /**
     * Parse the /auth/user endpoint response.
     * 
     * Extracts authenticated status, user data, and auth mode from the response.
     * 
     * @param array $response
     * @return array ['authenticated' => bool, 'user' => array|null, 'auth_mode' => string|null]
     */
    protected function parseAuthResponse(array $response): array
    {
        $data = $response['data'] ?? null;

        if (! is_array($data)) {
            return [
                'authenticated' => false,
                'user' => null,
                'auth_mode' => null,
            ];
        }

        $explicitAuth = array_key_exists('authenticated', $data) ? $data['authenticated'] : null;
        if ($explicitAuth === false) {
            return [
                'authenticated' => false,
                'user' => null,
                'auth_mode' => null,
            ];
        }

        $user = $data['user'] ?? null;
        if (is_object($user)) {
            $user = json_decode(json_encode($user), true);
        }

        $authenticated = $explicitAuth === true
            || ($explicitAuth === null && is_array($user) && isset($user['id']));

        if (! $authenticated || ! is_array($user) || $user === []) {
            return [
                'authenticated' => false,
                'user' => null,
                'auth_mode' => null,
            ];
        }
        
        // Extract auth_mode (Requirement 10.5)
        $authMode = $data['auth_mode'] ?? null;
        
        // Filter user fields to only safe fields (Requirement 8.2)
        $filteredUser = $this->filterUserFields($user);
        
        return [
            'authenticated' => true,
            'user' => $filteredUser,
            'auth_mode' => $authMode,
        ];
    }

    /**
     * Filter user data to only include whitelisted fields.
     * 
     * Removes sensitive fields like passwords, tokens, and hashes.
     * 
     * @param array $user
     * @return array
     */
    protected function filterUserFields(array $user): array
    {
        $filtered = [];
        
        foreach (self::SAFE_FIELDS as $field) {
            if (array_key_exists($field, $user)) {
                $filtered[$field] = $user[$field];
            }
        }
        
        return $filtered;
    }

    /**
     * Invalidate cached authentication state for a session.
     * 
     * Used on logout to clear cached authentication state.
     * 
     * @param string $sessionCookieValue
     * @return void
     */
    public static function forgetUser(string $sessionCookieValue): void
    {
        // Compute cache key — session-only material matches getCacheKey() when no Bearer is used.
        $material = $sessionCookieValue . "\0";
        $cacheKey = 'spa_user:' . hash('sha256', $material);
        
        // Delete cached authentication state (Requirement 9.2)
        try {
            Cache::forget($cacheKey);
        } catch (\Exception $e) {
            // Log but don't throw - cache invalidation failure is not critical
            Log::debug('Auth cache invalidation failed', [
                'key' => $cacheKey,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the session cookie value from the request.
     * 
     * @param Request $request
     * @return string|null
     */
    private function sessionCookieValue(Request $request): ?string
    {
        $value = $request->cookie(config('session.cookie'));
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * True when we should call /auth/user (session cookie and/or API bearer).
     */
    private function hasResolvableCredentials(Request $request): bool
    {
        if ($this->bearerTokenValue($request) !== null) {
            return true;
        }

        if ($this->sessionCookieValue($request) !== null) {
            return true;
        }

        $line = $this->cookieHeaderForApiProxy($request);

        return $line !== null && $line !== '';
    }

    /**
     * Raw Cookie header for proxying to the API (Sanctum / auth cookies often differ from SESSION_COOKIE).
     * Falls back to rebuilding from the cookie bag when the header is missing (e.g. some unit tests).
     */
    private function cookieHeaderForApiProxy(Request $request): ?string
    {
        $raw = $request->headers->get('Cookie');
        if (is_string($raw) && $raw !== '') {
            return $raw;
        }

        $pairs = [];
        foreach ($request->cookies->all() as $name => $value) {
            if (! is_string($name) || $name === '') {
                continue;
            }
            if (! is_scalar($value) || (string) $value === '') {
                continue;
            }
            $pairs[] = $name . '=' . (string) $value;
        }

        return $pairs === [] ? null : implode('; ', $pairs);
    }

    /**
     * Raw bearer token from Authorization header, or null if missing/empty.
     */
    private function bearerTokenValue(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if (!is_string($header) || !str_starts_with($header, 'Bearer ')) {
            return null;
        }
        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }
}

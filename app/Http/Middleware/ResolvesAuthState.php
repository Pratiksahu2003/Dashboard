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
     * - Inertia XHR navigation requests
     * - Inertia partial data requests
     * - Requests without session cookies
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

        // Skip Inertia XHR navigation requests (Requirement 5.2)
        if ($request->hasHeader('X-Inertia')) {
            return true;
        }

        // Skip Inertia partial data requests (Requirement 5.3)
        if ($request->hasHeader('X-Inertia-Partial-Data')) {
            return true;
        }

        // Skip if no session cookie present (Requirement 5.4)
        if ($this->sessionCookieValue($request) === null) {
            return true;
        }

        return false;
    }

    /**
     * Generate cache key from session cookie.
     * 
     * Returns SHA-256 hash of the session cookie value prefixed with 'spa_user:'.
     * Returns null if no session cookie is present.
     * 
     * @param Request $request
     * @return string|null
     */
    protected function getCacheKey(Request $request): ?string
    {
        $sessionCookieValue = $this->sessionCookieValue($request);
        
        if ($sessionCookieValue === null) {
            return null;
        }
        
        return 'spa_user:' . hash('sha256', $sessionCookieValue);
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
            // Build HTTP client with timeout (Requirement 5.5)
            $httpClient = Http::timeout(self::API_TIMEOUT)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]);

            // Include session cookies (Requirement 1.3)
            $sessionCookieName = config('session.cookie');
            $sessionCookieValue = $this->sessionCookieValue($request);
            
            if ($sessionCookieValue !== null) {
                // Create a cookie jar with the session cookie
                $cookieJar = new \GuzzleHttp\Cookie\CookieJar(false, [
                    new \GuzzleHttp\Cookie\SetCookie([
                        'Name' => $sessionCookieName,
                        'Value' => $sessionCookieValue,
                        'Domain' => parse_url($apiOrigin, PHP_URL_HOST),
                        'Path' => '/',
                        'Secure' => str_starts_with($apiOrigin, 'https'),
                        'HttpOnly' => true,
                    ])
                ]);
                
                $httpClient = $httpClient->withOptions([
                    'cookies' => $cookieJar,
                ]);
            }

            // Include bearer token if present (Requirement 1.4)
            $authHeader = $request->header('Authorization');
            if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                $httpClient = $httpClient->withToken(substr($authHeader, 7));
            }

            // Make the API call
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
        // Extract data object from response (Requirement 10.1)
        $data = $response['data'] ?? null;
        
        if (!is_array($data)) {
            return [
                'authenticated' => false,
                'user' => null,
                'auth_mode' => null,
            ];
        }
        
        // Extract authenticated status (Requirement 10.2, 10.3)
        $authenticated = ($data['authenticated'] ?? false) === true;
        $user = $data['user'] ?? null;
        
        // User must be authenticated AND have user data (Requirement 10.2)
        if (!$authenticated || !is_array($user) || empty($user)) {
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
        // Compute cache key (Requirement 9.4)
        $cacheKey = 'spa_user:' . hash('sha256', $sessionCookieValue);
        
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
}

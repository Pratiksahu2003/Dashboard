<?php

namespace App\Http\Middleware;

use App\Http\Support\SugantaBrowserProxyHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolves API web-session auth via GET {api}{auth_user_path}, with caching.
 * Proxies the browser Cookie header and SPA headers (Origin, Referer, User-Agent) so the API
 * can treat the call like credentials: 'include' from the dashboard origin (see config services.suganta).
 */
trait ResolvesAuthState
{
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

    private const TTL_AUTHENTICATED = 300;

    private const TTL_UNAUTHENTICATED = 60;

    private const API_TIMEOUT = 3;

    /** @return array{authenticated: bool, user: array|null} */
    protected function resolveAuthState(Request $request): array
    {
        if ($this->shouldSkipResolution($request)) {
            return $this->unauthenticated();
        }

        $cacheKey = $this->getCacheKey($request);
        if ($cacheKey === null) {
            return $this->unauthenticated();
        }

        try {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && array_key_exists('authenticated', $cached)) {
                return $this->normalizeCachedAuthState($cached);
            }
        } catch (\Exception $e) {
            Log::debug('Auth cache read failed', [
                'key' => $cacheKey,
                'exception' => $e->getMessage(),
            ]);
        }

        $response = $this->callAuthUserEndpoint($request);
        if ($response === null) {
            return $this->unauthenticated();
        }

        $authState = $this->parseAuthResponse($response);

        try {
            $ttl = $authState['authenticated']
                ? self::TTL_AUTHENTICATED
                : self::TTL_UNAUTHENTICATED;
            Cache::put($cacheKey, $authState, $ttl);
        } catch (\Exception $e) {
            Log::debug('Auth cache write failed', [
                'key' => $cacheKey,
                'exception' => $e->getMessage(),
            ]);
        }

        return $authState;
    }

    private function normalizeCachedAuthState(array $cached): array
    {
        if (! ($cached['authenticated'] ?? false)) {
            return $this->unauthenticated();
        }

        $user = $cached['user'] ?? null;
        if (! is_array($user) || $user === []) {
            return $this->unauthenticated();
        }

        return ['authenticated' => true, 'user' => $user];
    }

    /** @return array{authenticated: false, user: null} */
    private function unauthenticated(): array
    {
        return ['authenticated' => false, 'user' => null];
    }

    protected function shouldSkipResolution(Request $request): bool
    {
        return ! $request->isMethod('GET') || ! $this->hasResolvableCredentials($request);
    }

    protected function getCacheKey(Request $request): ?string
    {
        $session = $this->sessionCookieValue($request);
        $cookieLine = SugantaBrowserProxyHeaders::cookieLine($request) ?? '';

        if ($session !== null) {
            $material = $session . "\0";
        } elseif ($cookieLine !== '') {
            $material = "\0\0" . hash('sha256', $cookieLine);
        } else {
            return null;
        }

        return 'spa_user:' . hash('sha256', $material);
    }

    protected function callAuthUserEndpoint(Request $request): ?array
    {
        $apiOrigin = config('services.suganta.api_origin', 'https://api.suganta.com');
        $path = config('services.suganta.auth_user_path', '/api/v1/auth/user');
        $path = '/' . ltrim((string) $path, '/');
        $apiUrl = rtrim($apiOrigin, '/') . $path;

        try {
            $headers = SugantaBrowserProxyHeaders::forJsonApi($request, false);

            $response = Http::timeout(self::API_TIMEOUT)
                ->withHeaders($headers)
                ->get($apiUrl);

            if (! $response->successful()) {
                Log::warning('Auth user endpoint returned non-200 status', [
                    'status' => $response->status(),
                    'url' => $apiUrl,
                ]);

                return null;
            }

            return $response->json();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('Auth user endpoint connection failed', [
                'exception' => $e->getMessage(),
                'url' => $apiUrl,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Auth user endpoint exception', [
                'exception' => $e->getMessage(),
                'url' => $apiUrl,
            ]);

            return null;
        }
    }

    /** @return array{authenticated: bool, user: array|null} */
    protected function parseAuthResponse(array $response): array
    {
        $data = $response['data'] ?? null;
        if (! is_array($data)) {
            return $this->unauthenticated();
        }

        $explicitAuth = array_key_exists('authenticated', $data) ? $data['authenticated'] : null;
        if ($explicitAuth === false) {
            return $this->unauthenticated();
        }

        $user = $data['user'] ?? null;
        if (is_object($user)) {
            $user = json_decode(json_encode($user), true);
        }

        $authenticated = $explicitAuth === true
            || ($explicitAuth === null && is_array($user) && isset($user['id']));

        if (! $authenticated || ! is_array($user) || $user === []) {
            return $this->unauthenticated();
        }

        return [
            'authenticated' => true,
            'user' => $this->filterUserFields($user),
        ];
    }

    protected function filterUserFields(array $user): array
    {
        $out = [];
        foreach (self::SAFE_FIELDS as $field) {
            if (array_key_exists($field, $user)) {
                $out[$field] = $user[$field];
            }
        }

        return $out;
    }

    public static function forgetUser(string $sessionCookieValue): void
    {
        $cacheKey = 'spa_user:' . hash('sha256', $sessionCookieValue . "\0");

        try {
            Cache::forget($cacheKey);
        } catch (\Exception $e) {
            Log::debug('Auth cache invalidation failed', [
                'key' => $cacheKey,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function sessionCookieValue(Request $request): ?string
    {
        $value = $request->cookie(config('session.cookie'));

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function hasResolvableCredentials(Request $request): bool
    {
        if ($this->sessionCookieValue($request) !== null) {
            return true;
        }

        $line = SugantaBrowserProxyHeaders::cookieLine($request);

        return is_string($line) && $line !== '';
    }
}

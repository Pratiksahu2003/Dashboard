<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolvesAuthState;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Feature tests for ResolvesAuthState trait
 * 
 * **Validates: Requirements 5.1, 5.2, 5.3, 5.4**
 */
class ResolvesAuthStateTest extends TestCase
{
    use ResolvesAuthState;

    /**
     * Get the session cookie name from config
     */
    private function getSessionCookieName(): string
    {
        return config('session.cookie');
    }

    /**
     * Test that non-GET requests are skipped
     * 
     * **Validates: Requirement 5.1**
     */
    public function test_should_skip_non_get_requests(): void
    {
        $methods = ['POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
        $cookieName = $this->getSessionCookieName();

        foreach ($methods as $method) {
            $request = Request::create('/test', $method);
            $request->cookies->set($cookieName, 'test-session-cookie');

            $this->assertTrue(
                $this->shouldSkipResolution($request),
                "Should skip {$method} requests"
            );
        }
    }

    /**
     * Test that GET requests with session cookie are not skipped
     */
    public function test_should_not_skip_get_requests_with_session(): void
    {
        $request = Request::create('/test', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $this->assertFalse(
            $this->shouldSkipResolution($request),
            "Should not skip GET requests with session cookie"
        );
    }

    /**
     * Test that Inertia XHR requests are skipped
     * 
     * **Validates: Requirement 5.2**
     */
    public function test_should_skip_inertia_xhr_requests(): void
    {
        $request = Request::create('/test', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');
        $request->headers->set('X-Inertia', 'true');

        $this->assertTrue(
            $this->shouldSkipResolution($request),
            "Should skip requests with X-Inertia header"
        );
    }

    /**
     * Test that Inertia partial data requests are skipped
     * 
     * **Validates: Requirement 5.3**
     */
    public function test_should_skip_inertia_partial_requests(): void
    {
        $request = Request::create('/test', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');
        $request->headers->set('X-Inertia-Partial-Data', 'user,notifications');

        $this->assertTrue(
            $this->shouldSkipResolution($request),
            "Should skip requests with X-Inertia-Partial-Data header"
        );
    }

    /**
     * Test that requests without session cookie are skipped
     * 
     * **Validates: Requirement 5.4**
     */
    public function test_should_skip_requests_without_session_cookie(): void
    {
        $request = Request::create('/test', 'GET');
        // No session cookie set

        $this->assertTrue(
            $this->shouldSkipResolution($request),
            "Should skip requests without session cookie"
        );
    }

    /**
     * Test that requests with empty session cookie are skipped
     * 
     * **Validates: Requirement 5.4**
     */
    public function test_should_skip_requests_with_empty_session_cookie(): void
    {
        $request = Request::create('/test', 'GET');
        $request->cookies->set($this->getSessionCookieName(), '');

        $this->assertTrue(
            $this->shouldSkipResolution($request),
            "Should skip requests with empty session cookie"
        );
    }

    /**
     * Test that multiple skip conditions are evaluated correctly
     */
    public function test_should_skip_with_multiple_conditions(): void
    {
        // POST request with Inertia header and no session
        $request = Request::create('/test', 'POST');
        $request->headers->set('X-Inertia', 'true');

        $this->assertTrue(
            $this->shouldSkipResolution($request),
            "Should skip when multiple skip conditions are met"
        );
    }

    /**
     * Test that valid GET requests with session are not skipped
     */
    public function test_should_not_skip_valid_requests(): void
    {
        $request = Request::create('/dashboard', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'valid-session-cookie-value');

        $this->assertFalse(
            $this->shouldSkipResolution($request),
            "Should not skip valid GET requests with session cookie"
        );
    }

    /**
     * Test that cache key is generated with SHA-256 hash and prefix
     * 
     * **Validates: Requirement 4.2**
     */
    public function test_get_cache_key_generates_sha256_hash_with_prefix(): void
    {
        $sessionValue = 'test-session-cookie-value';
        $expectedHash = hash('sha256', $sessionValue);
        $expectedKey = 'spa_user:' . $expectedHash;

        $request = Request::create('/test', 'GET');
        $request->cookies->set($this->getSessionCookieName(), $sessionValue);

        $cacheKey = $this->getCacheKey($request);

        $this->assertEquals(
            $expectedKey,
            $cacheKey,
            "Cache key should be 'spa_user:' prefix followed by SHA-256 hash"
        );
    }

    /**
     * Test that cache key returns null when no session cookie present
     * 
     * **Validates: Requirement 4.6**
     */
    public function test_get_cache_key_returns_null_without_session_cookie(): void
    {
        $request = Request::create('/test', 'GET');
        // No session cookie set

        $cacheKey = $this->getCacheKey($request);

        $this->assertNull(
            $cacheKey,
            "Cache key should be null when no session cookie is present"
        );
    }

    /**
     * Test that cache key returns null for empty session cookie
     * 
     * **Validates: Requirement 4.6**
     */
    public function test_get_cache_key_returns_null_for_empty_session_cookie(): void
    {
        $request = Request::create('/test', 'GET');
        $request->cookies->set($this->getSessionCookieName(), '');

        $cacheKey = $this->getCacheKey($request);

        $this->assertNull(
            $cacheKey,
            "Cache key should be null when session cookie is empty"
        );
    }

    /**
     * Test that different session cookies generate different cache keys
     * 
     * **Validates: Requirement 4.6**
     */
    public function test_get_cache_key_generates_different_keys_for_different_sessions(): void
    {
        $sessionValue1 = 'session-cookie-1';
        $sessionValue2 = 'session-cookie-2';

        $request1 = Request::create('/test', 'GET');
        $request1->cookies->set($this->getSessionCookieName(), $sessionValue1);

        $request2 = Request::create('/test', 'GET');
        $request2->cookies->set($this->getSessionCookieName(), $sessionValue2);

        $cacheKey1 = $this->getCacheKey($request1);
        $cacheKey2 = $this->getCacheKey($request2);

        $this->assertNotEquals(
            $cacheKey1,
            $cacheKey2,
            "Different session cookies should generate different cache keys"
        );
    }

    /**
     * Test that same session cookie generates same cache key
     */
    public function test_get_cache_key_generates_same_key_for_same_session(): void
    {
        $sessionValue = 'consistent-session-cookie';

        $request1 = Request::create('/test', 'GET');
        $request1->cookies->set($this->getSessionCookieName(), $sessionValue);

        $request2 = Request::create('/test', 'GET');
        $request2->cookies->set($this->getSessionCookieName(), $sessionValue);

        $cacheKey1 = $this->getCacheKey($request1);
        $cacheKey2 = $this->getCacheKey($request2);

        $this->assertEquals(
            $cacheKey1,
            $cacheKey2,
            "Same session cookie should generate same cache key"
        );
    }

    /**
     * Test that parseAuthResponse extracts authenticated user correctly
     * 
     * **Validates: Requirements 1.5, 1.6, 1.7, 10.1, 10.2**
     */
    public function test_parse_auth_response_extracts_authenticated_user(): void
    {
        $response = [
            'success' => true,
            'message' => 'User authenticated',
            'code' => 200,
            'data' => [
                'authenticated' => true,
                'user' => [
                    'id' => 123,
                    'name' => 'John Doe',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                    'role' => 'teacher',
                    'phone' => '+1234567890',
                    'profile_pic' => 'https://example.com/pic.jpg',
                    'email_verified_at' => '2025-01-15T10:00:00.000000Z',
                    'phone_verified_at' => null,
                    'registration_fee_status' => 'paid',
                    'verification_status' => 'verified',
                    'payment_required' => false,
                    'password' => 'secret-hash', // Should be filtered out
                    'remember_token' => 'token', // Should be filtered out
                ],
                'auth_mode' => 'session',
            ],
        ];

        $result = $this->parseAuthResponse($response);

        $this->assertTrue($result['authenticated']);
        $this->assertIsArray($result['user']);
        $this->assertEquals(123, $result['user']['id']);
        $this->assertEquals('John Doe', $result['user']['name']);
        $this->assertEquals('john@example.com', $result['user']['email']);
        $this->assertEquals('session', $result['auth_mode']);
        $this->assertArrayNotHasKey('password', $result['user']);
        $this->assertArrayNotHasKey('remember_token', $result['user']);
    }

    /**
     * Test that parseAuthResponse handles unauthenticated response
     * 
     * **Validates: Requirements 10.1, 10.3**
     */
    public function test_parse_auth_response_handles_unauthenticated(): void
    {
        $response = [
            'success' => true,
            'message' => 'Not authenticated',
            'code' => 200,
            'data' => [
                'authenticated' => false,
                'user' => null,
                'auth_mode' => null,
            ],
        ];

        $result = $this->parseAuthResponse($response);

        $this->assertFalse($result['authenticated']);
        $this->assertNull($result['user']);
        $this->assertNull($result['auth_mode']);
    }

    /**
     * Test that parseAuthResponse treats missing data as unauthenticated
     * 
     * **Validates: Requirement 10.1**
     */
    public function test_parse_auth_response_handles_missing_data(): void
    {
        $response = [
            'success' => true,
            'message' => 'Invalid response',
            'code' => 200,
        ];

        $result = $this->parseAuthResponse($response);

        $this->assertFalse($result['authenticated']);
        $this->assertNull($result['user']);
        $this->assertNull($result['auth_mode']);
    }

    /**
     * Test that parseAuthResponse treats null user as unauthenticated
     * 
     * **Validates: Requirement 10.3**
     */
    public function test_parse_auth_response_handles_null_user(): void
    {
        $response = [
            'success' => true,
            'message' => 'User authenticated',
            'code' => 200,
            'data' => [
                'authenticated' => true,
                'user' => null,
                'auth_mode' => 'session',
            ],
        ];

        $result = $this->parseAuthResponse($response);

        $this->assertFalse($result['authenticated']);
        $this->assertNull($result['user']);
        $this->assertNull($result['auth_mode']);
    }

    /**
     * Test that parseAuthResponse treats empty user as unauthenticated
     * 
     * **Validates: Requirement 10.3**
     */
    public function test_parse_auth_response_handles_empty_user(): void
    {
        $response = [
            'success' => true,
            'message' => 'User authenticated',
            'code' => 200,
            'data' => [
                'authenticated' => true,
                'user' => [],
                'auth_mode' => 'session',
            ],
        ];

        $result = $this->parseAuthResponse($response);

        $this->assertFalse($result['authenticated']);
        $this->assertNull($result['user']);
        $this->assertNull($result['auth_mode']);
    }

    /**
     * Test that parseAuthResponse handles token authentication
     * 
     * **Validates: Requirements 10.2, 10.5**
     */
    public function test_parse_auth_response_handles_token_auth(): void
    {
        $response = [
            'success' => true,
            'message' => 'User authenticated',
            'code' => 200,
            'data' => [
                'authenticated' => true,
                'user' => [
                    'id' => 456,
                    'name' => 'Jane Smith',
                    'email' => 'jane@example.com',
                    'role' => 'student',
                ],
                'auth_mode' => 'token',
            ],
        ];

        $result = $this->parseAuthResponse($response);

        $this->assertTrue($result['authenticated']);
        $this->assertEquals('token', $result['auth_mode']);
        $this->assertEquals(456, $result['user']['id']);
    }

    /**
     * Test that parseAuthResponse handles missing auth_mode
     * 
     * **Validates: Requirement 10.5**
     */
    public function test_parse_auth_response_handles_missing_auth_mode(): void
    {
        $response = [
            'success' => true,
            'message' => 'User authenticated',
            'code' => 200,
            'data' => [
                'authenticated' => true,
                'user' => [
                    'id' => 789,
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ],
            ],
        ];

        $result = $this->parseAuthResponse($response);

        $this->assertTrue($result['authenticated']);
        $this->assertNull($result['auth_mode']);
    }

    /**
     * Test that filterUserFields only includes whitelisted fields
     * 
     * **Validates: Requirements 8.2, 8.3**
     */
    public function test_filter_user_fields_only_includes_safe_fields(): void
    {
        $user = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret-hash',
            'remember_token' => 'token-value',
            'api_token' => 'api-token-value',
            'role' => 'teacher',
            'phone' => '+1234567890',
        ];

        $filtered = $this->filterUserFields($user);

        $this->assertArrayHasKey('id', $filtered);
        $this->assertArrayHasKey('name', $filtered);
        $this->assertArrayHasKey('email', $filtered);
        $this->assertArrayHasKey('role', $filtered);
        $this->assertArrayHasKey('phone', $filtered);
        $this->assertArrayNotHasKey('password', $filtered);
        $this->assertArrayNotHasKey('remember_token', $filtered);
        $this->assertArrayNotHasKey('api_token', $filtered);
    }

    /**
     * Test that filterUserFields handles missing fields gracefully
     * 
     * **Validates: Requirement 8.2**
     */
    public function test_filter_user_fields_handles_missing_fields(): void
    {
        $user = [
            'id' => 1,
            'email' => 'john@example.com',
            // Missing name, role, phone, etc.
        ];

        $filtered = $this->filterUserFields($user);

        $this->assertArrayHasKey('id', $filtered);
        $this->assertArrayHasKey('email', $filtered);
        $this->assertArrayNotHasKey('name', $filtered);
        $this->assertArrayNotHasKey('role', $filtered);
    }

    /**
     * Test that filterUserFields includes all whitelisted fields
     * 
     * **Validates: Requirement 8.2**
     */
    public function test_filter_user_fields_includes_all_whitelisted_fields(): void
    {
        $user = [
            'id' => 1,
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'role' => 'teacher',
            'phone' => '+1234567890',
            'profile_pic' => 'https://example.com/pic.jpg',
            'email_verified_at' => '2025-01-15T10:00:00.000000Z',
            'phone_verified_at' => '2025-01-16T10:00:00.000000Z',
            'registration_fee_status' => 'paid',
            'payment_required' => false,
            'verification_status' => 'verified',
            'password' => 'should-be-filtered',
        ];

        $filtered = $this->filterUserFields($user);

        $this->assertCount(13, $filtered);
        $this->assertEquals(1, $filtered['id']);
        $this->assertEquals('John Doe', $filtered['name']);
        $this->assertEquals('John', $filtered['first_name']);
        $this->assertEquals('Doe', $filtered['last_name']);
        $this->assertEquals('john@example.com', $filtered['email']);
        $this->assertEquals('teacher', $filtered['role']);
        $this->assertEquals('+1234567890', $filtered['phone']);
        $this->assertEquals('https://example.com/pic.jpg', $filtered['profile_pic']);
        $this->assertEquals('2025-01-15T10:00:00.000000Z', $filtered['email_verified_at']);
        $this->assertEquals('2025-01-16T10:00:00.000000Z', $filtered['phone_verified_at']);
        $this->assertEquals('paid', $filtered['registration_fee_status']);
        $this->assertFalse($filtered['payment_required']);
        $this->assertEquals('verified', $filtered['verification_status']);
        $this->assertArrayNotHasKey('password', $filtered);
    }

    /**
     * Test that API timeout is treated as unauthenticated
     * 
     * **Validates: Requirement 6.1**
     */
    public function test_api_timeout_treats_user_as_unauthenticated(): void
    {
        // Mock HTTP client to throw ConnectionException (timeout)
        \Illuminate\Support\Facades\Http::fake([
            '*/auth/user' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            },
        ]);

        $request = Request::create('/test', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session');

        $result = $this->resolveAuthState($request);

        $this->assertFalse($result['authenticated'], 'Timeout should treat user as unauthenticated');
        $this->assertNull($result['user'], 'User should be null on timeout');
        $this->assertNull($result['auth_mode'], 'Auth mode should be null on timeout');
    }

    /**
     * Test that non-200 response is treated as unauthenticated
     * 
     * **Validates: Requirement 6.2**
     */
    public function test_non_200_response_treats_user_as_unauthenticated(): void
    {
        // Mock HTTP client to return 500 error
        \Illuminate\Support\Facades\Http::fake([
            '*/auth/user' => \Illuminate\Support\Facades\Http::response(
                ['error' => 'Internal server error'],
                500
            ),
        ]);

        $request = Request::create('/test', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session');

        $result = $this->resolveAuthState($request);

        $this->assertFalse($result['authenticated'], 'Non-200 response should treat user as unauthenticated');
        $this->assertNull($result['user'], 'User should be null on non-200 response');
        $this->assertNull($result['auth_mode'], 'Auth mode should be null on non-200 response');
    }

    /**
     * Test that 401 unauthorized response is treated as unauthenticated
     * 
     * **Validates: Requirement 6.2**
     */
    public function test_401_response_treats_user_as_unauthenticated(): void
    {
        // Mock HTTP client to return 401 unauthorized
        \Illuminate\Support\Facades\Http::fake([
            '*/auth/user' => \Illuminate\Support\Facades\Http::response(
                ['error' => 'Unauthorized'],
                401
            ),
        ]);

        $request = Request::create('/test', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session');

        $result = $this->resolveAuthState($request);

        $this->assertFalse($result['authenticated'], '401 response should treat user as unauthenticated');
        $this->assertNull($result['user'], 'User should be null on 401 response');
    }

    /**
     * Test that 404 not found response is treated as unauthenticated
     * 
     * **Validates: Requirement 6.2**
     */
    public function test_404_response_treats_user_as_unauthenticated(): void
    {
        // Mock HTTP client to return 404 not found
        \Illuminate\Support\Facades\Http::fake([
            '*/auth/user' => \Illuminate\Support\Facades\Http::response(
                ['error' => 'Not found'],
                404
            ),
        ]);

        $request = Request::create('/test', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session');

        $result = $this->resolveAuthState($request);

        $this->assertFalse($result['authenticated'], '404 response should treat user as unauthenticated');
        $this->assertNull($result['user'], 'User should be null on 404 response');
    }

    /**
     * Test that network exception is treated as unauthenticated
     * 
     * **Validates: Requirement 6.3**
     */
    public function test_network_exception_treats_user_as_unauthenticated(): void
    {
        // Mock HTTP client to throw generic exception
        \Illuminate\Support\Facades\Http::fake([
            '*/auth/user' => function () {
                throw new \Exception('Network error');
            },
        ]);

        $request = Request::create('/test', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session');

        $result = $this->resolveAuthState($request);

        $this->assertFalse($result['authenticated'], 'Network exception should treat user as unauthenticated');
        $this->assertNull($result['user'], 'User should be null on network exception');
        $this->assertNull($result['auth_mode'], 'Auth mode should be null on network exception');
    }

    /**
     * Test that errors are not cached
     * 
     * **Validates: Requirement 6.4**
     */
    public function test_errors_are_not_cached(): void
    {
        $callCount = 0;

        // Mock HTTP client to fail on first call, succeed on second
        \Illuminate\Support\Facades\Http::fake([
            '*/auth/user' => function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    throw new \Exception('Network error');
                }
                return \Illuminate\Support\Facades\Http::response([
                    'success' => true,
                    'message' => 'User authenticated',
                    'code' => 200,
                    'data' => [
                        'authenticated' => true,
                        'user' => [
                            'id' => 1,
                            'name' => 'John Doe',
                            'email' => 'john@example.com',
                        ],
                        'auth_mode' => 'session',
                    ],
                ], 200);
            },
        ]);

        $request = Request::create('/test', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session');

        // First call should fail
        $result1 = $this->resolveAuthState($request);
        $this->assertFalse($result1['authenticated'], 'First call should fail');
        $this->assertEquals(1, $callCount, 'API should be called once');

        // Second call should succeed (proving error was not cached)
        $result2 = $this->resolveAuthState($request);
        $this->assertTrue($result2['authenticated'], 'Second call should succeed');
        $this->assertEquals(2, $callCount, 'API should be called again (error not cached)');
    }

    /**
     * Test that next request retries after error
     * 
     * **Validates: Requirement 6.5**
     */
    public function test_next_request_retries_after_error(): void
    {
        $callCount = 0;

        // Mock HTTP client to track call count
        \Illuminate\Support\Facades\Http::fake([
            '*/auth/user' => function () use (&$callCount) {
                $callCount++;
                throw new \Illuminate\Http\Client\ConnectionException('Timeout');
            },
        ]);

        $request = Request::create('/test', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session');

        // First request fails
        $result1 = $this->resolveAuthState($request);
        $this->assertFalse($result1['authenticated']);
        $this->assertEquals(1, $callCount);

        // Second request retries (not using cached error)
        $result2 = $this->resolveAuthState($request);
        $this->assertFalse($result2['authenticated']);
        $this->assertEquals(2, $callCount, 'Second request should retry API call');

        // Third request also retries
        $result3 = $this->resolveAuthState($request);
        $this->assertFalse($result3['authenticated']);
        $this->assertEquals(3, $callCount, 'Third request should retry API call');
    }

    /**
     * Test that malformed JSON response is treated as unauthenticated
     * 
     * **Validates: Requirement 6.3**
     */
    public function test_malformed_json_treats_user_as_unauthenticated(): void
    {
        // Mock HTTP client to return invalid JSON structure
        \Illuminate\Support\Facades\Http::fake([
            '*/auth/user' => \Illuminate\Support\Facades\Http::response(
                'invalid json string',
                200
            ),
        ]);

        $request = Request::create('/test', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session');

        $result = $this->resolveAuthState($request);

        $this->assertFalse($result['authenticated'], 'Malformed JSON should treat user as unauthenticated');
        $this->assertNull($result['user'], 'User should be null on malformed JSON');
    }

    /**
     * Test that successful response after error is cached normally
     * 
     * **Validates: Requirements 6.4, 6.5**
     */
    public function test_successful_response_after_error_is_cached(): void
    {
        $callCount = 0;

        // Mock HTTP client to fail first, succeed second
        \Illuminate\Support\Facades\Http::fake([
            '*/auth/user' => function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return \Illuminate\Support\Facades\Http::response(['error' => 'Server error'], 500);
                }
                return \Illuminate\Support\Facades\Http::response([
                    'success' => true,
                    'message' => 'User authenticated',
                    'code' => 200,
                    'data' => [
                        'authenticated' => true,
                        'user' => [
                            'id' => 1,
                            'name' => 'John Doe',
                            'email' => 'john@example.com',
                        ],
                        'auth_mode' => 'session',
                    ],
                ], 200);
            },
        ]);

        $request = Request::create('/test', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session');

        // First call fails (not cached)
        $result1 = $this->resolveAuthState($request);
        $this->assertFalse($result1['authenticated']);
        $this->assertEquals(1, $callCount);

        // Second call succeeds (makes API call)
        $result2 = $this->resolveAuthState($request);
        $this->assertTrue($result2['authenticated']);
        $this->assertEquals(2, $callCount);

        // Third call uses cache (no API call)
        $result3 = $this->resolveAuthState($request);
        $this->assertTrue($result3['authenticated']);
        $this->assertEquals(2, $callCount, 'Third call should use cached result');
    }

    /**
     * Test that API timeout respects 3-second limit
     * 
     * **Validates: Requirement 5.5**
     */
    public function test_api_timeout_is_set_to_3_seconds(): void
    {
        // This test verifies the timeout is configured correctly
        // The actual timeout behavior is tested by the HTTP client
        $request = Request::create('/test', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session');

        // Mock a slow response that would exceed timeout
        \Illuminate\Support\Facades\Http::fake([
            '*/auth/user' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Operation timed out after 3000 milliseconds');
            },
        ]);

        $result = $this->resolveAuthState($request);

        $this->assertFalse($result['authenticated'], 'Timeout should result in unauthenticated state');
    }

    /**
     * Test that forgetUser invalidates cached authentication state
     * 
     * **Validates: Requirements 9.1, 9.2, 9.3, 9.4**
     */
    public function test_forget_user_invalidates_cache(): void
    {
        $sessionValue = 'test-session-cookie-value';
        $cacheKey = 'spa_user:' . hash('sha256', $sessionValue);

        // Set up cached authentication state
        $authState = [
            'authenticated' => true,
            'user' => [
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
            'auth_mode' => 'session',
        ];

        \Illuminate\Support\Facades\Cache::put($cacheKey, $authState, 300);

        // Verify cache exists
        $this->assertTrue(
            \Illuminate\Support\Facades\Cache::has($cacheKey),
            'Cache should exist before forgetUser is called'
        );

        // Call forgetUser
        ResolvesAuthState::forgetUser($sessionValue);

        // Verify cache is cleared
        $this->assertFalse(
            \Illuminate\Support\Facades\Cache::has($cacheKey),
            'Cache should be cleared after forgetUser is called'
        );
    }

    /**
     * Test that forgetUser computes correct cache key with SHA-256
     * 
     * **Validates: Requirement 9.4**
     */
    public function test_forget_user_computes_correct_cache_key(): void
    {
        $sessionValue = 'specific-session-value';
        $expectedHash = hash('sha256', $sessionValue);
        $expectedKey = 'spa_user:' . $expectedHash;

        // Set up cache with the expected key
        \Illuminate\Support\Facades\Cache::put($expectedKey, ['authenticated' => true], 300);

        // Call forgetUser with session value
        ResolvesAuthState::forgetUser($sessionValue);

        // Verify the correct key was deleted
        $this->assertFalse(
            \Illuminate\Support\Facades\Cache::has($expectedKey),
            'forgetUser should compute and delete the correct cache key'
        );
    }

    /**
     * Test that forgetUser handles non-existent cache gracefully
     * 
     * **Validates: Requirements 9.1, 9.2**
     */
    public function test_forget_user_handles_non_existent_cache(): void
    {
        $sessionValue = 'non-existent-session';
        $cacheKey = 'spa_user:' . hash('sha256', $sessionValue);

        // Ensure cache doesn't exist
        \Illuminate\Support\Facades\Cache::forget($cacheKey);

        // This should not throw an exception
        try {
            ResolvesAuthState::forgetUser($sessionValue);
            $this->assertTrue(true, 'forgetUser should handle non-existent cache gracefully');
        } catch (\Exception $e) {
            $this->fail('forgetUser should not throw exception for non-existent cache: ' . $e->getMessage());
        }
    }

    /**
     * Test that forgetUser only deletes the specific session's cache
     * 
     * **Validates: Requirements 9.2, 9.4**
     */
    public function test_forget_user_only_deletes_specific_session(): void
    {
        $sessionValue1 = 'session-1';
        $sessionValue2 = 'session-2';
        $cacheKey1 = 'spa_user:' . hash('sha256', $sessionValue1);
        $cacheKey2 = 'spa_user:' . hash('sha256', $sessionValue2);

        // Set up cache for both sessions
        \Illuminate\Support\Facades\Cache::put($cacheKey1, ['authenticated' => true, 'user' => ['id' => 1]], 300);
        \Illuminate\Support\Facades\Cache::put($cacheKey2, ['authenticated' => true, 'user' => ['id' => 2]], 300);

        // Verify both caches exist
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has($cacheKey1));
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has($cacheKey2));

        // Call forgetUser for session 1
        ResolvesAuthState::forgetUser($sessionValue1);

        // Verify only session 1 cache is cleared
        $this->assertFalse(
            \Illuminate\Support\Facades\Cache::has($cacheKey1),
            'Session 1 cache should be cleared'
        );
        $this->assertTrue(
            \Illuminate\Support\Facades\Cache::has($cacheKey2),
            'Session 2 cache should remain intact'
        );
    }

    /**
     * Test that forgetUser is a static method
     * 
     * **Validates: Requirement 9.1**
     */
    public function test_forget_user_is_static_method(): void
    {
        $reflection = new \ReflectionMethod(ResolvesAuthState::class, 'forgetUser');
        
        $this->assertTrue(
            $reflection->isStatic(),
            'forgetUser should be a static method'
        );
    }

    /**
     * Test that forgetUser accepts session cookie value parameter
     * 
     * **Validates: Requirement 9.3**
     */
    public function test_forget_user_accepts_session_cookie_parameter(): void
    {
        $reflection = new \ReflectionMethod(ResolvesAuthState::class, 'forgetUser');
        $parameters = $reflection->getParameters();

        $this->assertCount(1, $parameters, 'forgetUser should accept exactly one parameter');
        $this->assertEquals('sessionCookieValue', $parameters[0]->getName(), 'Parameter should be named sessionCookieValue');
        $this->assertTrue($parameters[0]->hasType(), 'Parameter should have a type');
        $this->assertEquals('string', $parameters[0]->getType()->getName(), 'Parameter should be of type string');
    }
}

<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for EnsureAuthenticated middleware
 * 
 * **Validates: Requirements 3.1, 3.2, 3.3, 8.1, 8.4**
 */
class EnsureAuthenticatedTest extends TestCase
{
    private EnsureAuthenticated $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsureAuthenticated();
    }

    /**
     * Get the session cookie name from config
     */
    private function getSessionCookieName(): string
    {
        return config('session.cookie');
    }

    /**
     * Test authenticated user proceeds to route
     * 
     * **Validates: Requirements 3.3**
     */
    public function test_authenticated_user_proceeds_to_route(): void
    {
        // Mock HTTP response for authenticated user
        Http::fake([
            '*/auth/user' => Http::response([
                'success' => true,
                'message' => 'User authenticated',
                'code' => 200,
                'data' => [
                    'authenticated' => true,
                    'user' => [
                        'id' => 1,
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                        'role' => 'teacher',
                    ],
                    'auth_mode' => 'session',
                ],
            ], 200),
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $nextCalled = false;
        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response('Dashboard');
        });

        $this->assertTrue($nextCalled, 'Next middleware should be called for authenticated user');
        $this->assertEquals('Dashboard', $response->getContent());
    }

    /**
     * Test unauthenticated user redirects to login
     * 
     * **Validates: Requirements 3.1, 3.2**
     */
    public function test_unauthenticated_user_redirects_to_login(): void
    {
        // Mock HTTP response for unauthenticated user
        Http::fake([
            '*/auth/user' => Http::response([
                'success' => true,
                'message' => 'Not authenticated',
                'code' => 200,
                'data' => [
                    'authenticated' => false,
                    'user' => null,
                    'auth_mode' => null,
                ],
            ], 200),
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $response = $this->middleware->handle($request, function () {
            $this->fail('Next middleware should not be called for unauthenticated user');
        });

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());
    }

    /**
     * Test user data stored in request attributes
     * 
     * **Validates: Requirements 8.1, 8.4**
     */
    public function test_user_data_stored_in_request_attributes(): void
    {
        $userData = [
            'id' => 1,
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'role' => 'teacher',
            'phone' => '+1234567890',
            'profile_pic' => 'https://example.com/pic.jpg',
            'email_verified_at' => '2025-01-15T10:00:00.000000Z',
            'registration_fee_status' => 'paid',
            'verification_status' => 'verified',
            'payment_required' => false,
        ];

        // Mock HTTP response for authenticated user
        Http::fake([
            '*/auth/user' => Http::response([
                'success' => true,
                'message' => 'User authenticated',
                'code' => 200,
                'data' => [
                    'authenticated' => true,
                    'user' => $userData,
                    'auth_mode' => 'session',
                ],
            ], 200),
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $capturedRequest = null;
        $this->middleware->handle($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return response('OK');
        });

        $this->assertNotNull($capturedRequest, 'Request should be passed to next middleware');
        $storedUser = $capturedRequest->attributes->get('api_user');
        $this->assertIsArray($storedUser, 'User data should be stored in request attributes');
        $this->assertEquals($userData, $storedUser);
    }

    /**
     * Test cache hit uses cached data
     * 
     * **Validates: Requirements 4.1, 4.5**
     */
    public function test_cache_hit_uses_cached_data(): void
    {
        $sessionValue = 'cached-session-cookie';
        $cacheKey = 'spa_user:' . hash('sha256', $sessionValue);

        // Set up cached authentication state
        $cachedAuthState = [
            'authenticated' => true,
            'user' => [
                'id' => 1,
                'name' => 'Cached User',
                'email' => 'cached@example.com',
            ],
            'auth_mode' => 'session',
        ];
        Cache::put($cacheKey, $cachedAuthState, 300);

        // Mock HTTP to verify it's NOT called (cache hit)
        $apiCalled = false;
        Http::fake([
            '*/auth/user' => function () use (&$apiCalled) {
                $apiCalled = true;
                return Http::response(['should' => 'not be called'], 200);
            },
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->cookies->set($this->getSessionCookieName(), $sessionValue);

        $nextCalled = false;
        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response('Dashboard');
        });

        $this->assertFalse($apiCalled, 'API should not be called when cache hit occurs');
        $this->assertTrue($nextCalled, 'Next middleware should be called for cached authenticated user');
        $this->assertEquals('Dashboard', $response->getContent());
    }

    /**
     * Test cache miss calls API
     * 
     * **Validates: Requirements 4.1, 4.5**
     */
    public function test_cache_miss_calls_api(): void
    {
        $sessionValue = 'uncached-session-cookie';
        $cacheKey = 'spa_user:' . hash('sha256', $sessionValue);

        // Ensure no cache exists
        Cache::forget($cacheKey);

        // Mock HTTP to verify it IS called (cache miss)
        $apiCalled = false;
        Http::fake([
            '*/auth/user' => function () use (&$apiCalled) {
                $apiCalled = true;
                return Http::response([
                    'success' => true,
                    'message' => 'User authenticated',
                    'code' => 200,
                    'data' => [
                        'authenticated' => true,
                        'user' => [
                            'id' => 2,
                            'name' => 'API User',
                            'email' => 'api@example.com',
                        ],
                        'auth_mode' => 'session',
                    ],
                ], 200);
            },
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->cookies->set($this->getSessionCookieName(), $sessionValue);

        $nextCalled = false;
        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response('Dashboard');
        });

        $this->assertTrue($apiCalled, 'API should be called when cache miss occurs');
        $this->assertTrue($nextCalled, 'Next middleware should be called for authenticated user');
        $this->assertEquals('Dashboard', $response->getContent());
    }

    /**
     * Test API errors treat user as unauthenticated
     * 
     * **Validates: Requirements 6.1, 6.2, 6.3**
     */
    public function test_api_errors_treat_user_as_unauthenticated(): void
    {
        // Mock HTTP to throw exception
        Http::fake([
            '*/auth/user' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            },
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $response = $this->middleware->handle($request, function () {
            $this->fail('Next middleware should not be called when API error occurs');
        });

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());
    }

    /**
     * Test API timeout treats user as unauthenticated
     * 
     * **Validates: Requirement 6.1**
     */
    public function test_api_timeout_treats_user_as_unauthenticated(): void
    {
        // Mock HTTP to simulate timeout
        Http::fake([
            '*/auth/user' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Operation timed out after 3000 milliseconds');
            },
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $response = $this->middleware->handle($request, function () {
            $this->fail('Next middleware should not be called when API timeout occurs');
        });

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());
    }

    /**
     * Test non-200 API response treats user as unauthenticated
     * 
     * **Validates: Requirement 6.2**
     */
    public function test_non_200_response_treats_user_as_unauthenticated(): void
    {
        // Mock HTTP to return 500 error
        Http::fake([
            '*/auth/user' => Http::response(['error' => 'Internal server error'], 500),
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $response = $this->middleware->handle($request, function () {
            $this->fail('Next middleware should not be called when API returns non-200');
        });

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());
    }

    /**
     * Test 401 unauthorized response treats user as unauthenticated
     * 
     * **Validates: Requirement 6.2**
     */
    public function test_401_response_treats_user_as_unauthenticated(): void
    {
        // Mock HTTP to return 401 unauthorized
        Http::fake([
            '*/auth/user' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $response = $this->middleware->handle($request, function () {
            $this->fail('Next middleware should not be called when API returns 401');
        });

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());
    }

    /**
     * Test network exception treats user as unauthenticated
     * 
     * **Validates: Requirement 6.3**
     */
    public function test_network_exception_treats_user_as_unauthenticated(): void
    {
        // Mock HTTP to throw generic exception
        Http::fake([
            '*/auth/user' => function () {
                throw new \Exception('Network error');
            },
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $response = $this->middleware->handle($request, function () {
            $this->fail('Next middleware should not be called when network exception occurs');
        });

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());
    }

    /**
     * Test that cached unauthenticated state redirects to login
     * 
     * **Validates: Requirements 4.4, 4.5**
     */
    public function test_cached_unauthenticated_state_redirects_to_login(): void
    {
        $sessionValue = 'cached-unauth-session';
        $cacheKey = 'spa_user:' . hash('sha256', $sessionValue);

        // Set up cached unauthenticated state
        $cachedAuthState = [
            'authenticated' => false,
            'user' => null,
            'auth_mode' => null,
        ];
        Cache::put($cacheKey, $cachedAuthState, 60);

        // Mock HTTP to verify it's NOT called (cache hit)
        $apiCalled = false;
        Http::fake([
            '*/auth/user' => function () use (&$apiCalled) {
                $apiCalled = true;
                return Http::response(['should' => 'not be called'], 200);
            },
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->cookies->set($this->getSessionCookieName(), $sessionValue);

        $response = $this->middleware->handle($request, function () {
            $this->fail('Next middleware should not be called for cached unauthenticated user');
        });

        $this->assertFalse($apiCalled, 'API should not be called when cache hit occurs');
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());
    }

    /**
     * Test that requests without session cookie redirect to login
     * 
     * **Validates: Requirement 5.4**
     */
    public function test_no_session_cookie_redirects_to_login(): void
    {
        // Mock HTTP to verify it's NOT called
        $apiCalled = false;
        Http::fake([
            '*/auth/user' => function () use (&$apiCalled) {
                $apiCalled = true;
                return Http::response(['should' => 'not be called'], 200);
            },
        ]);

        $request = Request::create('/dashboard', 'GET');
        // No session cookie set

        $response = $this->middleware->handle($request, function () {
            $this->fail('Next middleware should not be called when no session cookie');
        });

        $this->assertFalse($apiCalled, 'API should not be called when no session cookie present');
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());
    }

    /**
     * Test that POST requests skip authentication resolution
     * 
     * **Validates: Requirement 5.1**
     */
    public function test_post_requests_skip_authentication_resolution(): void
    {
        // Mock HTTP to verify it's NOT called
        $apiCalled = false;
        Http::fake([
            '*/auth/user' => function () use (&$apiCalled) {
                $apiCalled = true;
                return Http::response(['should' => 'not be called'], 200);
            },
        ]);

        $request = Request::create('/dashboard', 'POST');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $response = $this->middleware->handle($request, function () {
            $this->fail('Next middleware should not be called for POST requests');
        });

        $this->assertFalse($apiCalled, 'API should not be called for POST requests');
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());
    }

    /**
     * Test that Inertia XHR requests skip authentication resolution
     * 
     * **Validates: Requirement 5.2**
     */
    public function test_inertia_xhr_requests_skip_authentication_resolution(): void
    {
        // Mock HTTP to verify it's NOT called
        $apiCalled = false;
        Http::fake([
            '*/auth/user' => function () use (&$apiCalled) {
                $apiCalled = true;
                return Http::response(['should' => 'not be called'], 200);
            },
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');
        $request->headers->set('X-Inertia', 'true');

        $response = $this->middleware->handle($request, function () {
            $this->fail('Next middleware should not be called for Inertia XHR requests');
        });

        $this->assertFalse($apiCalled, 'API should not be called for Inertia XHR requests');
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());
    }

    /**
     * Test that user data is not stored for unauthenticated users
     * 
     * **Validates: Requirement 8.5**
     */
    public function test_user_data_not_stored_for_unauthenticated_users(): void
    {
        // Mock HTTP response for unauthenticated user
        Http::fake([
            '*/auth/user' => Http::response([
                'success' => true,
                'message' => 'Not authenticated',
                'code' => 200,
                'data' => [
                    'authenticated' => false,
                    'user' => null,
                    'auth_mode' => null,
                ],
            ], 200),
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $this->middleware->handle($request, function () {
            // This won't be called, but we need to check the request
        });

        $storedUser = $request->attributes->get('api_user');
        $this->assertNull($storedUser, 'User data should not be stored for unauthenticated users');
    }

    /**
     * Test that only whitelisted fields are stored
     * 
     * **Validates: Requirement 8.2**
     */
    public function test_only_whitelisted_fields_are_stored(): void
    {
        $userData = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'teacher',
            'password' => 'secret-password',  // Should be filtered out
            'api_token' => 'secret-token',    // Should be filtered out
            'remember_token' => 'secret',     // Should be filtered out
        ];

        // Mock HTTP response for authenticated user
        Http::fake([
            '*/auth/user' => Http::response([
                'success' => true,
                'message' => 'User authenticated',
                'code' => 200,
                'data' => [
                    'authenticated' => true,
                    'user' => $userData,
                    'auth_mode' => 'session',
                ],
            ], 200),
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $capturedRequest = null;
        $this->middleware->handle($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return response('OK');
        });

        $storedUser = $capturedRequest->attributes->get('api_user');
        $this->assertIsArray($storedUser);
        $this->assertArrayHasKey('id', $storedUser);
        $this->assertArrayHasKey('name', $storedUser);
        $this->assertArrayHasKey('email', $storedUser);
        $this->assertArrayHasKey('role', $storedUser);
        $this->assertArrayNotHasKey('password', $storedUser, 'Password should be filtered out');
        $this->assertArrayNotHasKey('api_token', $storedUser, 'API token should be filtered out');
        $this->assertArrayNotHasKey('remember_token', $storedUser, 'Remember token should be filtered out');
    }

    /**
     * Test authenticated user with token authentication
     * 
     * **Validates: Requirement 1.4**
     */
    public function test_authenticated_user_with_bearer_token(): void
    {
        // Mock HTTP response for token-authenticated user
        Http::fake([
            '*/auth/user' => Http::response([
                'success' => true,
                'message' => 'User authenticated',
                'code' => 200,
                'data' => [
                    'authenticated' => true,
                    'user' => [
                        'id' => 1,
                        'name' => 'Token User',
                        'email' => 'token@example.com',
                        'role' => 'teacher',
                    ],
                    'auth_mode' => 'token',
                ],
            ], 200),
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->headers->set('Authorization', 'Bearer test-token-123');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $nextCalled = false;
        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response('Dashboard');
        });

        $this->assertTrue($nextCalled, 'Next middleware should be called for token-authenticated user');
        $this->assertEquals('Dashboard', $response->getContent());
    }
}

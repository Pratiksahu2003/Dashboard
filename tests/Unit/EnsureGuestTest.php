<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureGuest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for EnsureGuest middleware
 * 
 * **Validates: Requirements 2.1, 2.2, 2.3**
 */
class EnsureGuestTest extends TestCase
{
    private EnsureGuest $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsureGuest();
    }

    /**
     * Get the session cookie name from config
     */
    private function getSessionCookieName(): string
    {
        return config('session.cookie');
    }

    /**
     * Test authenticated user redirects to dashboard
     * 
     * **Validates: Requirements 2.1, 2.2**
     */
    public function test_authenticated_user_redirects_to_dashboard(): void
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

        $request = Request::create('/login', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $response = $this->middleware->handle($request, function () {
            $this->fail('Next middleware should not be called for authenticated user');
        });

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('dashboard'), $response->getTargetUrl());
    }

    /**
     * Test unauthenticated user proceeds to route
     * 
     * **Validates: Requirements 2.3**
     */
    public function test_unauthenticated_user_proceeds_to_route(): void
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

        $request = Request::create('/login', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $nextCalled = false;
        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response('OK');
        });

        $this->assertTrue($nextCalled, 'Next middleware should be called for unauthenticated user');
        $this->assertEquals('OK', $response->getContent());
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

        $request = Request::create('/login', 'GET');
        $request->cookies->set($this->getSessionCookieName(), $sessionValue);

        $response = $this->middleware->handle($request, function () {
            $this->fail('Next middleware should not be called for cached authenticated user');
        });

        $this->assertFalse($apiCalled, 'API should not be called when cache hit occurs');
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('dashboard'), $response->getTargetUrl());
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

        $request = Request::create('/login', 'GET');
        $request->cookies->set($this->getSessionCookieName(), $sessionValue);

        $response = $this->middleware->handle($request, function () {
            $this->fail('Next middleware should not be called for authenticated user');
        });

        $this->assertTrue($apiCalled, 'API should be called when cache miss occurs');
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('dashboard'), $response->getTargetUrl());
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

        $request = Request::create('/login', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $nextCalled = false;
        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response('OK');
        });

        $this->assertTrue($nextCalled, 'Next middleware should be called when API error occurs');
        $this->assertEquals('OK', $response->getContent());
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

        $request = Request::create('/register', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $nextCalled = false;
        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response('OK');
        });

        $this->assertTrue($nextCalled, 'Next middleware should be called when API timeout occurs');
        $this->assertEquals('OK', $response->getContent());
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

        $request = Request::create('/login', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $nextCalled = false;
        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response('OK');
        });

        $this->assertTrue($nextCalled, 'Next middleware should be called when API returns non-200');
        $this->assertEquals('OK', $response->getContent());
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

        $request = Request::create('/login', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $nextCalled = false;
        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response('OK');
        });

        $this->assertTrue($nextCalled, 'Next middleware should be called when API returns 401');
        $this->assertEquals('OK', $response->getContent());
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

        $request = Request::create('/login', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $nextCalled = false;
        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response('OK');
        });

        $this->assertTrue($nextCalled, 'Next middleware should be called when network exception occurs');
        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Test that unauthenticated response allows access to guest route
     * 
     * **Validates: Requirement 2.3**
     */
    public function test_unauthenticated_response_allows_guest_route_access(): void
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

        $request = Request::create('/register', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $nextCalled = false;
        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response('Register Page');
        });

        $this->assertTrue($nextCalled, 'Next middleware should be called for unauthenticated user');
        $this->assertEquals('Register Page', $response->getContent());
    }

    /**
     * Test that cached unauthenticated state allows access
     * 
     * **Validates: Requirements 4.4, 4.5**
     */
    public function test_cached_unauthenticated_state_allows_access(): void
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

        $request = Request::create('/login', 'GET');
        $request->cookies->set($this->getSessionCookieName(), $sessionValue);

        $nextCalled = false;
        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response('Login Page');
        });

        $this->assertFalse($apiCalled, 'API should not be called when cache hit occurs');
        $this->assertTrue($nextCalled, 'Next middleware should be called for cached unauthenticated user');
        $this->assertEquals('Login Page', $response->getContent());
    }

    /**
     * Test that requests without session cookie proceed without API call
     * 
     * **Validates: Requirement 5.4**
     */
    public function test_no_session_cookie_proceeds_without_api_call(): void
    {
        // Mock HTTP to verify it's NOT called
        $apiCalled = false;
        Http::fake([
            '*/auth/user' => function () use (&$apiCalled) {
                $apiCalled = true;
                return Http::response(['should' => 'not be called'], 200);
            },
        ]);

        $request = Request::create('/login', 'GET');
        // No session cookie set

        $nextCalled = false;
        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response('Login Page');
        });

        $this->assertFalse($apiCalled, 'API should not be called when no session cookie present');
        $this->assertTrue($nextCalled, 'Next middleware should be called when no session cookie');
        $this->assertEquals('Login Page', $response->getContent());
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

        $request = Request::create('/login', 'POST');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');

        $nextCalled = false;
        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response('OK');
        });

        $this->assertFalse($apiCalled, 'API should not be called for POST requests');
        $this->assertTrue($nextCalled, 'Next middleware should be called for POST requests');
        $this->assertEquals('OK', $response->getContent());
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

        $request = Request::create('/login', 'GET');
        $request->cookies->set($this->getSessionCookieName(), 'test-session-cookie');
        $request->headers->set('X-Inertia', 'true');

        $nextCalled = false;
        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response('OK');
        });

        $this->assertFalse($apiCalled, 'API should not be called for Inertia XHR requests');
        $this->assertTrue($nextCalled, 'Next middleware should be called for Inertia XHR requests');
        $this->assertEquals('OK', $response->getContent());
    }
}

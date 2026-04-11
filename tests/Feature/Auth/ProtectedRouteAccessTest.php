<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integration tests for protected route access control
 * 
 * Tests the full authentication flow for protected routes (dashboard, profile, etc.)
 * to verify that authenticated users can access these routes and
 * unauthenticated users are redirected to login.
 * 
 * **Validates: Requirements 3.1, 3.2, 3.3, 3.4**
 */
class ProtectedRouteAccessTest extends TestCase
{
    /**
     * Get the session cookie name from config
     */
    private function getSessionCookieName(): string
    {
        return config('session.cookie');
    }

    /**
     * Test authenticated session allows access to /dashboard
     * 
     * **Validates: Requirements 3.3, 3.4**
     */
    public function test_authenticated_session_allows_access_to_dashboard(): void
    {
        // Mock authenticated user response
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

        // Make request to dashboard with session cookie
        $response = $this->withCookie($this->getSessionCookieName(), 'authenticated-session-cookie')
            ->get('/dashboard');

        // Should allow access (200 OK)
        $response->assertOk();
    }

    /**
     * Test unauthenticated session redirects from /dashboard to /login
     * 
     * **Validates: Requirements 3.1, 3.2, 3.4**
     */
    public function test_unauthenticated_session_redirects_from_dashboard_to_login(): void
    {
        // Mock unauthenticated user response
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

        // Make request to dashboard with session cookie
        $response = $this->withCookie($this->getSessionCookieName(), 'unauthenticated-session-cookie')
            ->get('/dashboard');

        // Should redirect to login
        $response->assertRedirect(route('login'));
    }

    /**
     * Test token authentication allows access to /dashboard
     * 
     * Note: Bearer tokens work in conjunction with session cookies in this SPA context.
     * The middleware requires a session cookie to proceed with authentication resolution.
     * 
     * **Validates: Requirements 3.3, 3.4, 1.4**
     */
    public function test_token_authentication_allows_access_to_dashboard(): void
    {
        // Mock authenticated user response with token auth
        Http::fake([
            '*/auth/user' => Http::response([
                'success' => true,
                'message' => 'User authenticated',
                'code' => 200,
                'data' => [
                    'authenticated' => true,
                    'user' => [
                        'id' => 2,
                        'name' => 'Jane Smith',
                        'email' => 'jane@example.com',
                        'role' => 'student',
                    ],
                    'auth_mode' => 'token',
                ],
            ], 200),
        ]);

        // Make request to dashboard with bearer token AND session cookie
        // (Bearer tokens are used in addition to session cookies in SPA context)
        $response = $this->withHeader('Authorization', 'Bearer test-token-123')
            ->withCookie($this->getSessionCookieName(), 'token-auth-session')
            ->get('/dashboard');

        // Should allow access
        $response->assertOk();
    }

    /**
     * Test authenticated user can access /profile
     * 
     * **Validates: Requirements 3.3, 3.4**
     */
    public function test_authenticated_user_can_access_profile(): void
    {
        // Mock authenticated user response
        Http::fake([
            '*/auth/user' => Http::response([
                'success' => true,
                'message' => 'User authenticated',
                'code' => 200,
                'data' => [
                    'authenticated' => true,
                    'user' => [
                        'id' => 3,
                        'name' => 'Test User',
                        'email' => 'test@example.com',
                        'role' => 'teacher',
                    ],
                    'auth_mode' => 'session',
                ],
            ], 200),
        ]);

        // Make request to profile page
        $response = $this->withCookie($this->getSessionCookieName(), 'authenticated-session')
            ->get('/profile');

        // Should allow access
        $response->assertOk();
    }

    /**
     * Test unauthenticated user redirects from /profile to /login
     * 
     * **Validates: Requirements 3.1, 3.2, 3.4**
     */
    public function test_unauthenticated_user_redirects_from_profile_to_login(): void
    {
        // Mock unauthenticated user response
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

        // Make request to profile page
        $response = $this->withCookie($this->getSessionCookieName(), 'unauthenticated-session')
            ->get('/profile');

        // Should redirect to login
        $response->assertRedirect(route('login'));
    }

    /**
     * Test that no session cookie redirects to login
     * 
     * **Validates: Requirements 3.1, 5.4**
     */
    public function test_no_session_cookie_redirects_to_login(): void
    {
        // Mock should not be called due to performance optimization
        $apiCalled = false;
        Http::fake([
            '*/auth/user' => function () use (&$apiCalled) {
                $apiCalled = true;
                return Http::response(['should' => 'not be called'], 200);
            },
        ]);

        // Make request without session cookie
        $response = $this->get('/dashboard');

        // Should redirect to login without calling API
        $response->assertRedirect(route('login'));
        $this->assertFalse($apiCalled, 'API should not be called when no session cookie present');
    }

    /**
     * Test that API errors redirect to login
     * 
     * **Validates: Requirements 3.1, 6.1, 6.2, 6.3**
     */
    public function test_api_errors_redirect_to_login(): void
    {
        // Mock API error
        Http::fake([
            '*/auth/user' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            },
        ]);

        // Make request to dashboard
        $response = $this->withCookie($this->getSessionCookieName(), 'test-session')
            ->get('/dashboard');

        // Should redirect to login (treat as unauthenticated)
        $response->assertRedirect(route('login'));
    }

    /**
     * Test that cached authenticated state allows access to protected routes
     * 
     * **Validates: Requirements 3.3, 4.5**
     */
    public function test_cached_authenticated_state_allows_access_to_protected_routes(): void
    {
        $sessionValue = 'cached-auth-session';
        $cacheKey = 'spa_user:' . hash('sha256', $sessionValue);

        // Set up cached authenticated state
        \Illuminate\Support\Facades\Cache::put($cacheKey, [
            'authenticated' => true,
            'user' => [
                'id' => 6,
                'name' => 'Cached User',
                'email' => 'cached@example.com',
            ],
            'auth_mode' => 'session',
        ], 300);

        // Mock should not be called (cache hit)
        $apiCalled = false;
        Http::fake([
            '*/auth/user' => function () use (&$apiCalled) {
                $apiCalled = true;
                return Http::response(['should' => 'not be called'], 200);
            },
        ]);

        // Make request to dashboard
        $response = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/dashboard');

        // Should allow access using cached data
        $response->assertOk();
        $this->assertFalse($apiCalled, 'API should not be called when cache hit occurs');
    }

    /**
     * Test that cached unauthenticated state redirects to login
     * 
     * **Validates: Requirements 3.1, 4.5**
     */
    public function test_cached_unauthenticated_state_redirects_to_login(): void
    {
        $sessionValue = 'cached-unauth-session';
        $cacheKey = 'spa_user:' . hash('sha256', $sessionValue);

        // Set up cached unauthenticated state
        \Illuminate\Support\Facades\Cache::put($cacheKey, [
            'authenticated' => false,
            'user' => null,
            'auth_mode' => null,
        ], 60);

        // Mock should not be called (cache hit)
        $apiCalled = false;
        Http::fake([
            '*/auth/user' => function () use (&$apiCalled) {
                $apiCalled = true;
                return Http::response(['should' => 'not be called'], 200);
            },
        ]);

        // Make request to dashboard
        $response = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/dashboard');

        // Should redirect to login using cached data
        $response->assertRedirect(route('login'));
        $this->assertFalse($apiCalled, 'API should not be called when cache hit occurs');
    }

    /**
     * Test that user data is stored in request attributes
     * 
     * **Validates: Requirements 8.1, 8.4**
     */
    public function test_user_data_stored_in_request_attributes(): void
    {
        // Mock authenticated user response
        Http::fake([
            '*/auth/user' => Http::response([
                'success' => true,
                'message' => 'User authenticated',
                'code' => 200,
                'data' => [
                    'authenticated' => true,
                    'user' => [
                        'id' => 7,
                        'name' => 'Attribute Test User',
                        'email' => 'attribute@example.com',
                        'role' => 'teacher',
                    ],
                    'auth_mode' => 'session',
                ],
            ], 200),
        ]);

        // Make request to dashboard
        $this->withCookie($this->getSessionCookieName(), 'test-session')
            ->get('/dashboard');

        // Verify user data is accessible in request attributes
        $apiUser = request()->attributes->get('api_user');
        $this->assertIsArray($apiUser);
        $this->assertEquals(7, $apiUser['id']);
        $this->assertEquals('Attribute Test User', $apiUser['name']);
        $this->assertEquals('attribute@example.com', $apiUser['email']);
    }

    /**
     * Test authenticated user can access multiple protected routes
     * 
     * **Validates: Requirements 3.3, 3.4**
     */
    public function test_authenticated_user_can_access_multiple_protected_routes(): void
    {
        // Mock authenticated user response
        Http::fake([
            '*/auth/user' => Http::response([
                'success' => true,
                'message' => 'User authenticated',
                'code' => 200,
                'data' => [
                    'authenticated' => true,
                    'user' => [
                        'id' => 8,
                        'name' => 'Multi Route User',
                        'email' => 'multi@example.com',
                        'role' => 'teacher',
                    ],
                    'auth_mode' => 'session',
                ],
            ], 200),
        ]);

        $sessionCookie = $this->getSessionCookieName();

        // Test multiple protected routes
        $protectedRoutes = [
            '/dashboard',
            '/profile',
            '/notifications',
            '/payments',
            '/marketplace',
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->withCookie($sessionCookie, 'authenticated-session')
                ->get($route);
            
            $response->assertOk();
        }
    }

    /**
     * Test unauthenticated user redirects from multiple protected routes
     * 
     * **Validates: Requirements 3.1, 3.2, 3.4**
     */
    public function test_unauthenticated_user_redirects_from_multiple_protected_routes(): void
    {
        // Mock unauthenticated user response
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

        $sessionCookie = $this->getSessionCookieName();

        // Test multiple protected routes
        $protectedRoutes = [
            '/dashboard',
            '/profile',
            '/notifications',
            '/payments',
            '/marketplace',
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->withCookie($sessionCookie, 'unauthenticated-session')
                ->get($route);
            
            $response->assertRedirect(route('login'));
        }
    }
}

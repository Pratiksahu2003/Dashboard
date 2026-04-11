<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integration tests for guest route protection
 * 
 * Tests the full authentication flow for guest routes (login, register, etc.)
 * to verify that authenticated users are redirected to dashboard and
 * unauthenticated users can access these routes.
 * 
 * **Validates: Requirements 2.1, 2.2, 2.3, 2.4**
 */
class GuestRouteProtectionTest extends TestCase
{
    /**
     * Get the session cookie name from config
     */
    private function getSessionCookieName(): string
    {
        return config('session.cookie');
    }

    /**
     * Test authenticated session redirects from /login to /dashboard
     * 
     * **Validates: Requirements 2.1, 2.2**
     */
    public function test_authenticated_session_redirects_from_login_to_dashboard(): void
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

        // Make request to login page with session cookie
        $response = $this->withCookie($this->getSessionCookieName(), 'authenticated-session-cookie')
            ->get('/login');

        // Should redirect to dashboard
        $response->assertRedirect(route('dashboard'));
    }

    /**
     * Test unauthenticated session allows access to /login
     * 
     * **Validates: Requirements 2.3, 2.4**
     */
    public function test_unauthenticated_session_allows_access_to_login(): void
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

        // Make request to login page with session cookie
        $response = $this->withCookie($this->getSessionCookieName(), 'unauthenticated-session-cookie')
            ->get('/login');

        // Should allow access (200 OK)
        $response->assertOk();
    }

    /**
     * Test token authentication redirects from /login to /dashboard
     * 
     * Note: Bearer tokens work in conjunction with session cookies in this SPA context.
     * The middleware requires a session cookie to proceed with authentication resolution.
     * 
     * **Validates: Requirements 2.1, 2.2, 1.4**
     */
    public function test_token_authentication_redirects_from_login_to_dashboard(): void
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

        // Make request to login page with bearer token AND session cookie
        // (Bearer tokens are used in addition to session cookies in SPA context)
        $response = $this->withHeader('Authorization', 'Bearer test-token-123')
            ->withCookie($this->getSessionCookieName(), 'token-auth-session')
            ->get('/login');

        // Should redirect to dashboard
        $response->assertRedirect(route('dashboard'));
    }

    /**
     * Test authenticated user redirects from /register to /dashboard
     * 
     * **Validates: Requirements 2.1, 2.4**
     */
    public function test_authenticated_user_redirects_from_register_to_dashboard(): void
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

        // Make request to register page with session cookie
        $response = $this->withCookie($this->getSessionCookieName(), 'authenticated-session')
            ->get('/register');

        // Should redirect to dashboard
        $response->assertRedirect(route('dashboard'));
    }

    /**
     * Test unauthenticated user can access /register
     * 
     * **Validates: Requirements 2.3, 2.4**
     */
    public function test_unauthenticated_user_can_access_register(): void
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

        // Make request to register page
        $response = $this->withCookie($this->getSessionCookieName(), 'unauthenticated-session')
            ->get('/register');

        // Should allow access
        $response->assertOk();
    }

    /**
     * Test authenticated user redirects from /forgot-password to /dashboard
     * 
     * **Validates: Requirements 2.1, 2.4**
     */
    public function test_authenticated_user_redirects_from_forgot_password_to_dashboard(): void
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
                        'id' => 4,
                        'name' => 'Another User',
                        'email' => 'another@example.com',
                        'role' => 'student',
                    ],
                    'auth_mode' => 'session',
                ],
            ], 200),
        ]);

        // Make request to forgot-password page
        $response = $this->withCookie($this->getSessionCookieName(), 'authenticated-session')
            ->get('/forgot-password');

        // Should redirect to dashboard
        $response->assertRedirect(route('dashboard'));
    }

    /**
     * Test unauthenticated user can access /forgot-password
     * 
     * **Validates: Requirements 2.3, 2.4**
     */
    public function test_unauthenticated_user_can_access_forgot_password(): void
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

        // Make request to forgot-password page
        $response = $this->withCookie($this->getSessionCookieName(), 'unauthenticated-session')
            ->get('/forgot-password');

        // Should allow access
        $response->assertOk();
    }

    /**
     * Test authenticated user redirects from /reset-password to /dashboard
     * 
     * **Validates: Requirements 2.1, 2.4**
     */
    public function test_authenticated_user_redirects_from_reset_password_to_dashboard(): void
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
                        'id' => 5,
                        'name' => 'Reset User',
                        'email' => 'reset@example.com',
                        'role' => 'teacher',
                    ],
                    'auth_mode' => 'session',
                ],
            ], 200),
        ]);

        // Make request to reset-password page with token
        $response = $this->withCookie($this->getSessionCookieName(), 'authenticated-session')
            ->get('/reset-password/test-token-123?email=reset@example.com');

        // Should redirect to dashboard
        $response->assertRedirect(route('dashboard'));
    }

    /**
     * Test unauthenticated user can access /reset-password
     * 
     * **Validates: Requirements 2.3, 2.4**
     */
    public function test_unauthenticated_user_can_access_reset_password(): void
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

        // Make request to reset-password page with token
        $response = $this->withCookie($this->getSessionCookieName(), 'unauthenticated-session')
            ->get('/reset-password/test-token-123?email=test@example.com');

        // Should allow access
        $response->assertOk();
    }

    /**
     * Test that no session cookie allows access to guest routes
     * 
     * **Validates: Requirements 2.3, 5.4**
     */
    public function test_no_session_cookie_allows_access_to_guest_routes(): void
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
        $response = $this->get('/login');

        // Should allow access without calling API
        $response->assertOk();
        $this->assertFalse($apiCalled, 'API should not be called when no session cookie present');
    }

    /**
     * Test that API errors allow access to guest routes
     * 
     * **Validates: Requirements 2.3, 6.1, 6.2, 6.3**
     */
    public function test_api_errors_allow_access_to_guest_routes(): void
    {
        // Mock API error
        Http::fake([
            '*/auth/user' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            },
        ]);

        // Make request to login page
        $response = $this->withCookie($this->getSessionCookieName(), 'test-session')
            ->get('/login');

        // Should allow access (treat as unauthenticated)
        $response->assertOk();
    }

    /**
     * Test that cached authenticated state redirects from guest routes
     * 
     * **Validates: Requirements 2.1, 4.5**
     */
    public function test_cached_authenticated_state_redirects_from_guest_routes(): void
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

        // Make request to login page
        $response = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/login');

        // Should redirect to dashboard using cached data
        $response->assertRedirect(route('dashboard'));
        $this->assertFalse($apiCalled, 'API should not be called when cache hit occurs');
    }

    /**
     * Test that cached unauthenticated state allows access to guest routes
     * 
     * **Validates: Requirements 2.3, 4.5**
     */
    public function test_cached_unauthenticated_state_allows_access_to_guest_routes(): void
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

        // Make request to login page
        $response = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/login');

        // Should allow access using cached data
        $response->assertOk();
        $this->assertFalse($apiCalled, 'API should not be called when cache hit occurs');
    }
}

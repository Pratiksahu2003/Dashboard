<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\ResolvesAuthState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integration tests for logout flow and cache invalidation
 * 
 * Tests that the logout flow properly invalidates the authentication cache
 * using the ResolvesAuthState::forgetUser() method, and that subsequent
 * requests after logout call the API instead of using stale cached data.
 * 
 * **Validates: Requirements 9.1, 9.2, 9.3, 9.4**
 */
class LogoutFlowTest extends TestCase
{
    /**
     * Get the session cookie name from config
     */
    private function getSessionCookieName(): string
    {
        return config('session.cookie');
    }

    /**
     * Test logout invalidates cache
     * 
     * **Validates: Requirements 9.1, 9.2, 9.4**
     */
    public function test_logout_invalidates_cache(): void
    {
        $sessionValue = 'logout-test-session';
        $cacheKey = 'spa_user:' . hash('sha256', $sessionValue);

        // Pre-populate cache with authenticated state
        Cache::put($cacheKey, [
            'authenticated' => true,
            'user' => [
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'role' => 'teacher',
            ],
            'auth_mode' => 'session',
        ], 300);

        // Verify cache exists before logout
        $this->assertTrue(
            Cache::has($cacheKey),
            'Cache should exist before logout'
        );

        // Call forgetUser to simulate logout
        ResolvesAuthState::forgetUser($sessionValue);

        // Verify cache was invalidated
        $this->assertFalse(
            Cache::has($cacheKey),
            'Cache should be invalidated after logout'
        );
    }

    /**
     * Test next request after logout calls API
     * 
     * **Validates: Requirements 9.1, 9.2**
     */
    public function test_next_request_after_logout_calls_api(): void
    {
        $sessionValue = 'logout-api-call-session';
        $cacheKey = 'spa_user:' . hash('sha256', $sessionValue);

        // Pre-populate cache with authenticated state
        Cache::put($cacheKey, [
            'authenticated' => true,
            'user' => [
                'id' => 2,
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'role' => 'student',
            ],
            'auth_mode' => 'session',
        ], 300);

        $apiCallCount = 0;

        // Mock unauthenticated response (user logged out)
        Http::fake([
            '*/auth/user' => function () use (&$apiCallCount) {
                $apiCallCount++;
                return Http::response([
                    'success' => true,
                    'message' => 'Not authenticated',
                    'code' => 200,
                    'data' => [
                        'authenticated' => false,
                        'user' => null,
                        'auth_mode' => null,
                    ],
                ], 200);
            },
        ]);

        // Simulate logout by invalidating cache
        ResolvesAuthState::forgetUser($sessionValue);

        // Make request after logout
        $response = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/dashboard');

        // Should redirect to login (unauthenticated)
        $response->assertRedirect('/login');

        // Verify API was called (cache miss after logout)
        $this->assertEquals(
            1,
            $apiCallCount,
            'API should be called after logout (cache invalidated)'
        );
    }

    /**
     * Test cached authenticated state not reused after logout
     * 
     * **Validates: Requirements 9.1, 9.2, 9.3, 9.4**
     */
    public function test_cached_authenticated_state_not_reused_after_logout(): void
    {
        $sessionValue = 'logout-no-reuse-session';
        $cacheKey = 'spa_user:' . hash('sha256', $sessionValue);

        $apiCallCount = 0;

        // Mock authenticated response for first request
        Http::fake([
            '*/auth/user' => function () use (&$apiCallCount) {
                $apiCallCount++;
                
                // First call: authenticated
                if ($apiCallCount === 1) {
                    return Http::response([
                        'success' => true,
                        'message' => 'User authenticated',
                        'code' => 200,
                        'data' => [
                            'authenticated' => true,
                            'user' => [
                                'id' => 3,
                                'name' => 'Alice Johnson',
                                'email' => 'alice@example.com',
                                'role' => 'teacher',
                            ],
                            'auth_mode' => 'session',
                        ],
                    ], 200);
                }
                
                // Second call (after logout): unauthenticated
                return Http::response([
                    'success' => true,
                    'message' => 'Not authenticated',
                    'code' => 200,
                    'data' => [
                        'authenticated' => false,
                        'user' => null,
                        'auth_mode' => null,
                    ],
                ], 200);
            },
        ]);

        // First request: populate cache with authenticated state
        $response1 = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/dashboard');

        $response1->assertOk();
        $this->assertEquals(1, $apiCallCount, 'First request should call API');

        // Verify cache contains authenticated state
        $this->assertTrue(Cache::has($cacheKey), 'Cache should exist after first request');
        $cached = Cache::get($cacheKey);
        $this->assertTrue($cached['authenticated'], 'Cache should contain authenticated state');
        $this->assertEquals(3, $cached['user']['id']);

        // Simulate logout
        ResolvesAuthState::forgetUser($sessionValue);

        // Verify cache was cleared
        $this->assertFalse(
            Cache::has($cacheKey),
            'Cache should be cleared after logout'
        );

        // Second request: should call API and get unauthenticated state
        $response2 = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/dashboard');

        $response2->assertRedirect('/login');
        $this->assertEquals(
            2,
            $apiCallCount,
            'Second request should call API (not use old cached state)'
        );

        // Verify new cache contains unauthenticated state
        $this->assertTrue(
            Cache::has($cacheKey),
            'Cache should be repopulated with unauthenticated state'
        );
        $newCached = Cache::get($cacheKey);
        $this->assertFalse(
            $newCached['authenticated'],
            'New cache should contain unauthenticated state'
        );
        $this->assertNull($newCached['user'], 'User should be null after logout');
    }

    /**
     * Test forgetUser computes correct cache key
     * 
     * **Validates: Requirements 9.3, 9.4**
     */
    public function test_forget_user_computes_correct_cache_key(): void
    {
        $sessionValue = 'cache-key-test-session';
        $expectedHash = hash('sha256', $sessionValue);
        $expectedCacheKey = 'spa_user:' . $expectedHash;

        // Pre-populate cache with authenticated state
        Cache::put($expectedCacheKey, [
            'authenticated' => true,
            'user' => [
                'id' => 4,
                'name' => 'Bob Wilson',
                'email' => 'bob@example.com',
                'role' => 'student',
            ],
            'auth_mode' => 'session',
        ], 300);

        // Verify cache exists
        $this->assertTrue(
            Cache::has($expectedCacheKey),
            'Cache should exist before forgetUser'
        );

        // Call forgetUser with session value
        ResolvesAuthState::forgetUser($sessionValue);

        // Verify cache was deleted using correct key format
        $this->assertFalse(
            Cache::has($expectedCacheKey),
            'Cache should be deleted using SHA-256 hash key'
        );

        // Verify plaintext session value is not used as key
        $this->assertFalse(
            Cache::has('spa_user:' . $sessionValue),
            'Plaintext session value should not be used as cache key'
        );
    }

    /**
     * Test logout with multiple sessions only invalidates specific session
     * 
     * **Validates: Requirements 9.2, 9.4**
     */
    public function test_logout_only_invalidates_specific_session(): void
    {
        $sessionValue1 = 'session-1';
        $sessionValue2 = 'session-2';
        $cacheKey1 = 'spa_user:' . hash('sha256', $sessionValue1);
        $cacheKey2 = 'spa_user:' . hash('sha256', $sessionValue2);

        // Pre-populate cache for both sessions
        Cache::put($cacheKey1, [
            'authenticated' => true,
            'user' => [
                'id' => 10,
                'name' => 'User One',
                'email' => 'user1@example.com',
            ],
            'auth_mode' => 'session',
        ], 300);

        Cache::put($cacheKey2, [
            'authenticated' => true,
            'user' => [
                'id' => 20,
                'name' => 'User Two',
                'email' => 'user2@example.com',
            ],
            'auth_mode' => 'session',
        ], 300);

        // Verify both caches exist
        $this->assertTrue(Cache::has($cacheKey1), 'Session 1 cache should exist');
        $this->assertTrue(Cache::has($cacheKey2), 'Session 2 cache should exist');

        // Logout session 1 only
        ResolvesAuthState::forgetUser($sessionValue1);

        // Verify only session 1 cache was invalidated
        $this->assertFalse(
            Cache::has($cacheKey1),
            'Session 1 cache should be invalidated'
        );
        $this->assertTrue(
            Cache::has($cacheKey2),
            'Session 2 cache should still exist (not affected by session 1 logout)'
        );

        // Verify session 2 data is intact
        $cached2 = Cache::get($cacheKey2);
        $this->assertEquals(20, $cached2['user']['id'], 'Session 2 data should be unchanged');
    }

    /**
     * Test logout handles non-existent cache gracefully
     * 
     * **Validates: Requirement 9.2**
     */
    public function test_logout_handles_non_existent_cache_gracefully(): void
    {
        $sessionValue = 'non-existent-session';
        $cacheKey = 'spa_user:' . hash('sha256', $sessionValue);

        // Ensure cache doesn't exist
        Cache::forget($cacheKey);
        $this->assertFalse(Cache::has($cacheKey), 'Cache should not exist');

        // Call forgetUser on non-existent cache (should not throw exception)
        try {
            ResolvesAuthState::forgetUser($sessionValue);
            $this->assertTrue(true, 'forgetUser should handle non-existent cache gracefully');
        } catch (\Exception $e) {
            $this->fail('forgetUser should not throw exception for non-existent cache: ' . $e->getMessage());
        }

        // Verify cache still doesn't exist
        $this->assertFalse(
            Cache::has($cacheKey),
            'Cache should still not exist after forgetUser'
        );
    }

    /**
     * Test complete logout flow: authenticated -> logout -> unauthenticated
     * 
     * **Validates: Requirements 9.1, 9.2, 9.3, 9.4**
     */
    public function test_complete_logout_flow(): void
    {
        $sessionValue = 'complete-flow-session';
        $cacheKey = 'spa_user:' . hash('sha256', $sessionValue);

        $apiCallCount = 0;

        // Mock API responses
        Http::fake([
            '*/auth/user' => function () use (&$apiCallCount) {
                $apiCallCount++;
                
                // Before logout: authenticated
                if ($apiCallCount === 1) {
                    return Http::response([
                        'success' => true,
                        'message' => 'User authenticated',
                        'code' => 200,
                        'data' => [
                            'authenticated' => true,
                            'user' => [
                                'id' => 5,
                                'name' => 'Complete Flow User',
                                'email' => 'complete@example.com',
                                'role' => 'teacher',
                            ],
                            'auth_mode' => 'session',
                        ],
                    ], 200);
                }
                
                // After logout: unauthenticated
                return Http::response([
                    'success' => true,
                    'message' => 'Not authenticated',
                    'code' => 200,
                    'data' => [
                        'authenticated' => false,
                        'user' => null,
                        'auth_mode' => null,
                    ],
                ], 200);
            },
        ]);

        // Step 1: User is authenticated, access protected route
        $response1 = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/dashboard');

        $response1->assertOk();
        $this->assertEquals(1, $apiCallCount, 'Initial request should call API');
        $this->assertTrue(Cache::has($cacheKey), 'Cache should be populated');

        // Step 2: Second request uses cache (no API call)
        $response2 = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/dashboard');

        $response2->assertOk();
        $this->assertEquals(1, $apiCallCount, 'Second request should use cache');

        // Step 3: User logs out (cache invalidated)
        ResolvesAuthState::forgetUser($sessionValue);
        $this->assertFalse(Cache::has($cacheKey), 'Cache should be cleared after logout');

        // Step 4: Next request calls API and gets unauthenticated state
        $response3 = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/dashboard');

        $response3->assertRedirect('/login');
        $this->assertEquals(2, $apiCallCount, 'Request after logout should call API');

        // Step 5: Verify new cache contains unauthenticated state
        $this->assertTrue(Cache::has($cacheKey), 'Cache should be repopulated');
        $cached = Cache::get($cacheKey);
        $this->assertFalse($cached['authenticated'], 'New cache should be unauthenticated');
        $this->assertNull($cached['user'], 'User should be null');

        // Step 6: Subsequent request uses cached unauthenticated state
        $response4 = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/dashboard');

        $response4->assertRedirect('/login');
        $this->assertEquals(2, $apiCallCount, 'Subsequent request should use cached unauthenticated state');
    }
}

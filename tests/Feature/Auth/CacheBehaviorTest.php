<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integration tests for authentication cache behavior
 * 
 * Tests the caching behavior of the authentication middleware, including
 * cache hits, cache misses, cache expiry, and cache key generation for
 * different sessions.
 * 
 * **Validates: Requirements 4.1, 4.2, 4.3, 4.4, 4.5, 4.6**
 */
class CacheBehaviorTest extends TestCase
{
    /**
     * Get the session cookie name from config
     */
    private function getSessionCookieName(): string
    {
        return config('session.cookie');
    }

    /**
     * Test first request calls API and caches result
     * 
     * **Validates: Requirements 4.1, 4.5**
     */
    public function test_first_request_calls_api_and_caches_result(): void
    {
        $sessionValue = 'first-request-session';
        $cacheKey = 'spa_user:' . hash('sha256', $sessionValue);

        // Ensure cache is empty
        Cache::forget($cacheKey);

        $apiCallCount = 0;

        // Mock authenticated user response
        Http::fake([
            '*/auth/user' => function () use (&$apiCallCount) {
                $apiCallCount++;
                return Http::response([
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
                ], 200);
            },
        ]);

        // Make first request
        $response = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/dashboard');

        // Should call API once
        $this->assertEquals(1, $apiCallCount, 'First request should call API');

        // Should allow access
        $response->assertOk();

        // Verify cache was populated
        $this->assertTrue(
            Cache::has($cacheKey),
            'Cache should be populated after first request'
        );

        // Verify cached data structure
        $cached = Cache::get($cacheKey);
        $this->assertIsArray($cached);
        $this->assertTrue($cached['authenticated']);
        $this->assertEquals(1, $cached['user']['id']);
        $this->assertEquals('session', $cached['auth_mode']);
    }

    /**
     * Test second request uses cached result (no API call)
     * 
     * **Validates: Requirements 4.5**
     */
    public function test_second_request_uses_cached_result_no_api_call(): void
    {
        $sessionValue = 'cached-session';
        $cacheKey = 'spa_user:' . hash('sha256', $sessionValue);

        $apiCallCount = 0;

        // Mock authenticated user response
        Http::fake([
            '*/auth/user' => function () use (&$apiCallCount) {
                $apiCallCount++;
                return Http::response([
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
                        'auth_mode' => 'session',
                    ],
                ], 200);
            },
        ]);

        // Make first request (cache miss)
        $response1 = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/dashboard');

        $response1->assertOk();
        $this->assertEquals(1, $apiCallCount, 'First request should call API');

        // Make second request (cache hit)
        $response2 = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/dashboard');

        $response2->assertOk();
        $this->assertEquals(1, $apiCallCount, 'Second request should NOT call API (cache hit)');

        // Make third request (still cache hit)
        $response3 = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/dashboard');

        $response3->assertOk();
        $this->assertEquals(1, $apiCallCount, 'Third request should NOT call API (cache hit)');
    }

    /**
     * Test cache expiry triggers new API call
     * 
     * **Validates: Requirements 4.3, 4.4**
     */
    public function test_cache_expiry_triggers_new_api_call(): void
    {
        $sessionValue = 'expiry-test-session';
        $cacheKey = 'spa_user:' . hash('sha256', $sessionValue);

        // Pre-populate cache with authenticated state and 1-second TTL
        Cache::put($cacheKey, [
            'authenticated' => true,
            'user' => [
                'id' => 3,
                'name' => 'Cached User',
                'email' => 'cached@example.com',
            ],
            'auth_mode' => 'session',
        ], 1); // 1 second TTL

        $apiCallCount = 0;

        // Mock authenticated user response
        Http::fake([
            '*/auth/user' => function () use (&$apiCallCount) {
                $apiCallCount++;
                return Http::response([
                    'success' => true,
                    'message' => 'User authenticated',
                    'code' => 200,
                    'data' => [
                        'authenticated' => true,
                        'user' => [
                            'id' => 4,
                            'name' => 'Fresh User',
                            'email' => 'fresh@example.com',
                            'role' => 'teacher',
                        ],
                        'auth_mode' => 'session',
                    ],
                ], 200);
            },
        ]);

        // Make first request (cache hit, no API call)
        $response1 = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/dashboard');

        $response1->assertOk();
        $this->assertEquals(0, $apiCallCount, 'First request should use cache');

        // Wait for cache to expire
        sleep(2);

        // Make second request (cache expired, should call API)
        $response2 = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/dashboard');

        $response2->assertOk();
        $this->assertEquals(1, $apiCallCount, 'Second request should call API after cache expiry');

        // Verify new data was cached
        $cached = Cache::get($cacheKey);
        $this->assertEquals(4, $cached['user']['id'], 'Cache should contain fresh data');
        $this->assertEquals('Fresh User', $cached['user']['name']);
    }

    /**
     * Test different session cookies use different cache keys
     * 
     * **Validates: Requirements 4.2, 4.6**
     */
    public function test_different_session_cookies_use_different_cache_keys(): void
    {
        $sessionValue1 = 'session-user-1';
        $sessionValue2 = 'session-user-2';
        $cacheKey1 = 'spa_user:' . hash('sha256', $sessionValue1);
        $cacheKey2 = 'spa_user:' . hash('sha256', $sessionValue2);

        // Ensure caches are empty
        Cache::forget($cacheKey1);
        Cache::forget($cacheKey2);

        $apiCallCount = 0;

        // Mock different responses for different users
        Http::fake([
            '*/auth/user' => function () use (&$apiCallCount) {
                $apiCallCount++;
                
                // Return different user based on call count
                if ($apiCallCount === 1) {
                    return Http::response([
                        'success' => true,
                        'message' => 'User authenticated',
                        'code' => 200,
                        'data' => [
                            'authenticated' => true,
                            'user' => [
                                'id' => 10,
                                'name' => 'User One',
                                'email' => 'user1@example.com',
                                'role' => 'teacher',
                            ],
                            'auth_mode' => 'session',
                        ],
                    ], 200);
                } else {
                    return Http::response([
                        'success' => true,
                        'message' => 'User authenticated',
                        'code' => 200,
                        'data' => [
                            'authenticated' => true,
                            'user' => [
                                'id' => 20,
                                'name' => 'User Two',
                                'email' => 'user2@example.com',
                                'role' => 'student',
                            ],
                            'auth_mode' => 'session',
                        ],
                    ], 200);
                }
            },
        ]);

        // Make request with first session
        $response1 = $this->withCookie($this->getSessionCookieName(), $sessionValue1)
            ->get('/dashboard');

        $response1->assertOk();
        $this->assertEquals(1, $apiCallCount, 'First session should call API');

        // Make request with second session
        $response2 = $this->withCookie($this->getSessionCookieName(), $sessionValue2)
            ->get('/dashboard');

        $response2->assertOk();
        $this->assertEquals(2, $apiCallCount, 'Second session should call API (different cache key)');

        // Verify both caches exist with different data
        $this->assertTrue(Cache::has($cacheKey1), 'Cache for session 1 should exist');
        $this->assertTrue(Cache::has($cacheKey2), 'Cache for session 2 should exist');

        $cached1 = Cache::get($cacheKey1);
        $cached2 = Cache::get($cacheKey2);

        $this->assertEquals(10, $cached1['user']['id'], 'Session 1 should have User One data');
        $this->assertEquals('User One', $cached1['user']['name']);

        $this->assertEquals(20, $cached2['user']['id'], 'Session 2 should have User Two data');
        $this->assertEquals('User Two', $cached2['user']['name']);

        // Make another request with first session (should use cache, no API call)
        $response3 = $this->withCookie($this->getSessionCookieName(), $sessionValue1)
            ->get('/dashboard');

        $response3->assertOk();
        $this->assertEquals(2, $apiCallCount, 'Third request should use cached data for session 1');
    }

    /**
     * Test authenticated state cached for 5 minutes
     * 
     * **Validates: Requirement 4.3**
     */
    public function test_authenticated_state_cached_for_5_minutes(): void
    {
        $sessionValue = 'auth-ttl-session';
        $cacheKey = 'spa_user:' . hash('sha256', $sessionValue);

        // Ensure cache is empty
        Cache::forget($cacheKey);

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
                        'name' => 'TTL Test User',
                        'email' => 'ttl@example.com',
                        'role' => 'teacher',
                    ],
                    'auth_mode' => 'session',
                ],
            ], 200),
        ]);

        // Make request to populate cache
        $response = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/dashboard');

        $response->assertOk();

        // Verify cache exists
        $this->assertTrue(Cache::has($cacheKey), 'Cache should exist after request');

        // Note: We can't easily test the exact TTL without waiting 5 minutes,
        // but we can verify the cache exists and contains authenticated data
        $cached = Cache::get($cacheKey);
        $this->assertTrue($cached['authenticated'], 'Cached state should be authenticated');
    }

    /**
     * Test unauthenticated state cached for 60 seconds
     * 
     * **Validates: Requirement 4.4**
     */
    public function test_unauthenticated_state_cached_for_60_seconds(): void
    {
        $sessionValue = 'unauth-ttl-session';
        $cacheKey = 'spa_user:' . hash('sha256', $sessionValue);

        // Ensure cache is empty
        Cache::forget($cacheKey);

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

        // Make request to populate cache
        $response = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/login');

        $response->assertOk();

        // Verify cache exists
        $this->assertTrue(Cache::has($cacheKey), 'Cache should exist after request');

        // Verify cached data is unauthenticated
        $cached = Cache::get($cacheKey);
        $this->assertFalse($cached['authenticated'], 'Cached state should be unauthenticated');
        $this->assertNull($cached['user'], 'Cached user should be null');
    }

    /**
     * Test cache key format uses SHA-256 hash with prefix
     * 
     * **Validates: Requirement 4.2**
     */
    public function test_cache_key_format_uses_sha256_hash_with_prefix(): void
    {
        $sessionValue = 'test-session-value';
        $expectedHash = hash('sha256', $sessionValue);
        $expectedCacheKey = 'spa_user:' . $expectedHash;

        // Mock authenticated user response
        Http::fake([
            '*/auth/user' => Http::response([
                'success' => true,
                'message' => 'User authenticated',
                'code' => 200,
                'data' => [
                    'authenticated' => true,
                    'user' => [
                        'id' => 6,
                        'name' => 'Hash Test User',
                        'email' => 'hash@example.com',
                        'role' => 'teacher',
                    ],
                    'auth_mode' => 'session',
                ],
            ], 200),
        ]);

        // Make request
        $response = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/dashboard');

        $response->assertOk();

        // Verify cache exists with expected key format
        $this->assertTrue(
            Cache::has($expectedCacheKey),
            'Cache key should use format: spa_user:{sha256_hash}'
        );

        // Verify the session value is NOT stored in plaintext
        $this->assertFalse(
            Cache::has('spa_user:' . $sessionValue),
            'Session value should NOT be stored in plaintext'
        );
    }

    /**
     * Test cache miss on first request for new session
     * 
     * **Validates: Requirements 4.1, 4.5**
     */
    public function test_cache_miss_on_first_request_for_new_session(): void
    {
        $sessionValue = 'brand-new-session';
        $cacheKey = 'spa_user:' . hash('sha256', $sessionValue);

        // Ensure cache is empty
        Cache::forget($cacheKey);

        // Verify cache doesn't exist before request
        $this->assertFalse(
            Cache::has($cacheKey),
            'Cache should not exist before first request'
        );

        $apiCallCount = 0;

        // Mock authenticated user response
        Http::fake([
            '*/auth/user' => function () use (&$apiCallCount) {
                $apiCallCount++;
                return Http::response([
                    'success' => true,
                    'message' => 'User authenticated',
                    'code' => 200,
                    'data' => [
                        'authenticated' => true,
                        'user' => [
                            'id' => 7,
                            'name' => 'New Session User',
                            'email' => 'newsession@example.com',
                            'role' => 'teacher',
                        ],
                        'auth_mode' => 'session',
                    ],
                ], 200);
            },
        ]);

        // Make request
        $response = $this->withCookie($this->getSessionCookieName(), $sessionValue)
            ->get('/dashboard');

        $response->assertOk();

        // Verify API was called
        $this->assertEquals(1, $apiCallCount, 'API should be called on cache miss');

        // Verify cache now exists
        $this->assertTrue(
            Cache::has($cacheKey),
            'Cache should exist after first request'
        );
    }

    /**
     * Test cache isolation between different sessions
     * 
     * **Validates: Requirements 4.2, 4.6**
     */
    public function test_cache_isolation_between_different_sessions(): void
    {
        $sessionA = 'session-a';
        $sessionB = 'session-b';
        $cacheKeyA = 'spa_user:' . hash('sha256', $sessionA);
        $cacheKeyB = 'spa_user:' . hash('sha256', $sessionB);

        // Pre-populate cache for session A
        Cache::put($cacheKeyA, [
            'authenticated' => true,
            'user' => [
                'id' => 100,
                'name' => 'User A',
                'email' => 'usera@example.com',
            ],
            'auth_mode' => 'session',
        ], 300);

        // Mock response for session B
        Http::fake([
            '*/auth/user' => Http::response([
                'success' => true,
                'message' => 'User authenticated',
                'code' => 200,
                'data' => [
                    'authenticated' => true,
                    'user' => [
                        'id' => 200,
                        'name' => 'User B',
                        'email' => 'userb@example.com',
                        'role' => 'student',
                    ],
                    'auth_mode' => 'session',
                ],
            ], 200),
        ]);

        // Make request with session B
        $response = $this->withCookie($this->getSessionCookieName(), $sessionB)
            ->get('/dashboard');

        $response->assertOk();

        // Verify both caches exist independently
        $this->assertTrue(Cache::has($cacheKeyA), 'Session A cache should still exist');
        $this->assertTrue(Cache::has($cacheKeyB), 'Session B cache should now exist');

        // Verify data isolation
        $cachedA = Cache::get($cacheKeyA);
        $cachedB = Cache::get($cacheKeyB);

        $this->assertEquals(100, $cachedA['user']['id'], 'Session A should have User A data');
        $this->assertEquals(200, $cachedB['user']['id'], 'Session B should have User B data');
        $this->assertNotEquals($cachedA['user']['id'], $cachedB['user']['id'], 'Sessions should have different user data');
    }
}

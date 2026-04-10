# Implementation Plan: Session Auth Migration

## Overview

Replace the Bearer token / `localStorage` authentication layer with Laravel Sanctum SPA cookie-based session auth. The migration proceeds in layers: server config → HTTP transport → auth state → middleware → auth pages → downstream consumers → cleanup.

## Tasks

- [x] 1. Update environment config and `config/session.php`
  - In `config/session.php`, change `same_site` to `env('SESSION_SAME_SITE', 'lax')`, `secure` to `env('SESSION_SECURE_COOKIE')`, and `domain` to `env('SESSION_DOMAIN')` (already uses env — verify values match design)
  - Add `VITE_SANCTUM_URL=https://api.suganta.com` to `.env.example`
  - Add the API_Server config comment block (`SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `SESSION_SAME_SITE=none`, `SESSION_SECURE_COOKIE=true`) to `.env.example`
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 17.1, 17.2, 17.3, 17.4_

- [x] 2. Refactor `resources/js/api.js` — remove Bearer injection, add `withCredentials` and CSRF helper
  - [x] 2.1 Remove all `Authorization: Bearer` header injection and all `localStorage` reads for `AUTH_TOKEN_KEY`, `AUTH_SESSION_TS_KEY`, `AUTH_DEVICE_TOKEN_KEY` from the request interceptor
    - Retain `X-Device-Token` injection from `AUTH_DEVICE_TOKEN_KEY` (non-credential device identifier)
    - Retain `X-Client-Fingerprint`, `X-Request-Timestamp`, and origin allowlist check
    - Set `withCredentials: true` on the axios instance defaults
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 2.4_
  - [x] 2.2 Update the 401 response interceptor to dispatch `app:unauthorized` without reading or clearing `localStorage` tokens
    - Update the 403 response interceptor similarly — dispatch `app:unauthorized` on non-exempt paths without clearing tokens
    - _Requirements: 3.5, 3.6, 16.1_
  - [x] 2.3 Export `ensureCsrf()` helper that calls `GET /sanctum/csrf-cookie` on `VITE_SANCTUM_URL` with `withCredentials: true`
    - _Requirements: 2.1, 2.2, 2.3_
  - [ ]* 2.4 Write property tests for the axios interceptor (`tests/js/api.property.test.js`)
    - **Property 1: No Bearer token in outgoing requests** — for any `localStorage` state, no `Authorization: Bearer` header is injected
    - **Property 2: Required security headers always present** — `X-Client-Fingerprint` and `X-Request-Timestamp` are always set
    - **Property 3: Origin allowlist blocks untrusted requests** — requests to non-`VITE_API_DOMAIN` origins are rejected
    - **Property 4: CSRF prefetch gates state-mutating requests** — if `ensureCsrf()` throws, the main request is not made
    - **Validates: Requirements 3.1, 3.2, 3.4, 3.7, 2.1, 2.3, 15.2**

- [x] 3. Update `app/Http/Middleware/HandleInertiaRequests.php` to share `Auth::user()`
  - Replace `'user' => null` with `Auth::user()?->only([...safe fields...])` using the field list from the design (`id`, `name`, `first_name`, `last_name`, `email`, `role`, `phone`, `profile_pic`, `email_verified_at`, `registration_fee_status`, `payment_required`, `verification_status`)
  - Add `use Illuminate\Support\Facades\Auth;` import
  - _Requirements: 5.1, 5.2, 5.3_
  - [ ]* 3.1 Write PHPUnit data-provider tests for `HandleInertiaRequests` (`tests/Feature/HandleInertiaRequestsTest.php`)
    - **Property 10: Middleware shares user without sensitive fields** — for any `Auth::user()` model, `auth.user` must not contain `password`, `remember_token`, or `*_hash` fields
    - **Validates: Requirements 5.2, 5.3**

- [x] 4. Refactor `resources/js/composables/useAuth.js` to read from Inertia shared props
  - [x] 4.1 Replace `getUser()` to return `usePage().props.auth.user` instead of `localStorage`
    - Replace `isAuthenticated()` to return `!!usePage().props.auth.user`
    - Remove `getToken()` (or return `null` unconditionally) and remove `setSession()`
    - Remove `refreshToken()`
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.8_
  - [x] 4.2 Update `clearSession()` to remove only non-credential `localStorage` keys (`PAYMENT_DETAILS_KEY`, `AUTH_IDENTIFIER_KEY`, `REGISTRATION_CHARGES_KEY`, `POST_VERIFY_LOGIN_NOTICE_KEY`) and call `localStorage.removeItem('auth_token')` and `localStorage.removeItem('auth_session_ts')` once to purge legacy keys
    - _Requirements: 4.5, 15.3_
  - [x] 4.3 Update `canAccessDashboard()` and `enforceBestRoute()` / `getBestAuthRoute()` to use the Inertia props user instead of `getToken()` / `getUser()` from `localStorage`
    - _Requirements: 4.6, 4.7, 16.3_
  - [ ]* 4.4 Write property tests for `useAuth` composable (`tests/js/useAuth.property.test.js`)
    - **Property 5: getUser reflects Inertia shared props** — for any `usePage().props.auth.user` value, `getUser()` returns exactly that value
    - **Property 6: isAuthenticated matches user presence** — returns `true` iff user is a non-null object
    - **Property 7: clearSession removes only correct keys** — legacy `auth_token`/`auth_session_ts` are removed; `AUTH_DEVICE_TOKEN_KEY` is retained
    - **Property 8: canAccessDashboard consistency** — true iff user is non-null, `isEmailVerified` true, `isRegistrationFeeSatisfied` true
    - **Property 9: enforceBestRoute consistency** — correct route for null/verified+paid/verified+unpaid/unverified user states
    - **Validates: Requirements 4.1, 4.2, 4.5, 4.6, 4.7, 15.3, 16.3**

- [x] 5. Simplify `resources/js/stores/auth.js`
  - Remove `token` state property and all `localStorage` reads for `AUTH_TOKEN_KEY`
  - Remove or no-op `syncFromStorage()` action
  - Update `isAuthenticated` getter to derive from `usePage().props.auth.user != null`
  - Update `reset()` to clear only transient state (`requiresOtp`, `lastPaymentGate`)
  - Retain `requiresOtp`, `lastPaymentGate`, `setRequiresOtp`, `setLastPaymentGate`, `clearTransient`
  - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

- [x] 6. Checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. Refactor auth pages to remove `setSession()` and add CSRF prefetch
  - [x] 7.1 Update `Login.vue`: call `ensureCsrf()` before `POST /auth/login`; on success call `router.visit(route('dashboard'))` without `setSession()`; on `requires_otp` store only `auth_identifier`; remove all `setSession()` calls; ignore any `token` field in the response
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_
  - [x] 7.2 Update `VerifyOtp.vue`: remove all `setSession()` calls; on success call `router.visit(route('dashboard'))` without `setSession()`; retain `auth_identifier` for OTP flow
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_
  - [x] 7.3 Update `Register.vue`: call `ensureCsrf()` before `POST /auth/register`; remove `setSession()` call; on success redirect to login
    - _Requirements: 8.1, 8.2, 8.3_
  - [x] 7.4 Update `Payment.vue`: ensure `handleLogout()` POSTs to `/auth/logout` (already does via axios instance with `withCredentials`); remove any reads of `AUTH_TOKEN_KEY`
    - _Requirements: 9.1, 9.2, 9.3_

- [x] 8. Update `resources/js/Layouts/AppLayout.vue`
  - Initialize `user` ref from `usePage().props.auth.user` instead of `getUser()`
  - Update `onInertiaFinish` to refresh `user.value` from `usePage().props.auth.user`
  - Update `handleUnauthorized` to call `clearSession()` and redirect without reading `AUTH_TOKEN_KEY`
  - Update `logout()` to POST `/auth/logout`, call `clearSession()`, then redirect (already structured this way — verify no token reads remain)
  - Update `connectEcho()` call to pass `null` instead of `() => getToken()`
  - Update `syncChatRealtimeSubscriptions()` to call `connectEcho(null)` instead of `connectEcho(() => getToken())`
  - Remove `getToken` from the destructured `useAuth()` call
  - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

- [x] 9. Refactor `resources/js/services/chatEcho.js` — remove Bearer token from Echo authorizer
  - Remove the `getAccessToken` parameter from `connectEcho()` (or make it ignored)
  - Remove `Authorization: Bearer` header from the `authorizer` axios POST to `/broadcasting/auth`
  - Add `withCredentials: true` to the authorizer request (or rely on the global axios default)
  - _Requirements: 12.5_
  - [ ]* 9.1 Write property tests for the Echo authorizer (`tests/js/chatEcho.property.test.js`)
    - **Property 12: Echo authorizer uses withCredentials, no Bearer header** — for any channel authorization request, `withCredentials: true` is set and no `Authorization` header is present
    - **Validates: Requirements 12.5**

- [x] 10. Refactor `app/Http/Controllers/BroadcastingAuthProxyController.php`
  - Remove the `Authorization` header check and early-return 401 (no longer required)
  - Forward the session cookie from the incoming request to the API_Server using `Http::withCookies($request->cookies->all(), parse_url($apiOrigin, PHP_URL_HOST))`
  - Remove all reads of the `Authorization` header from the incoming request when building the upstream request
  - Update `tryLocalSign()` to use cookie forwarding instead of Bearer token when calling the conversation endpoint
  - _Requirements: 12.1, 12.2, 12.3, 12.4_
  - [ ]* 10.1 Write PHPUnit data-provider tests for `BroadcastingAuthProxyController` (`tests/Feature/BroadcastingAuthProxyTest.php`)
    - **Property 11: Broadcasting proxy forwards session cookie, not Bearer token** — upstream request includes session cookie and no `Authorization: Bearer` header
    - **Validates: Requirements 12.1, 12.2**

- [x] 11. Verify Firebase push token services require no changes
  - Confirm `pushTokenApi.js` uses the shared axios instance without manual `Authorization` header — no changes needed
  - Confirm `firebaseWebPush.js` does not read `AUTH_TOKEN_KEY` before calling `registerPushToken` / `unregisterPushToken` — no changes needed
  - If any manual token attachment is found, remove it
  - _Requirements: 13.1, 13.2, 13.3, 13.4_

- [x] 12. Audit and fix API composables and service modules
  - Scan `useNotesApi`, `useMarketplaceApi`, `useLeadApi`, `useGoogleWorkspaceApi`, `useSubscriptionsApi`, `usePortfolioApi`, `chatApi.js`, `aiAdviserApi.js`, `studyRequirementsApi.js` for any manual `Authorization: Bearer` header construction or direct `localStorage.getItem(AUTH_TOKEN_KEY)` reads
  - Remove any such reads and ensure all use the shared axios instance
  - _Requirements: 14.1, 14.2, 14.3_

- [x] 13. Checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 14. Clean up `resources/js/constants/authStorage.js` and remove obsolete imports
  - Remove `AUTH_TOKEN_KEY` and `AUTH_SESSION_TS_KEY` exports from `authStorage.js` after all consumers are updated
  - Remove `AUTH_USER_KEY` export (legacy user object cache key, no longer stored)
  - Remove any remaining imports of `AUTH_TOKEN_KEY`, `AUTH_SESSION_TS_KEY`, `AUTH_USER_KEY` from `api.js`, `useAuth.js`, `stores/auth.js`, and any other files
  - Retain `PAYMENT_DETAILS_KEY`, `AUTH_IDENTIFIER_KEY`, `REGISTRATION_CHARGES_KEY`, `AUTH_REDIRECT_REASON_KEY`, `POST_VERIFY_LOGIN_NOTICE_KEY`, `AUTH_DEVICE_TOKEN_KEY`, `EMAIL_VERIFY_LOGIN_FLOW_KEY`
  - _Requirements: 15.1, 15.2, 15.4_

- [x] 15. Final checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation at key migration boundaries
- Property tests use [fast-check](https://github.com/dubzzz/fast-check) (JS) and PHPUnit data providers (PHP)
- The `pushTokenApi.js` and `firebaseWebPush.js` services are already compliant post-axios-refactor (task 11 is a verification step)
- `config/session.php` already uses `env()` for `domain`, `secure`, and `same_site` — task 1 verifies the defaults match the design requirements

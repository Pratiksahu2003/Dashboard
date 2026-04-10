# Requirements Document

## Introduction

The SuGanta dashboard (`app.suganta.com`) currently authenticates users with Bearer tokens stored in `localStorage` and sent as `Authorization: Bearer` headers on every API request to `api.suganta.com`. This feature migrates the entire authentication layer to Laravel Sanctum SPA cookie-based sessions. After migration, the browser will carry a session cookie automatically; no token will be stored in `localStorage`; and the Inertia `HandleInertiaRequests` middleware will share the authenticated user via `Auth::user()` so the frontend reads identity from Inertia shared props rather than local storage.

The migration touches: the axios instance (`api.js`), the `useAuth` composable, the `useAuthStore` Pinia store, all auth pages (Login, Register, VerifyOtp, Payment), `AppLayout.vue`, the `BroadcastingAuthProxyController`, the `HandleInertiaRequests` middleware, Firebase push-token registration, the Echo/Pusher authorizer, and all API composables and services.

---

## Glossary

- **SPA_Client**: The Inertia/Vue 3 frontend running at `app.suganta.com`.
- **API_Server**: The separate Laravel API server running at `api.suganta.com`.
- **Sanctum**: Laravel Sanctum package providing SPA cookie authentication.
- **CSRF_Cookie**: The `XSRF-TOKEN` cookie issued by `GET /sanctum/csrf-cookie` on the API_Server.
- **Session_Cookie**: The encrypted `laravel_session` cookie that identifies the authenticated session on the API_Server.
- **Axios_Instance**: The shared axios instance exported from `resources/js/api.js`.
- **Auth_Composable**: The `useAuth` composable in `resources/js/composables/useAuth.js`.
- **Auth_Store**: The `useAuthStore` Pinia store in `resources/js/stores/auth.js`.
- **Inertia_Shared_Props**: The `auth.user` object injected by `HandleInertiaRequests.share()` and accessible via `usePage().props.auth.user`.
- **Broadcasting_Proxy**: The `BroadcastingAuthProxyController` that proxies Pusher/Reverb channel authorization to the API_Server.
- **Push_Token_API**: The `pushTokenApi.js` service that registers/unregisters FCM tokens with the API_Server.
- **OTP_Flow**: The two-step login path where the API_Server returns `requires_otp: true` and the user completes verification on the `VerifyOtp` page.
- **Payment_Gate**: The registration-fee enforcement page (`Payment.vue`) shown when `payment_required: true`.
- **Stateful_Domain**: A domain listed in `SANCTUM_STATEFUL_DOMAINS` that the API_Server treats as eligible for cookie-based SPA auth.

---

## Requirements

### Requirement 1: Sanctum Stateful Domain Configuration

**User Story:** As a platform operator, I want `app.suganta.com` to be recognized as a Sanctum stateful domain on the API_Server, so that SPA cookie authentication works across the two-domain setup.

#### Acceptance Criteria

1. THE API_Server SHALL list `app.suganta.com` (and `localhost` variants for local development) in `SANCTUM_STATEFUL_DOMAINS`.
2. THE API_Server SHALL set `SESSION_DOMAIN` to `.suganta.com` so the session cookie is scoped to the shared root domain.
3. THE API_Server SHALL set `SESSION_SAME_SITE` to `none` and `SESSION_SECURE_COOKIE` to `true` to allow cross-subdomain cookie delivery over HTTPS.
4. THE SPA_Client `.env` / `.env.example` SHALL document the required `SANCTUM_STATEFUL_DOMAINS` and `SESSION_DOMAIN` values as comments or example entries so operators know what to configure on the API_Server.

---

### Requirement 2: CSRF Cookie Initialization

**User Story:** As a developer, I want the SPA_Client to fetch the CSRF cookie before any state-mutating request, so that Sanctum can verify the request origin and prevent CSRF attacks.

#### Acceptance Criteria

1. WHEN the SPA_Client is about to submit a login, OTP verify, or registration request, THE Axios_Instance SHALL first issue `GET /sanctum/csrf-cookie` to the API_Server with `withCredentials: true`.
2. THE Axios_Instance SHALL include the `X-XSRF-TOKEN` header (populated automatically by axios from the `XSRF-TOKEN` cookie) on all subsequent state-mutating requests.
3. IF the `GET /sanctum/csrf-cookie` request fails, THEN THE SPA_Client SHALL surface an error message and SHALL NOT proceed with the login or registration request.
4. THE Axios_Instance SHALL set `withCredentials: true` globally so session and CSRF cookies are sent on every cross-origin request to the API_Server.

---

### Requirement 3: Axios Instance Refactoring

**User Story:** As a developer, I want the shared axios instance to use cookie-based auth instead of Bearer tokens, so that all API calls automatically carry the session cookie without manual token management.

#### Acceptance Criteria

1. THE Axios_Instance SHALL remove all `Authorization: Bearer` header injection from the request interceptor.
2. THE Axios_Instance SHALL remove all reads from `localStorage` for `AUTH_TOKEN_KEY`, `AUTH_SESSION_TS_KEY`, and `AUTH_DEVICE_TOKEN_KEY` in the request interceptor.
3. THE Axios_Instance SHALL retain the `X-Device-Token` header injection using a device token sourced from a non-sensitive mechanism (e.g., a short-lived cookie or removed entirely if the API_Server no longer requires it).
4. THE Axios_Instance SHALL retain the `X-Client-Fingerprint` and `X-Request-Timestamp` headers.
5. WHEN a response has HTTP status 401, THE Axios_Instance SHALL dispatch the `app:unauthorized` custom DOM event without reading or clearing `localStorage` tokens.
6. WHEN a response has HTTP status 403 and the current path is not `/otp-verify` or `/payment-required`, THE Axios_Instance SHALL dispatch the `app:unauthorized` custom DOM event.
7. THE Axios_Instance SHALL retain the origin allowlist check to block requests to untrusted origins.

---

### Requirement 4: Auth Composable Refactoring

**User Story:** As a developer, I want `useAuth` to derive authentication state from Inertia shared props instead of `localStorage`, so that the frontend has a single, server-authoritative source of truth for the current user.

#### Acceptance Criteria

1. THE Auth_Composable `getUser()` function SHALL read the user object from `usePage().props.auth.user` (Inertia_Shared_Props) instead of `localStorage`.
2. THE Auth_Composable `isAuthenticated()` function SHALL return `true` when `usePage().props.auth.user` is a non-null object, and `false` otherwise.
3. THE Auth_Composable `getToken()` function SHALL be removed or SHALL return `null` unconditionally, as Bearer tokens are no longer used.
4. THE Auth_Composable `setSession()` function SHALL be removed or SHALL be a no-op, as session state is managed server-side.
5. THE Auth_Composable `clearSession()` function SHALL remove only non-auth `localStorage` keys (e.g., `PAYMENT_DETAILS_KEY`, `AUTH_IDENTIFIER_KEY`, `REGISTRATION_CHARGES_KEY`, `POST_VERIFY_LOGIN_NOTICE_KEY`) and SHALL NOT attempt to remove `AUTH_TOKEN_KEY` or `AUTH_SESSION_TS_KEY`.
6. THE Auth_Composable `canAccessDashboard()` function SHALL evaluate `isRegistrationFeeSatisfied` and `isEmailVerified` against the user from Inertia_Shared_Props.
7. THE Auth_Composable `enforceBestRoute()` function SHALL use the Inertia_Shared_Props user to determine the best route, replacing all `localStorage` token checks.
8. THE Auth_Composable `refreshToken()` function SHALL be removed, as session refresh is handled transparently by the browser cookie mechanism.

---

### Requirement 5: HandleInertiaRequests Middleware Update

**User Story:** As a developer, I want the `HandleInertiaRequests` middleware to share the authenticated user from `Auth::user()`, so that every Inertia page receives the current user without a client-side localStorage lookup.

#### Acceptance Criteria

1. THE `HandleInertiaRequests` middleware `share()` method SHALL set `auth.user` to `Auth::user()` (or `null` when unauthenticated) instead of the hardcoded `null`.
2. WHEN `Auth::user()` returns a non-null model, THE middleware SHALL share the user as an array (e.g., `Auth::user()->toArray()` or a curated subset) to avoid exposing sensitive fields.
3. THE middleware SHALL NOT share `password`, `remember_token`, or any hashed credential fields in `auth.user`.

---

### Requirement 6: Login Page Refactoring

**User Story:** As a user, I want to log in using my email/password or OTP and have my session established via a Sanctum cookie, so that I no longer need a token stored in my browser's local storage.

#### Acceptance Criteria

1. WHEN the user submits the password login form, THE Login_Page SHALL first call `GET /sanctum/csrf-cookie`, then POST credentials to `/auth/login` with `withCredentials: true`.
2. WHEN the API_Server responds with `success: true` and a session cookie (no `token` field required), THE Login_Page SHALL call `router.visit(route('dashboard'))` without calling `setSession()`.
3. WHEN the API_Server responds with `requires_otp: true`, THE Login_Page SHALL store only the `auth_identifier` in `localStorage` (not a token) and navigate to the OTP verify page.
4. WHEN the API_Server responds with `requires_registration_payment: true`, THE Login_Page SHALL store payment details in `localStorage` and navigate to the payment page.
5. THE Login_Page SHALL remove all calls to `setSession({ token, user, deviceToken })`.
6. IF the API_Server returns a `token` field in the login response (legacy compatibility), THE Login_Page SHALL ignore it and rely solely on the session cookie.

---

### Requirement 7: OTP Verify Page Refactoring

**User Story:** As a user completing OTP verification, I want my session to be established via a Sanctum cookie after successful verification, so that no Bearer token is stored in local storage.

#### Acceptance Criteria

1. WHEN the user submits a valid OTP, THE VerifyOtp_Page SHALL POST to `/auth/login/verify` with `withCredentials: true`.
2. WHEN the API_Server responds with `success: true` and a session cookie, THE VerifyOtp_Page SHALL call `router.visit(route('dashboard'))` without calling `setSession()`.
3. THE VerifyOtp_Page SHALL remove all calls to `setSession({ token, user, deviceToken })`.
4. WHEN the API_Server responds with `requires_registration_payment: true`, THE VerifyOtp_Page SHALL store payment details in `localStorage` and navigate to the payment page.
5. THE VerifyOtp_Page SHALL retain the `auth_identifier` `localStorage` key for the OTP flow identifier (this is not a credential).

---

### Requirement 8: Registration Page Refactoring

**User Story:** As a new user registering, I want my registration to use cookie-based auth so that the flow is consistent with the session-based login.

#### Acceptance Criteria

1. WHEN the user submits the registration form, THE Register_Page SHALL first call `GET /sanctum/csrf-cookie`, then POST to `/auth/register` with `withCredentials: true`.
2. WHEN the API_Server responds with `success: true`, THE Register_Page SHALL redirect to the login page without calling `setSession()`.
3. THE Register_Page SHALL remove all calls to `setSession()` and SHALL NOT store any token in `localStorage`.

---

### Requirement 9: Payment Gate Page Refactoring

**User Story:** As a user required to pay a registration fee, I want the payment gate to work with session-based auth so that my session is preserved through the payment flow.

#### Acceptance Criteria

1. WHEN the user clicks "Cancel & return to sign in", THE Payment_Page SHALL POST to `/auth/logout` with `withCredentials: true` to invalidate the server-side session.
2. THE Payment_Page SHALL call `clearSession()` (which removes non-auth `localStorage` keys) after the logout request completes or fails.
3. THE Payment_Page SHALL NOT call `setSession()` or read `AUTH_TOKEN_KEY` from `localStorage`.

---

### Requirement 10: AppLayout Session Handling

**User Story:** As a developer, I want `AppLayout.vue` to derive user state from Inertia shared props and handle session expiry via the `app:unauthorized` event, so that the layout does not depend on `localStorage` tokens.

#### Acceptance Criteria

1. THE AppLayout SHALL read the current user from `usePage().props.auth.user` (Inertia_Shared_Props) instead of calling `getUser()` from the Auth_Composable.
2. WHEN the `app:unauthorized` event fires, THE AppLayout `handleUnauthorized` handler SHALL call `clearSession()` and redirect to the login route, without reading or clearing `AUTH_TOKEN_KEY`.
3. THE AppLayout `logout()` function SHALL POST to `/auth/logout` with `withCredentials: true`, call `clearSession()`, and then redirect to the login route.
4. THE AppLayout `connectEcho()` call SHALL NOT pass a `getToken` callback that reads from `localStorage`; instead it SHALL pass `null` or a no-op, as the Echo authorizer will use the session cookie.
5. WHEN the `inertia:finish` event fires, THE AppLayout SHALL refresh the displayed user from `usePage().props.auth.user`.

---

### Requirement 11: Auth Store Simplification

**User Story:** As a developer, I want the `useAuthStore` Pinia store to reflect session state from Inertia shared props rather than `localStorage`, so that the store is a thin reactive mirror of server-authoritative state.

#### Acceptance Criteria

1. THE Auth_Store SHALL remove the `token` state property and all `localStorage` reads for `AUTH_TOKEN_KEY`.
2. THE Auth_Store `syncFromStorage()` action SHALL be removed or SHALL be a no-op.
3. THE Auth_Store `isAuthenticated` getter SHALL derive its value from whether `auth.user` in Inertia_Shared_Props is non-null.
4. THE Auth_Store SHALL retain `requiresOtp`, `lastPaymentGate`, `setRequiresOtp`, `setLastPaymentGate`, and `clearTransient` for transient UI state that is not server-persisted.
5. THE Auth_Store `reset()` action SHALL clear only transient state (not attempt to clear `localStorage` tokens).

---

### Requirement 12: Broadcasting Auth Proxy Refactoring

**User Story:** As a developer, I want the `BroadcastingAuthProxyController` to forward the session cookie to the API_Server for channel authorization instead of a Bearer token, so that real-time chat works with cookie-based auth.

#### Acceptance Criteria

1. THE Broadcasting_Proxy SHALL read the session cookie from the incoming request and forward it to the API_Server's `/broadcasting/auth` endpoint using `withCookies()` or equivalent.
2. THE Broadcasting_Proxy SHALL remove all reads of the `Authorization` header from the incoming request.
3. WHEN the API_Server returns a non-200 response for channel authorization, THE Broadcasting_Proxy SHALL attempt the local HMAC signing fallback only after verifying the user's access via a session-authenticated request to the API_Server.
4. THE Broadcasting_Proxy `tryLocalSign()` method SHALL use the session cookie (not a Bearer token) when calling the API_Server's conversation endpoint to verify access.
5. THE Echo authorizer in `chatEcho.js` SHALL send the `/broadcasting/auth` request with `withCredentials: true` and SHALL NOT include an `Authorization: Bearer` header.

---

### Requirement 13: Firebase Push Token Registration

**User Story:** As a developer, I want FCM push token registration and unregistration to use the session cookie, so that the Push_Token_API calls are authenticated without a Bearer token.

#### Acceptance Criteria

1. THE Push_Token_API `registerPushToken()` function SHALL rely on the Axios_Instance (which uses `withCredentials: true`) and SHALL NOT manually attach an `Authorization` header.
2. THE Push_Token_API `unregisterPushToken()` function SHALL rely on the Axios_Instance and SHALL NOT manually attach an `Authorization` header.
3. THE `firebaseWebPush.js` `initWebPush()` function SHALL NOT read `AUTH_TOKEN_KEY` from `localStorage` before calling `registerPushToken`.
4. THE `firebaseWebPush.js` `teardownWebPush()` function SHALL NOT read `AUTH_TOKEN_KEY` from `localStorage` before calling `unregisterPushToken`.

---

### Requirement 14: API Composables and Services

**User Story:** As a developer, I want all API composables and service modules to use the session cookie for authentication, so that no composable manually reads or attaches a Bearer token.

#### Acceptance Criteria

1. THE `useNotesApi`, `useMarketplaceApi`, `useLeadApi`, `useGoogleWorkspaceApi`, `useSubscriptionsApi`, and `usePortfolioApi` composables SHALL use the shared Axios_Instance without manually reading `AUTH_TOKEN_KEY` from `localStorage`.
2. THE `chatApi.js`, `aiAdviserApi.js`, and `studyRequirementsApi.js` service modules SHALL use the shared Axios_Instance without manually attaching `Authorization: Bearer` headers.
3. IF any composable or service currently constructs its own axios instance with a Bearer token, THEN it SHALL be refactored to use the shared Axios_Instance or to set `withCredentials: true` without a Bearer header.

---

### Requirement 15: localStorage Key Cleanup

**User Story:** As a developer, I want all obsolete `localStorage` keys related to token-based auth to be removed from the codebase, so that no stale token data persists in the browser after migration.

#### Acceptance Criteria

1. THE `authStorage.js` constants file SHALL remove `AUTH_TOKEN_KEY` and `AUTH_SESSION_TS_KEY` exports after all consumers are updated.
2. WHEN a user who previously had a Bearer token stored in `localStorage` visits the app after migration, THE SPA_Client SHALL gracefully ignore any stale `auth_token` key and SHALL NOT attempt to use it as a Bearer token.
3. THE `clearSession()` function in the Auth_Composable SHALL call `localStorage.removeItem('auth_token')` and `localStorage.removeItem('auth_session_ts')` once on first run to purge legacy keys from existing browser sessions.
4. THE `authStorage.js` file SHALL retain `PAYMENT_DETAILS_KEY`, `AUTH_IDENTIFIER_KEY`, `REGISTRATION_CHARGES_KEY`, `AUTH_REDIRECT_REASON_KEY`, and `POST_VERIFY_LOGIN_NOTICE_KEY` as these are non-credential UI-state keys still needed by the payment and OTP flows.

---

### Requirement 16: Session Expiry and Unauthorized Handling

**User Story:** As a user, I want to be redirected to the login page when my session expires or is invalidated, so that I am never stuck in a broken authenticated state.

#### Acceptance Criteria

1. WHEN the API_Server returns HTTP 401 on any request, THE Axios_Instance SHALL dispatch the `app:unauthorized` DOM event.
2. WHEN the `app:unauthorized` event fires in AppLayout, THE AppLayout SHALL redirect the user to the login route using `router.visit` with `replace: true`.
3. THE Auth_Composable `enforceBestRoute()` SHALL treat a null `auth.user` in Inertia_Shared_Props as the unauthenticated state and SHALL redirect to the login route when on a protected page.
4. WHEN the user's session expires between Inertia page navigations, THE Inertia router SHALL receive a redirect response from the server and SHALL navigate to the login page automatically via Inertia's built-in redirect handling.

---

### Requirement 17: Environment Configuration

**User Story:** As a platform operator, I want clear environment variable documentation for the session-based auth setup, so that I can configure both the SPA_Client and API_Server correctly.

#### Acceptance Criteria

1. THE SPA_Client `.env.example` SHALL include `VITE_SANCTUM_URL` pointing to the API_Server base URL used for `GET /sanctum/csrf-cookie`.
2. THE SPA_Client `.env.example` SHALL include a comment block explaining that `SESSION_DOMAIN`, `SESSION_SAME_SITE=none`, `SESSION_SECURE_COOKIE=true`, and `SANCTUM_STATEFUL_DOMAINS` must be set on the API_Server.
3. THE SPA_Client `config/session.php` SHALL set `same_site` to `none` and `secure` to `true` for the production environment to support cross-subdomain cookie delivery.
4. THE SPA_Client `config/session.php` `domain` value SHALL be set to `.suganta.com` in production via `SESSION_DOMAIN=.suganta.com`.

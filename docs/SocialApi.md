# Social API

Complete API reference for the unversioned social authentication, phone onboarding, passkey, registration payment, and profile routes defined in `routes/social.php`.

**Base path:** `/api`  
**Route source:** `routes/social.php`  
**Controllers:**

| Controller | Responsibility |
|------------|----------------|
| `App\Http\Controllers\Api\AuthController` | Firebase social login, social phone OTP, profile completion |
| `App\Http\Controllers\Api\PasskeyController` | WebAuthn passkey registration and login |
| `App\Http\Controllers\Api\PaymentController` | Registration fee order creation and verification |
| `App\Http\Controllers\Api\ProfileController` | Final authenticated profile state |

---

## Common Headers

For JSON requests:

```http
Accept: application/json
Content-Type: application/json
```

For protected routes:

```http
Authorization: Bearer {sanctum_token}
```

Protected routes use Laravel Sanctum through `auth:sanctum`. Browser SPA clients may also authenticate through a configured Sanctum session cookie.

---

## Endpoint Summary

| Method | Endpoint | Auth | Middleware | Purpose |
|--------|----------|------|------------|---------|
| `POST` | `/api/auth/social-login` | Public | API | Verify Firebase ID token and issue Sanctum token |
| `POST` | `/api/auth/send-otp` | Required | `auth:sanctum` | Send OTP to an onboarding phone number |
| `POST` | `/api/auth/verify-otp` | Required | `auth:sanctum` | Verify OTP and save phone on the authenticated user |
| `POST` | `/api/auth/complete-profile` | Required | `auth:sanctum` | Set role, mark profile complete, and return payment state |
| `POST` | `/api/auth/passkey/register/options` | Required | `auth:sanctum` | Create WebAuthn registration challenge |
| `POST` | `/api/auth/passkey/register/verify` | Required | `auth:sanctum` | Verify WebAuthn attestation and save passkey |
| `POST` | `/api/auth/passkey/login/options` | Public | API | Create WebAuthn login challenge |
| `POST` | `/api/auth/passkey/login/verify` | Public | API | Verify WebAuthn assertion and issue Sanctum token |
| `POST` | `/api/payment/create-order` | Required | `auth:sanctum`, `profile.complete` | Create or reuse registration fee payment order |
| `POST` | `/api/payment/verify` | Required | `auth:sanctum`, `profile.complete` | Verify Cashfree registration fee payment |
| `GET` | `/api/profile` | Required | `auth:sanctum`, `profile.complete`, `payment.complete` | Return profile after onboarding and payment are complete |

---

## Recommended Flow

1. Call `POST /api/auth/social-login`.
2. Store the returned Sanctum token.
3. If `needs_onboarding` is `true`, verify phone:
   - Call `POST /api/auth/send-otp`.
   - Call `POST /api/auth/verify-otp`.
   - Call `POST /api/auth/complete-profile`.
4. If `needs_payment` is `true`, call `POST /api/payment/create-order`, open the returned checkout URL, then call `POST /api/payment/verify`.
5. After onboarding and payment are complete, call `GET /api/profile`.
6. Optionally register a passkey after login with `POST /api/auth/passkey/register/options` and `POST /api/auth/passkey/register/verify`.
7. Later, login with passkey using `POST /api/auth/passkey/login/options` and `POST /api/auth/passkey/login/verify`.

Current implementation note: `complete-profile` validates `phone` but only updates role/profile/payment flags. The phone is saved by `verify-otp` or by Firebase social login when the Firebase token already contains a phone number.

---

## Shared Response Objects

### User Object

Most auth/profile responses return this user shape:

```json
{
  "id": 101,
  "name": "Amit Sharma",
  "email": "amit@example.com",
  "phone": "+919876543210",
  "role": "teacher",
  "avatar": "https://example.com/avatar.jpg",
  "is_phone_verified": true,
  "is_profile_complete": true,
  "is_fee_paid": false
}
```

Passkey login currently returns a smaller user object:

```json
{
  "id": 101,
  "name": "Amit Sharma",
  "email": "amit@example.com",
  "phone": "+919876543210",
  "role": "teacher"
}
```

If the user was created without an email, the backend may store an internal `@firebase.suganta.invalid` email. API responses convert that value to `null`.

### Validation Error

```json
{
  "message": "The given data was invalid.",
  "success": false,
  "code": 422,
  "errors": {
    "phone": [
      "The phone field is required."
    ]
  }
}
```

### Authentication Error

```json
{
  "message": "Unauthenticated.",
  "success": false,
  "code": 401
}
```

### Rate Limit Error

Some controller rate limits return only a message:

```json
{
  "message": "Too many login attempts."
}
```

OTP rate limits are raised through Laravel exceptions and include `success` and `code`.

---

## 1. Social Login

**Endpoint:** `POST /api/auth/social-login`  
**Auth:** Public  
**Rate limit:** 30 attempts per minute per IP

Verifies a Firebase ID token, finds or creates the local user, and returns a Sanctum token.

### Request Body

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `firebase_token` | string | Yes | Minimum 20 characters; must be a valid Firebase ID token |
| `device_name` | string | No | Max 120 characters; token device name |

```json
{
  "firebase_token": "eyJhbGciOiJSUzI1NiIsImtpZCI6...",
  "device_name": "Chrome on Windows"
}
```

### Success Response: `200 OK`

```json
{
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "token_type": "Bearer",
  "needs_onboarding": true,
  "needs_payment": false,
  "user": {
    "id": 101,
    "name": "Amit Sharma",
    "email": "amit@example.com",
    "phone": null,
    "role": "student",
    "avatar": "https://lh3.googleusercontent.com/a/...",
    "is_phone_verified": false,
    "is_profile_complete": false,
    "is_fee_paid": false
  }
}
```

### Notes

- User lookup order is Firebase UID, then social email, then Firebase phone.
- New users default to role `student`, `is_profile_complete = false`, and `is_fee_paid = false`.
- `needs_onboarding` is true until the user has a verified phone, a role, and profile completion flag.
- `needs_payment` is true only after onboarding is complete and the selected role has a paid registration fee.

### Error Responses

| HTTP | Cause |
|------|-------|
| `422` | Missing or invalid `firebase_token` |
| `422` | Firebase account already linked to another SuGanta user |
| `429` | Too many login attempts |
| `500` | Firebase service account is misconfigured |

---

## 2. Send Social Phone OTP

**Endpoint:** `POST /api/auth/send-otp`  
**Auth:** Required  
**Middleware:** `auth:sanctum`  
**OTP limits:** 3 requests per 15 minutes, with progressive cooldowns of 30 seconds, 2 minutes, and 5 minutes

Sends an SMS OTP to the submitted phone number for the authenticated user.

### Request Body

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `phone` | string | Yes | Regex: `^\+?[1-9][0-9]{7,14}$`; cannot belong to another user |

```json
{
  "phone": "+919876543210"
}
```

### Success Response: `200 OK`

```json
{
  "message": "OTP sent successfully.",
  "phone": "+919876543210"
}
```

### Error Responses

| HTTP | Cause |
|------|-------|
| `401` | Missing or invalid Sanctum token |
| `422` | Invalid phone format |
| `422` | Phone is already used by another user |
| `429` | OTP cooldown or request limit exceeded |

---

## 3. Verify Social Phone OTP

**Endpoint:** `POST /api/auth/verify-otp`  
**Auth:** Required  
**Middleware:** `auth:sanctum`

Verifies the SMS OTP and saves the normalized phone number to the authenticated user.

### Request Body

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `phone` | string | Yes | Regex: `^\+?[1-9][0-9]{7,14}$`; cannot belong to another user |
| `otp` | string | Yes | Exactly 6 characters |

```json
{
  "phone": "+919876543210",
  "otp": "123456"
}
```

### Success Response: `200 OK`

```json
{
  "message": "OTP verified successfully.",
  "phone": "+919876543210",
  "verified": true,
  "user": {
    "id": 101,
    "name": "Amit Sharma",
    "email": "amit@example.com",
    "phone": "+919876543210",
    "role": "student",
    "avatar": "https://lh3.googleusercontent.com/a/...",
    "is_phone_verified": true,
    "is_profile_complete": false,
    "is_fee_paid": false
  }
}
```

### Error Responses

| HTTP | Cause |
|------|-------|
| `401` | Missing or invalid Sanctum token |
| `422` | Invalid phone format |
| `422` | Phone is already used by another user |
| `422` | Invalid or expired OTP |

---

## 4. Complete Profile

**Endpoint:** `POST /api/auth/complete-profile`  
**Auth:** Required  
**Middleware:** `auth:sanctum`

Sets the user's role, marks the profile as complete, and returns the registration payment requirement for that role.

### Request Body

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `phone` | string | Yes | Regex: `^\+?[1-9][0-9]{7,14}$`; unique except current user |
| `role` | string | Yes | One of the keys in `config/registration.php` `charges` |

```json
{
  "phone": "+919876543210",
  "role": "teacher"
}
```

### Success Response: Paid Role, `200 OK`

```json
{
  "needs_payment": true,
  "amount": 299,
  "currency": "INR",
  "user": {
    "id": 101,
    "name": "Amit Sharma",
    "email": "amit@example.com",
    "phone": "+919876543210",
    "role": "teacher",
    "avatar": "https://lh3.googleusercontent.com/a/...",
    "is_phone_verified": true,
    "is_profile_complete": true,
    "is_fee_paid": false
  }
}
```

### Success Response: Free Role, `200 OK`

```json
{
  "needs_payment": false,
  "amount": 0,
  "currency": "INR",
  "user": {
    "id": 101,
    "name": "Amit Sharma",
    "email": "amit@example.com",
    "phone": "+919876543210",
    "role": "student",
    "avatar": "https://lh3.googleusercontent.com/a/...",
    "is_phone_verified": true,
    "is_profile_complete": true,
    "is_fee_paid": true
  }
}
```

### Role Fees

| Role | Discounted Price | Currency | Payment Required |
|------|------------------|----------|------------------|
| `student` | `0.00` | `INR` | No |
| `teacher` | `299.00` | `INR` | Yes |
| `institute` | `499.00` | `INR` | Yes |
| `university` | `599.00` | `INR` | Yes |

### Error Responses

| HTTP | Cause |
|------|-------|
| `401` | Missing or invalid Sanctum token |
| `422` | Invalid phone |
| `422` | Phone is already used by another user |
| `422` | Invalid role or missing charge configuration |

---

## 5. Passkey Register Options

**Endpoint:** `POST /api/auth/passkey/register/options`  
**Auth:** Required  
**Middleware:** `auth:sanctum`  
**Rate limit:** 20 attempts per minute per user  
**Challenge TTL:** 300 seconds

Creates a WebAuthn registration challenge for the authenticated user.

### Request Body

No body is required.

```json
{}
```

### Success Response: `200 OK`

```json
{
  "challenge_id": "22c96f23-66d6-4c61-b388-11f85d012c92",
  "publicKey": {
    "rp": {
      "name": "SuGanta",
      "id": "suganta.com"
    },
    "user": {
      "id": "MTAx",
      "name": "amit@example.com",
      "displayName": "Amit Sharma"
    },
    "challenge": "0adffKVAa0X3FjJ1b3eSdfwYVv6h4h-QCw6NMi_w2rM",
    "pubKeyCredParams": [
      {
        "type": "public-key",
        "alg": -7
      }
    ],
    "timeout": 60000,
    "attestation": "none",
    "excludeCredentials": []
  }
}
```

### Client Handling

- Convert `publicKey.challenge`, `publicKey.user.id`, and credential IDs from base64url strings to `ArrayBuffer`.
- Pass the converted `publicKey` object to `navigator.credentials.create()`.
- Send `challenge_id` and the serialized credential to `register/verify`.

### Error Responses

| HTTP | Cause |
|------|-------|
| `401` | Missing or invalid Sanctum token |
| `429` | Too many passkey registration attempts |

---

## 6. Passkey Register Verify

**Endpoint:** `POST /api/auth/passkey/register/verify`  
**Auth:** Required  
**Middleware:** `auth:sanctum`

Verifies the browser/platform attestation response and saves the passkey credential.

### Request Body

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `challenge_id` | uuid | Yes | Value from register options |
| `credential` | object | Yes | Serialized WebAuthn registration credential |
| `device_name` | string | No | Max 120 characters |

```json
{
  "challenge_id": "22c96f23-66d6-4c61-b388-11f85d012c92",
  "device_name": "Windows Hello",
  "credential": {
    "id": "bnHVF1...",
    "rawId": "bnHVF1...",
    "type": "public-key",
    "response": {
      "clientDataJSON": "eyJ0eXBlIjoid2ViYXV0aG4uY3JlYXRlIiwiY2hhbGxlbmdlIjoi...",
      "attestationObject": "o2NmbXRkbm9uZWdhdHRTdG10oG..."
    }
  }
}
```

### Success Response: `201 Created`

```json
{
  "passkey_id": 12,
  "device_name": "Windows Hello"
}
```

### Error Responses

| HTTP | Cause |
|------|-------|
| `401` | Missing or invalid Sanctum token |
| `422` | Challenge expired or invalid |
| `422` | Missing `clientDataJSON` or `attestationObject` |
| `422` | Credential ID mismatch |
| `422` | Invalid origin, RP ID, signature, attestation, or user verification |
| `422` | Duplicate credential ID |

---

## 7. Passkey Login Options

**Endpoint:** `POST /api/auth/passkey/login/options`  
**Auth:** Public  
**Rate limit:** 40 attempts per minute per IP  
**Challenge TTL:** 300 seconds

Creates a WebAuthn login challenge. The optional `identifier` can be an email or phone number. If provided and a user is found, the response contains an allow-list of that user's passkeys. If omitted, the client can use discoverable passkeys.

### Request Body

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `identifier` | string | No | Max 255 characters; email or phone |

Identifier mode:

```json
{
  "identifier": "amit@example.com"
}
```

Discoverable passkey mode:

```json
{}
```

### Success Response: `200 OK`

```json
{
  "challenge_id": "e4c8b3ac-44e6-4a86-8925-4d16861c1aac",
  "publicKey": {
    "challenge": "htj4Wq0yRXg6fm2A3Vn5RZgN6hzm8xGQfmhcP9aD5vU",
    "timeout": 60000,
    "rpId": "suganta.com",
    "userVerification": "required",
    "allowCredentials": [
      {
        "id": "bnHVF1...",
        "type": "public-key",
        "transports": [
          "usb",
          "nfc",
          "ble",
          "hybrid",
          "internal"
        ]
      }
    ]
  }
}
```

In discoverable passkey mode, `allowCredentials` can be empty or omitted.

### Client Handling

- Convert `publicKey.challenge` and every `allowCredentials[].id` from base64url strings to `ArrayBuffer`.
- Pass the converted `publicKey` object to `navigator.credentials.get()`.
- Send `challenge_id` and the serialized credential to `login/verify`.

### Error Responses

| HTTP | Cause |
|------|-------|
| `422` | Invalid identifier |
| `429` | Too many passkey login attempts |

---

## 8. Passkey Login Verify

**Endpoint:** `POST /api/auth/passkey/login/verify`  
**Auth:** Public  
**Rate limit:** 30 attempts per minute per IP

Verifies the WebAuthn assertion response and returns a Sanctum token.

### Request Body

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `challenge_id` | uuid | Yes | Value from login options |
| `credential` | object | Yes | Serialized WebAuthn login credential |
| `device_name` | string | No | Max 120 characters; token device name |

```json
{
  "challenge_id": "e4c8b3ac-44e6-4a86-8925-4d16861c1aac",
  "device_name": "Chrome on Windows",
  "credential": {
    "id": "bnHVF1...",
    "rawId": "bnHVF1...",
    "type": "public-key",
    "response": {
      "clientDataJSON": "eyJ0eXBlIjoid2ViYXV0aG4uZ2V0IiwiY2hhbGxlbmdlIjoi...",
      "authenticatorData": "SZYN5YgOjGh0NBcPZHZgW4_krrmihcElc1L...",
      "signature": "MEUCIQDY...",
      "userHandle": "MTAx"
    }
  }
}
```

### Success Response: `200 OK`

```json
{
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "token_type": "Bearer",
  "needs_onboarding": false,
  "needs_payment": true,
  "user": {
    "id": 101,
    "name": "Amit Sharma",
    "email": "amit@example.com",
    "phone": "+919876543210",
    "role": "teacher"
  }
}
```

### Error Responses

| HTTP | Cause |
|------|-------|
| `403` | User account is inactive |
| `422` | Challenge expired or invalid |
| `422` | Unknown passkey credential |
| `422` | Passkey does not belong to the selected account |
| `422` | User handle mismatch |
| `422` | Missing `clientDataJSON`, `authenticatorData`, or `signature` |
| `422` | Invalid origin, RP ID, signature, user presence, or user verification |
| `429` | Too many passkey login attempts |

---

## 9. Create Registration Payment Order

**Endpoint:** `POST /api/payment/create-order`  
**Auth:** Required  
**Middleware:** `auth:sanctum`, `profile.complete`  
**Rate limit:** 10 attempts per minute per user  
**Lock:** One active create-order operation per user for 30 seconds

Creates or reuses a Cashfree registration fee payment order for the authenticated user's role.

### Profile Requirement

The `profile.complete` middleware requires:

- `is_profile_complete = true`
- `is_phone_verified = true`
- non-empty `phone`
- non-empty `role`

If any requirement is missing, the API returns `403`.

### Request Body

No body is required.

```json
{}
```

### Success Response: Payment Required, `200 OK`

```json
{
  "order_id": "REG_8M1K2Q9PZA",
  "checkout_url": "https://api.suganta.com/api/v1/payment/checkout?order_id=REG_8M1K2Q9PZA",
  "payment_session_id": "session_id",
  "amount": 299,
  "currency": "INR",
  "needs_payment": true,
  "already_paid": false,
  "description": "Teacher Registration Fee",
  "actual_price": 1000
}
```

### Success Response: Already Paid or Free Role, `200 OK`

```json
{
  "already_paid": true,
  "needs_payment": false,
  "amount": 0,
  "currency": "INR"
}
```

For paid roles that were already paid, `amount` is the role registration amount.

### Error Responses

| HTTP | Cause |
|------|-------|
| `401` | Missing or invalid Sanctum token |
| `403` | Profile is not complete |
| `409` | Payment creation already in progress |
| `422` | Unable to create payment order |
| `429` | Too many payment attempts |

---

## 10. Verify Registration Payment

**Endpoint:** `POST /api/payment/verify`  
**Auth:** Required  
**Middleware:** `auth:sanctum`, `profile.complete`  
**Lock:** One active verify operation per order ID for 30 seconds

Checks the authenticated user's Cashfree registration order and marks the user paid if Cashfree reports success.

### Request Body

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `order_id` | string | Yes | Max 120 characters; must belong to the authenticated user |

```json
{
  "order_id": "REG_8M1K2Q9PZA"
}
```

### Success Response: Paid, `200 OK`

```json
{
  "order_id": "REG_8M1K2Q9PZA",
  "status": "success",
  "is_fee_paid": true
}
```

### Success Response: Pending or Failed, `200 OK`

```json
{
  "order_id": "REG_8M1K2Q9PZA",
  "status": "active",
  "is_fee_paid": false
}
```

`status` is derived from Cashfree `order_status` when available, otherwise the local payment status.

### Error Responses

| HTTP | Cause |
|------|-------|
| `401` | Missing or invalid Sanctum token |
| `403` | Profile is not complete |
| `404` | Payment order not found for this user |
| `409` | Payment verification already in progress |
| `422` | Missing or invalid `order_id` |

---

## 11. Final Profile

**Endpoint:** `GET /api/profile`  
**Auth:** Required  
**Middleware:** `auth:sanctum`, `profile.complete`, `payment.complete`

Returns the authenticated user and current onboarding/payment state. This route is available only after profile completion and payment completion. Free roles are automatically marked as paid/not required by `RegistrationFeeService`.

### Success Response: `200 OK`

```json
{
  "user": {
    "id": 101,
    "name": "Amit Sharma",
    "email": "amit@example.com",
    "phone": "+919876543210",
    "role": "teacher",
    "avatar": "https://lh3.googleusercontent.com/a/...",
    "is_phone_verified": true,
    "is_profile_complete": true,
    "is_fee_paid": true
  },
  "needs_onboarding": false,
  "needs_payment": false
}
```

### Error Response: Profile Incomplete, `403 Forbidden`

```json
{
  "message": "Complete phone verification and role selection first.",
  "needs_onboarding": true,
  "needs_payment": false
}
```

### Error Response: Payment Required, `402 Payment Required`

```json
{
  "message": "Registration payment is required.",
  "needs_onboarding": false,
  "needs_payment": true,
  "amount": 299,
  "currency": "INR"
}
```

### Other Error Responses

| HTTP | Cause |
|------|-------|
| `401` | Missing or invalid Sanctum token |
| `403` | Profile is not complete |
| `402` | Registration payment is required |

---

## WebAuthn Serialization Reference

Browsers return `ArrayBuffer` values from `navigator.credentials.create()` and `navigator.credentials.get()`. Convert all binary fields to base64url strings before sending them to the backend.

### Registration Credential Shape

```json
{
  "id": "base64url",
  "rawId": "base64url",
  "type": "public-key",
  "response": {
    "clientDataJSON": "base64url",
    "attestationObject": "base64url"
  }
}
```

### Login Credential Shape

```json
{
  "id": "base64url",
  "rawId": "base64url",
  "type": "public-key",
  "response": {
    "clientDataJSON": "base64url",
    "authenticatorData": "base64url",
    "signature": "base64url",
    "userHandle": "base64url-or-null"
  }
}
```

---

## Quick cURL Examples

### Social Login

```bash
curl -X POST "https://api.suganta.com/api/auth/social-login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "firebase_token": "FIREBASE_ID_TOKEN",
    "device_name": "Postman"
  }'
```

### Send OTP

```bash
curl -X POST "https://api.suganta.com/api/auth/send-otp" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SANCTUM_TOKEN" \
  -d '{
    "phone": "+919876543210"
  }'
```

### Complete Profile

```bash
curl -X POST "https://api.suganta.com/api/auth/complete-profile" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SANCTUM_TOKEN" \
  -d '{
    "phone": "+919876543210",
    "role": "teacher"
  }'
```

### Create Payment Order

```bash
curl -X POST "https://api.suganta.com/api/payment/create-order" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SANCTUM_TOKEN" \
  -d '{}'
```

### Verify Payment

```bash
curl -X POST "https://api.suganta.com/api/payment/verify" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SANCTUM_TOKEN" \
  -d '{
    "order_id": "REG_8M1K2Q9PZA"
  }'
```

### Get Profile

```bash
curl -X GET "https://api.suganta.com/api/profile" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer SANCTUM_TOKEN"
```

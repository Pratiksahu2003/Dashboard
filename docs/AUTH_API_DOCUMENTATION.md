# SuGanta API - Authentication Documentation

## Base URL
```
https://your-domain.com/api/v1/auth
```

## Response Format

All API responses follow a consistent JSON format:

### Success Response
```json
{
    "success": true,
    "message": "Success message",
    "code": 200,
    "data": {
        // Response data
    }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error message",
    "code": 400,
    "errors": {
        // Error details (optional)
    }
}
```

---

## Public Authentication Endpoints

### 1. User Registration

**Endpoint:** `POST /api/v1/auth/register`

**Description:** Register a new user account

**Request Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "password": "SecurePassword123!",
    "password_confirmation": "SecurePassword123!",
    "role": "student",
    "phone": "+1234567890",
    "referral_code": "REF123",
    "device_name": "iPhone 13"
}
```

**Request Parameters:**

| Parameter | Type | Required | Description | Validation Rules |
|-----------|------|----------|-------------|------------------|
| `first_name` | string | Yes | User's first name | max:255 |
| `last_name` | string | Yes | User's last name | max:255 |
| `email` | string | Yes | User's email address | email, max:255, unique |
| `password` | string | Yes | User's password | confirmed, Password::defaults() |
| `password_confirmation` | string | Yes | Password confirmation | must match password |
| `role` | string | Yes | User role | in:student,teacher,institute,ngo |
| `phone` | string | No | Phone number | max:20 |
| `referral_code` | string | No | Referral code | max:20 |
| `device_name` | string | No | Device identifier | max:255 |

**Success Response (201):**
```json
{
    "success": true,
    "message": "User registered successfully",
    "code": 201,
    "data": {
        "user": {
            "id": 1,
            "first_name": "John",
            "last_name": "Doe",
            "email": "john.doe@example.com",
            "role": "student",
            "phone": "+1234567890",
            "email_verified_at": null,
            "registration_fee_status": "pending",
            "created_at": "2026-03-10T10:00:00.000000Z",
            "updated_at": "2026-03-10T10:00:00.000000Z"
        },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "token_type": "Bearer"
    }
}
```

**Error Responses:**

**Validation Error (422):**
```json
{
    "success": false,
    "message": "Validation failed",
    "code": 422,
    "errors": {
        "email": ["The email has already been taken."],
        "password": ["The password confirmation does not match."]
    }
}
```

**Server Error (500):**
```json
{
    "success": false,
    "message": "Registration failed. Please try again.",
    "code": 500
}
```

---

### 2. User Login

**Endpoint:** `POST /api/v1/auth/login`

**Description:** Authenticate user and return access token

**Request Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
    "email": "john.doe@example.com",
    "password": "SecurePassword123!",
    "device_name": "iPhone 13"
}
```

**Request Parameters:**

| Parameter | Type | Required | Description | Validation Rules |
|-----------|------|----------|-------------|------------------|
| `email` | string | Yes | Email or phone number | required, string |
| `password` | string | Yes | User's password | required, string |
| `device_name` | string | No | Device identifier | max:255 |

**Success Response (200):**
```json
{
    "success": true,
    "message": "Login successful",
    "code": 200,
    "data": {
        "user": {
            "id": 1,
            "first_name": "John",
            "last_name": "Doe",
            "email": "john.doe@example.com",
            "role": "student",
            "phone": "+1234567890",
            "email_verified_at": "2026-03-10T10:00:00.000000Z",
            "registration_fee_status": "paid"
        },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "token_type": "Bearer"
    }
}
```

**OTP Required Response (200):**
```json
{
    "success": true,
    "message": "OTP sent to your registered email/phone",
    "code": 200,
    "data": {
        "requires_otp": true,
        "identifier": "john.doe@example.com"
    }
}
```

**Registration Payment Required Response (200):**
```json
{
    "success": false,
    "message": "Registration fee payment is required to complete login.",
    "code": 200,
    "errors": {
        "requires_registration_payment": true,
        "payment_link": "https://www.suganta.in/api/v1/payment/checkout?order_id=REG_XXX",
        "payment_session_id": "session_XXX",
        "order_id": "REG_XXX",
        "actual_price": 1000,
        "discounted_price": 299,
        "description": "Teacher Registration Fee",
        "role": "teacher",
        "message": "Registration fee payment is required to complete login."
    }
}
```

**Payment Gate Return Response (200):**

After the user completes payment via Cashfree, the payment gateway redirects to the configured return URL. The return URL may respond with JSON body instead of query params. The app detects both formats.

**JSON Body Format (Success):**
```json
{
    "message": "Payment status retrieved.",
    "success": true,
    "code": 200,
    "data": {
        "order_id": "REG_HXJNFMZEQO",
        "status": "success",
        "processed_at": "2026-03-11T05:30:12+00:00"
    }
}
```

**JSON Body Format (Failure):**
```json
{
    "success": false,
    "code": 200,
    "data": {
        "order_id": "REG_XXX",
        "status": "failure",
        "processed_at": "2026-03-11T05:30:12+00:00"
    }
}
```

**Error Responses:**

**Invalid Credentials (422):**
```json
{
    "success": false,
    "message": "Invalid credentials",
    "code": 422,
    "errors": {
        "email": ["These credentials do not match our records."]
    }
}
```

**Account Forbidden (403):**
```json
{
    "success": false,
    "message": "Account is suspended or requires verification",
    "code": 403
}
```

---

### 3. Send Login OTP

**Endpoint:** `POST /api/v1/auth/login/send-otp`

**Description:** Send OTP for login verification

**Request Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
    "identifier": "john.doe@example.com"
}
```

**Request Parameters:**

| Parameter | Type | Required | Description | Validation Rules |
|-----------|------|----------|-------------|------------------|
| `identifier` | string | Yes | Email or phone number | required, string |

**Success Response (200):**
```json
{
    "success": true,
    "message": "OTP sent successfully",
    "code": 200,
    "data": {
        "identifier": "john.doe@example.com",
        "expires_in": 300
    }
}
```

**Error Responses:**

**User Not Found (404):**
```json
{
    "success": false,
    "message": "User not found",
    "code": 404
}
```

**Account Forbidden (403):**
```json
{
    "success": false,
    "message": "Account is not eligible for OTP login",
    "code": 403
}
```

**Rate Limited (429):**
```json
{
    "success": false,
    "message": "Too many OTP requests. Please try again later.",
    "code": 429
}
```

---

### 4. Verify Login OTP

**Endpoint:** `POST /api/v1/auth/login/verify`

**Description:** Verify OTP and complete login

**Request Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
    "identifier": "john.doe@example.com",
    "otp": "123456",
    "remember_device": true,
    "device_name": "iPhone 13"
}
```

**Request Parameters:**

| Parameter | Type | Required | Description | Validation Rules |
|-----------|------|----------|-------------|------------------|
| `identifier` | string | Yes | Email or phone number | required, string |
| `otp` | string | Yes | OTP code | required, string |
| `remember_device` | boolean | No | Remember this device | boolean |
| `device_name` | string | No | Device identifier | string |

**Success Response (200):**
```json
{
    "success": true,
    "message": "Login successful",
    "code": 200,
    "data": {
        "user": {
            "id": 1,
            "first_name": "John",
            "last_name": "Doe",
            "email": "john.doe@example.com",
            "role": "student",
            "phone": "+1234567890",
            "email_verified_at": "2026-03-10T10:00:00.000000Z",
            "registration_fee_status": "paid"
        },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "token_type": "Bearer"
    }
}
```

**Registration Payment Required Response (200):**
```json
{
    "success": false,
    "message": "Registration fee payment is required to complete login.",
    "code": 200,
    "errors": {
        "requires_registration_payment": true,
        "payment_link": "https://www.suganta.in/api/v1/payment/checkout?order_id=REG_XXX",
        "actual_price": 1000,
        "discounted_price": 299,
        "description": "Teacher Registration Fee"
    }
}
```

**Error Responses:**

**Invalid OTP (422):**
```json
{
    "success": false,
    "message": "Verification failed",
    "code": 422,
    "errors": {
        "otp": ["Invalid or expired OTP code."]
    }
}
```

**Account Forbidden (403):**
```json
{
    "success": false,
    "message": "Account verification failed",
    "code": 403
}
```

---

### 5. Forgot Password

**Endpoint:** `POST /api/v1/auth/forgot-password`

**Description:** Send password reset link to user's email

**Request Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
    "email": "john.doe@example.com"
}
```

**Request Parameters:**

| Parameter | Type | Required | Description | Validation Rules |
|-----------|------|----------|-------------|------------------|
| `email` | string | Yes | User's email address | required, string, email |

**Success Response (200):**
```json
{
    "success": true,
    "message": "If an account with that email exists, a password reset link has been sent.",
    "code": 200
}
```

**Error Responses:**

**Validation Error (422):**
```json
{
    "success": false,
    "message": "Validation failed",
    "code": 422,
    "errors": {
        "email": ["The email field is required."]
    }
}
```

**Server Error (500):**
```json
{
    "success": false,
    "message": "Password reset request failed. Please try again.",
    "code": 500
}
```

---

### 6. Reset Password

**Endpoint:** `POST /api/v1/auth/reset-password`

**Description:** Reset user password using reset token

**Request Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
    "token": "reset_token_here",
    "email": "john.doe@example.com",
    "password": "NewSecurePassword123!",
    "password_confirmation": "NewSecurePassword123!"
}
```

**Request Parameters:**

| Parameter | Type | Required | Description | Validation Rules |
|-----------|------|----------|-------------|------------------|
| `token` | string | Yes | Password reset token | required, string |
| `email` | string | Yes | User's email address | required, string, email |
| `password` | string | Yes | New password | required, string, confirmed, Password::defaults() |
| `password_confirmation` | string | Yes | Password confirmation | must match password |

**Success Response (200):**
```json
{
    "success": true,
    "message": "Password has been reset successfully. Please login with your new password.",
    "code": 200
}
```

**Error Responses:**

**Invalid Token (400):**
```json
{
    "success": false,
    "message": "Invalid or expired reset token",
    "code": 400
}
```

**Validation Error (422):**
```json
{
    "success": false,
    "message": "Validation failed",
    "code": 422,
    "errors": {
        "password": ["The password confirmation does not match."]
    }
}
```

---

## Protected Authentication Endpoints

**Note:** All protected endpoints require authentication token in the header:
```
Authorization: Bearer {your_access_token}
```

### 7. Logout

**Endpoint:** `POST /api/v1/auth/logout`

**Description:** Logout user and revoke current token

**Request Headers:**
```
Authorization: Bearer {your_access_token}
Content-Type: application/json
Accept: application/json
```

**Request Body:** None required

**Success Response (200):**
```json
{
    "success": true,
    "message": "Logged out successfully",
    "code": 200
}
```

**Error Responses:**

**Unauthorized (401):**
```json
{
    "success": false,
    "message": "Unauthorized",
    "code": 401
}
```

**Server Error (500):**
```json
{
    "success": false,
    "message": "Logout failed",
    "code": 500
}
```

---

### 8. Logout from All Devices

**Endpoint:** `POST /api/v1/auth/logout-all`

**Description:** Logout user from all devices and revoke all tokens

**Request Headers:**
```
Authorization: Bearer {your_access_token}
Content-Type: application/json
Accept: application/json
```

**Request Body:** None required

**Success Response (200):**
```json
{
    "success": true,
    "message": "Logged out from all devices successfully",
    "code": 200
}
```

**Error Responses:**

**Unauthorized (401):**
```json
{
    "success": false,
    "message": "Unauthorized",
    "code": 401
}
```

**Server Error (500):**
```json
{
    "success": false,
    "message": "Logout failed",
    "code": 500
}
```

---

### 9. Refresh Token

**Endpoint:** `POST /api/v1/auth/refresh-token`

**Description:** Refresh user access token

**Request Headers:**
```
Authorization: Bearer {your_access_token}
Content-Type: application/json
Accept: application/json
```

**Request Body:** None required

**Success Response (200):**
```json
{
    "success": true,
    "message": "Token refreshed successfully",
    "code": 200,
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "token_type": "Bearer"
    }
}
```

**Error Responses:**

**Unauthorized (401):**
```json
{
    "success": false,
    "message": "Unauthorized",
    "code": 401
}
```

**Server Error (500):**
```json
{
    "success": false,
    "message": "Token refresh failed",
    "code": 500
}
```

---

## Verification Endpoints

### 10. Resend Verification OTP

**Endpoint:** `POST /api/v1/auth/verification/resend`

**Description:** Resend verification OTP for email or phone

**Request Headers:**
```
Authorization: Bearer {your_access_token}
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
    "type": "email"
}
```

**Request Parameters:**

| Parameter | Type | Required | Description | Validation Rules |
|-----------|------|----------|-------------|------------------|
| `type` | string | Yes | Verification type | required, in:email,phone |

**Success Response (200):**
```json
{
    "success": true,
    "message": "Verification code sent.",
    "code": 200
}
```

**Error Responses:**

**Already Verified (400):**
```json
{
    "success": false,
    "message": "Email already verified.",
    "code": 400
}
```

**Unauthorized (401):**
```json
{
    "success": false,
    "message": "Unauthorized",
    "code": 401
}
```

---

### 11. Verify OTP

**Endpoint:** `POST /api/v1/auth/verification/verify`

**Description:** Verify email or phone OTP

**Request Headers:**
```
Authorization: Bearer {your_access_token}
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
    "email_otp": "123456",
    "phone_otp": "654321"
}
```

**Request Parameters:**

| Parameter | Type | Required | Description | Validation Rules |
|-----------|------|----------|-------------|------------------|
| `email_otp` | string | No | Email OTP code | string |
| `phone_otp` | string | No | Phone OTP code | string |

**Note:** At least one OTP (email_otp or phone_otp) must be provided.

**Success Response (200):**
```json
{
    "success": true,
    "message": "Email verified successfully. Phone verified successfully.",
    "code": 200,
    "data": {
        "user": {
            "id": 1,
            "role": "student",
            "email": "john.doe@example.com",
            "phone": "+1234567890",
            "email_verified_at": "2026-03-10T10:00:00.000000Z",
            "registration_fee_status": "paid",
            "verification_status": "verified",
            "payment_required": false
        }
    }
}
```

**Error Responses:**

**Invalid OTP (400):**
```json
{
    "success": false,
    "message": "Invalid or expired Email OTP. Invalid or expired Phone OTP.",
    "code": 400,
    "errors": {
        "user": {
            "id": 1,
            "role": "student",
            "email": "john.doe@example.com",
            "phone": "+1234567890",
            "email_verified_at": null,
            "registration_fee_status": "paid",
            "verification_status": "pending",
            "payment_required": false
        }
    }
}
```

**Validation Error (422):**
```json
{
    "success": false,
    "message": "Please provide email_otp or phone_otp.",
    "code": 422
}
```

**Unauthorized (401):**
```json
{
    "success": false,
    "message": "Unauthorized",
    "code": 401
}
```

---

## Error Codes Reference

| HTTP Status | Description | When it occurs |
|-------------|-------------|----------------|
| 200 | OK | Successful request |
| 201 | Created | Resource created successfully |
| 400 | Bad Request | Invalid request data or business logic error |
| 401 | Unauthorized | Missing or invalid authentication token |
| 403 | Forbidden | Account suspended, verification required, or insufficient permissions |
| 404 | Not Found | Resource not found (e.g., user not found) |
| 422 | Unprocessable Entity | Validation errors |
| 429 | Too Many Requests | Rate limiting exceeded |
| 500 | Internal Server Error | Server error or unexpected exception |

---

## Authentication Flow Examples

### Standard Login Flow
1. `POST /api/v1/auth/login` with credentials
2. If successful, receive token and user data
3. Use token in `Authorization: Bearer {token}` header for protected endpoints

### OTP Login Flow
1. `POST /api/v1/auth/login` with credentials
2. If OTP required, receive `requires_otp: true` response
3. `POST /api/v1/auth/login/send-otp` with identifier
4. `POST /api/v1/auth/login/verify` with OTP code
5. Receive token and user data upon successful verification

### Registration Flow
1. `POST /api/v1/auth/register` with user details
2. Receive token and user data
3. `POST /api/v1/auth/verification/resend` to send verification OTP
4. `POST /api/v1/auth/verification/verify` to verify email/phone

### Password Reset Flow
1. `POST /api/v1/auth/forgot-password` with email
2. Check email for reset link
3. `POST /api/v1/auth/reset-password` with token and new password

---

## Rate Limiting

- OTP requests are rate limited to prevent abuse
- Failed login attempts may trigger temporary account locks
- Password reset requests are limited per email address

## Security Notes

- All passwords must meet the default Laravel password requirements
- Tokens are issued using Laravel Sanctum
- OTP codes expire after 5 minutes (300 seconds)
- Password reset tokens have limited validity
- Device names are used for token identification and management

## Postman Collection

You can import the following cURL commands into Postman for testing:

### Register
```bash
curl -X POST "https://your-domain.com/api/v1/auth/register" \
-H "Content-Type: application/json" \
-H "Accept: application/json" \
-d '{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "password": "SecurePassword123!",
    "password_confirmation": "SecurePassword123!",
    "role": "student",
    "phone": "+1234567890",
    "device_name": "Test Device"
}'
```

### Login
```bash
curl -X POST "https://your-domain.com/api/v1/auth/login" \
-H "Content-Type: application/json" \
-H "Accept: application/json" \
-d '{
    "email": "john.doe@example.com",
    "password": "SecurePassword123!",
    "device_name": "Test Device"
}'
```

### Logout
```bash
curl -X POST "https://your-domain.com/api/v1/auth/logout" \
-H "Authorization: Bearer YOUR_TOKEN_HERE" \
-H "Content-Type: application/json" \
-H "Accept: application/json"
```
# SuGanta API Documentation

**Base URL:** `https://www.suganta.in/api/v1`

**Authentication:** Protected routes require `Authorization: Bearer {token}` header.

---

## Table of Contents
1. [Support Tickets](#support-tickets)
2. [Payments](#payments)
3. [Portfolios](#portfolios)
4. [Leads](#leads)
5. [Study Requirements](#study-requirements)
6. [Subscriptions](#subscriptions)
7. [Profile](#profile)

---

## Support Tickets

**Prefix:** `/support-tickets`  
**Middleware:** `auth:sanctum`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/support-tickets/options` | Get dropdown options for ticket forms |
| GET | `/support-tickets` | List user's support tickets |
| POST | `/support-tickets` | Create new support ticket |
| GET | `/support-tickets/{supportTicket}` | Get single ticket details |
| PUT | `/support-tickets/{supportTicket}` | Update ticket |
| PATCH | `/support-tickets/{supportTicket}` | Partial update ticket |
| DELETE | `/support-tickets/{supportTicket}` | Delete ticket |
| POST | `/support-tickets/{supportTicket}/reply` | Add reply to ticket |
| GET | `/support-tickets/{supportTicket}/attachment` | Download ticket attachment |
| GET | `/support-tickets/{supportTicket}/replies/{reply}/attachment` | Download reply attachment |

### Get Options
| | |
|---|---|
| **Endpoint** | `GET /support-tickets/options` |
| **Headers** | `Authorization: Bearer {token}` |

**Success (200):**
```json
{
  "success": true,
  "data": {
    "priorities": ["low", "medium", "high"],
    "categories": ["technical", "billing", "general"],
    "statuses": ["open", "in_progress", "resolved", "closed"]
  }
}
```

### List Tickets
| | |
|---|---|
| **Endpoint** | `GET /support-tickets` |
| **Query Params** | |

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| page | integer | No | Pagination page (default: 1) |
| per_page | integer | No | Items per page (default: 15) |
| status | string | No | Filter by status |
| category | string | No | Filter by category |

**Success (200):**
```json
{
  "success": true,
  "data": {
    "tickets": [...],
    "meta": { "current_page": 1, "per_page": 15, "total": 10 }
  }
}
```

### Create Ticket
| | |
|---|---|
| **Endpoint** | `POST /support-tickets` |
| **Headers** | `Authorization: Bearer {token}`, `Content-Type: application/json` |

**Request Body:**
```json
{
  "subject": "Unable to access course",
  "message": "I cannot view the purchased course content.",
  "category": "technical",
  "priority": "high",
  "attachment": "base64_or_file"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| subject | string | Yes | Max 255 chars |
| message | string | Yes | Max 5000 chars |
| category | string | No | technical, billing, general |
| priority | string | No | low, medium, high |
| attachment | string/file | No | Optional file attachment |

### Reply to Ticket
| | |
|---|---|
| **Endpoint** | `POST /support-tickets/{supportTicket}/reply` |

**Request Body:**
```json
{
  "message": "Here are more details about the issue.",
  "attachment": "base64_or_file"
}
```

---

## Payments

**Prefix:** `/payments`  
**Middleware:** `auth:sanctum`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/payments` | List user's payments |
| GET | `/payments/invoice/{orderId}` | Get invoice for order |

### List Payments
| | |
|---|---|
| **Endpoint** | `GET /payments` |

| Query Param | Type | Required | Description |
|-------------|------|----------|-------------|
| page | integer | No | Pagination page |
| per_page | integer | No | Items per page |
| from_date | date | No | Filter from date (YYYY-MM-DD) |
| to_date | date | No | Filter to date |
| status | string | No | Filter by status |

**Success (200):**
```json
{
  "success": true,
  "data": {
    "payments": [
      {
        "id": 1,
        "order_id": "REG_XXX",
        "amount": 299,
        "status": "success",
        "created_at": "2026-03-11T10:00:00.000000Z"
      }
    ],
    "meta": {}
  }
}
```

### Get Invoice
| | |
|---|---|
| **Endpoint** | `GET /payments/invoice/{orderId}` |
| **Path Param** | `orderId` - Order ID (e.g. REG_XXX) |

**Success (200):** Returns invoice PDF or JSON invoice data.

---

## Portfolios

**Prefix:** `/portfolios`  
**Middleware:** `auth:sanctum`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/portfolios/options` | Get portfolio form options |
| GET | `/portfolios` | Get user's portfolio |
| POST | `/portfolios` | Create portfolio |
| PUT | `/portfolios/{portfolio}` | Update portfolio |
| PATCH | `/portfolios/{portfolio}` | Partial update portfolio |

### Get Options
| | |
|---|---|
| **Endpoint** | `GET /portfolios/options` |

**Success (200):**
```json
{
  "success": true,
  "data": {
    "subjects": [],
    "grades": [],
    "experience_years": []
  }
}
```

### Get Portfolio
| | |
|---|---|
| **Endpoint** | `GET /portfolios` |

**Success (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 1,
    "bio": "...",
    "subjects": [],
    "experience_years": 5,
    "created_at": "...",
    "updated_at": "..."
  }
}
```

### Create/Update Portfolio
| | |
|---|---|
| **Endpoint** | `POST /portfolios` or `PUT/PATCH /portfolios/{portfolio}` |

**Request Body:**
```json
{
  "bio": "Experienced tutor in mathematics and physics.",
  "subjects": ["mathematics", "physics"],
  "experience_years": 5,
  "qualifications": "M.Sc. Mathematics",
  "teaching_style": "Interactive"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| bio | string | No | Max 2000 chars |
| subjects | array | No | Array of subject IDs or names |
| experience_years | integer | No | Years of experience |
| qualifications | string | No | Max 500 chars |
| teaching_style | string | No | Max 500 chars |

---

## Leads

**Prefix:** `/leads`  
**Middleware:** `auth:sanctum`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/leads` | List user's leads |
| POST | `/leads` | Create new lead |
| GET | `/leads/{lead}` | Get single lead details |

### List Leads
| | |
|---|---|
| **Endpoint** | `GET /leads` |

| Query Param | Type | Required | Description |
|-------------|------|----------|-------------|
| page | integer | No | Pagination page |
| per_page | integer | No | Items per page |
| status | string | No | Filter by status |
| source | string | No | Filter by source |

**Success (200):**
```json
{
  "success": true,
  "data": {
    "leads": [
      {
        "id": 1,
        "name": "Student Name",
        "email": "student@example.com",
        "phone": "+91...",
        "status": "new",
        "source": "website",
        "created_at": "..."
      }
    ],
    "meta": {}
  }
}
```

### Create Lead
| | |
|---|---|
| **Endpoint** | `POST /leads` |

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+919876543210",
  "subject": "Math tutoring",
  "message": "Interested in weekend classes."
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | string | Yes | Max 255 chars |
| email | string | Yes | Valid email |
| phone | string | No | Max 20 chars |
| subject | string | No | Max 255 chars |
| message | string | No | Max 2000 chars |
| source | string | No | lead source identifier |

---

## Study Requirements

**Prefix:** `/study-requirements`  
**Middleware:** `auth:sanctum`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/study-requirements` | List study requirements (paginated) |

### List Study Requirements
| | |
|---|---|
| **Endpoint** | `GET /study-requirements` |

| Query Param | Type | Required | Description |
|-------------|------|----------|-------------|
| page | integer | No | Pagination page |
| per_page | integer | No | Items per page |
| subject | string | No | Filter by subject |
| grade | string | No | Filter by grade |
| status | string | No | Filter by status |

**Success (200):**
```json
{
  "success": true,
  "data": {
    "study_requirements": [
      {
        "id": 1,
        "subject": "mathematics",
        "grade": "10",
        "description": "Need help with algebra",
        "status": "open",
        "created_at": "..."
      }
    ],
    "meta": {}
  }
}
```

---

## Subscriptions

**Prefix:** `/subscriptions`

### Public Routes (No Auth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/subscriptions/plans` | List all subscription plans |
| GET | `/subscriptions/plans/{plan}` | Get single plan details |

### Get Plans
| | |
|---|---|
| **Endpoint** | `GET /subscriptions/plans` |
| **Access** | Public |

**Success (200):**
```json
{
  "success": true,
  "data": {
    "plans": [
      {
        "id": 1,
        "name": "Basic",
        "price": 299,
        "duration_days": 30,
        "features": []
      }
    ]
  }
}
```

### Protected Routes (Auth Required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/subscriptions/my-subscriptions` | User's subscriptions |
| GET | `/subscriptions/current` | Current active subscription |
| POST | `/subscriptions/purchase` | Purchase subscription |
| PATCH | `/subscriptions/{subscription}/cancel` | Cancel subscription |
| POST | `/subscriptions/{subscription}/renew` | Renew subscription |

### Purchase
| | |
|---|---|
| **Endpoint** | `POST /subscriptions/purchase` |
| **Headers** | `Authorization: Bearer {token}` |

**Request Body:**
```json
{
  "plan_id": 1,
  "payment_method": "card"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| plan_id | integer | Yes | Plan ID |
| payment_method | string | No | card, upi, netbanking |
| coupon_code | string | No | Discount coupon |

### Cancel Subscription
| | |
|---|---|
| **Endpoint** | `PATCH /subscriptions/{subscription}/cancel` |

**Request Body:**
```json
{
  "reason": "No longer needed",
  "feedback": "Optional feedback"
}
```

---

## Profile

**Prefix:** `/profile`  
**Middleware:** `auth:sanctum`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/profile` | Get user profile |
| PUT | `/profile` | Update profile |
| PATCH | `/profile` | Partial update profile |
| PUT | `/profile/location` | Update location |
| PUT | `/profile/social` | Update social links |
| PUT | `/profile/teaching` | Update teaching preferences |
| PUT | `/profile/institute` | Update institute profile |
| PUT | `/profile/student` | Update student profile |
| PUT | `/profile/avatar` | Update avatar image |
| POST | `/profile/avatar` | Update avatar (alternative) |
| PUT | `/profile/password` | Change password |
| PATCH | `/profile/password` | Change password |
| PUT | `/profile/preferences` | Update preferences |
| PATCH | `/profile/preferences` | Update preferences |
| GET | `/profile/completion` | Get profile completion % |
| POST | `/profile/refresh` | Refresh profile data |
| POST | `/profile/cache/clear` | Clear profile cache |
| DELETE | `/profile` | Delete account |

### Get Profile
| | |
|---|---|
| **Endpoint** | `GET /profile` |

**Success (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "phone": "+919876543210",
    "role": "teacher",
    "avatar_url": "...",
    "location": {},
    "social": {},
    "teaching": {},
    "created_at": "...",
    "updated_at": "..."
  }
}
```

### Update Profile
| | |
|---|---|
| **Endpoint** | `PUT /profile` or `PATCH /profile` |

**Request Body:**
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "phone": "+919876543210",
  "date_of_birth": "1990-01-15"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| first_name | string | No | Max 255 |
| last_name | string | No | Max 255 |
| phone | string | No | Max 20 |
| date_of_birth | date | No | YYYY-MM-DD |
| bio | string | No | Max 2000 |

### Update Location
| | |
|---|---|
| **Endpoint** | `PUT /profile/location` or `PATCH /profile/location` |

**Request Body:**
```json
{
  "country": "India",
  "state": "Karnataka",
  "city": "Bangalore",
  "pincode": "560001",
  "address": "Street address"
}
```

### Update Password
| | |
|---|---|
| **Endpoint** | `PUT /profile/password` or `PATCH /profile/password` |

**Request Body:**
```json
{
  "current_password": "oldPassword123!",
  "password": "newPassword123!",
  "password_confirmation": "newPassword123!"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| current_password | string | Yes | Current password |
| password | string | Yes | New password |
| password_confirmation | string | Yes | Must match password |

### Update Avatar
| | |
|---|---|
| **Endpoint** | `PUT /profile/avatar` or `POST /profile/avatar` |
| **Content-Type** | `multipart/form-data` or `application/json` |

**Request Body (multipart):**
- `avatar`: File (image/jpeg, image/png, max 2MB)

**Request Body (base64):**
```json
{
  "avatar": "data:image/jpeg;base64,/9j/4AAQ..."
}
```

### Profile Completion
| | |
|---|---|
| **Endpoint** | `GET /profile/completion` |

**Success (200):**
```json
{
  "success": true,
  "data": {
    "percentage": 75,
    "missing_fields": ["bio", "avatar"],
    "is_complete": false
  }
}
```

### Delete Account
| | |
|---|---|
| **Endpoint** | `DELETE /profile` |

**Request Body (optional):**
```json
{
  "password": "current_password",
  "reason": "Optional feedback"
}
```

---

## Common Response Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized (missing or invalid token) |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Server Error |

---

## Authentication Header

All protected routes require:
```
Authorization: Bearer {your_access_token}
Accept: application/json
Content-Type: application/json
```

---

## Example: Create Support Ticket

```bash
curl -X POST https://www.suganta.in/api/v1/support-tickets \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "subject": "Payment issue",
    "message": "I was charged twice for my subscription.",
    "category": "billing",
    "priority": "high"
  }'
```

---

## Example: Get Payments

```bash
curl -X GET "https://www.suganta.in/api/v1/payments?page=1&per_page=10" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

---

## Example: Update Profile

```bash
curl -X PUT https://www.suganta.in/api/v1/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "John",
    "last_name": "Doe",
    "phone": "+919876543210"
  }'
```

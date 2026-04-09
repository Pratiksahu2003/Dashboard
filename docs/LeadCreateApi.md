# Create Lead API

**Endpoint**: `POST /api/v1/leads`  
**Controller**: `LeadController@store`  
**Auth**: Required (Bearer token, Sanctum)

Creates a new lead. The authenticated user's ID is stored as `user_id` (creator). A unique `lead_id` (e.g. `SUG-20250311-000123`) is auto-generated.

---

## Request

| | |
|---|---|
| **Method** | POST |
| **URL** | `/api/v1/leads` |
| **Content-Type** | `application/json` |
| **Authorization** | `Bearer {access_token}` |

---

## Parameters

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| name | string | **Yes** | max:255 | Lead name |
| phone | string | **Yes** | max:30 | Lead phone number |
| lead_owner_id | integer | **Yes** | exists:users,id | User ID of the lead owner (teacher) |
| email | string | No | email, max:255 | Lead email |
| type | string | No | student, parent, institute, teacher | Lead type |
| source | string | No | website, social_media, referral, advertisement, direct | Lead source |
| subject_interest | string | No | max:255 | Subject of interest |
| grade_level | string | No | max:100 | Grade or class level |
| location | string | No | max:255 | Location |
| message | string | No | max:5000 | Additional message |
| status | string | No | new, contacted, qualified, converted, closed | Default: `new` |
| priority | string | No | low, medium, high, urgent | Lead priority |
| assigned_to | integer | No | exists:users,id | User ID to assign the lead to |
| estimated_value | number | No | min:0 | Estimated value (decimal) |
| utm_source | string | No | max:255 | UTM source for tracking |
| utm_medium | string | No | max:255 | UTM medium for tracking |
| utm_campaign | string | No | max:255 | UTM campaign for tracking |

---

## Success Response (201)

```json
{
  "message": "Lead created successfully.",
  "success": true,
  "code": 201,
  "data": {
    "id": 1,
    "lead_id": "SUG-20250311-000123",
    "user_id": 5,
    "lead_owner_id": 3,
    "assigned_to": 7,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+911234567890",
    "type": "student",
    "source": "website",
    "subject_interest": "Mathematics",
    "grade_level": "Class 10",
    "location": "Mumbai, India",
    "message": "Interested in online tutoring.",
    "status": "new",
    "priority": "high",
    "estimated_value": "5000.00",
    "utm_source": null,
    "utm_medium": null,
    "utm_campaign": null,
    "last_contacted_at": null,
    "next_follow_up_at": null,
    "contact_history": null,
    "notes": null,
    "created_at": "2025-03-11T10:00:00.000000Z",
    "updated_at": "2025-03-11T10:00:00.000000Z",
    "user": {
      "id": 5,
      "name": "Jane Smith",
      "email": "jane@example.com"
    },
    "lead_owner": {
      "id": 3,
      "name": "Teacher One",
      "email": "teacher@example.com"
    },
    "assigned_to": {
      "id": 7,
      "name": "Sales Rep",
      "email": "sales@example.com"
    }
  }
}
```

---

## Error Responses

### 401 Unauthorized

```json
{
  "message": "Unauthenticated."
}
```

### 422 Validation Error

```json
{
  "message": "Validation failed.",
  "success": false,
  "code": 422,
  "errors": {
    "name": ["The name field is required."],
    "phone": ["The phone field is required."],
    "lead_owner_id": ["The selected lead owner id is invalid."],
    "type": ["The selected type is invalid."]
  }
}
```

---

## Example: cURL

```bash
curl -X POST "https://api.example.com/api/v1/leads" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "phone": "+911234567890",
    "email": "john@example.com",
    "lead_owner_id": 3,
    "type": "student",
    "source": "website",
    "subject_interest": "Mathematics",
    "grade_level": "Class 10",
    "location": "Mumbai, India",
    "message": "Interested in online tutoring."
  }'
```

---

## Example: JavaScript (Fetch)

```javascript
const response = await fetch('/api/v1/leads', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    name: 'John Doe',
    phone: '+911234567890',
    email: 'john@example.com',
    lead_owner_id: 3,
    type: 'student',
    source: 'website',
    subject_interest: 'Mathematics',
    grade_level: 'Class 10',
    message: 'Interested in online tutoring.',
  }),
});
```

# Notes, Subscription, and Payment API

Clean production guide for Notes purchase, Subscription purchase, and Cashfree checkout flow.

## Production Base Domain

- API base: `https://www.suganta.in/api`
- Notes API: `https://www.suganta.in/api/v2/notes`
- Subscriptions API: `https://www.suganta.in/api/v1/subscriptions`
- Payments API: `https://www.suganta.in/api/v1/payments`
- Checkout routes: `https://www.suganta.in/api/v1/payment/*`

---

## 1) Authentication

Use Bearer token for protected endpoints.

```http
Authorization: Bearer {sanctum_token}
Accept: application/json
Content-Type: application/json
```

Token source:
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/register`

---

## 2) Standard Response Shape

Success:

```json
{
  "message": "Operation completed successfully.",
  "success": true,
  "code": 200,
  "data": {}
}
```

Error:

```json
{
  "message": "Validation failed.",
  "success": false,
  "code": 422,
  "errors": {
    "field": ["Error message"]
  }
}
```

---

## 3) API Modules at a Glance

| Module | Route Prefix | Auth |
|---|---|---|
| Notes | `/api/v2/notes` | Required |
| Subscriptions | `/api/v1/subscriptions` | Plans: Public, others: Required |
| Payments | `/api/v1/payments` | Required |
| Checkout + Callback | `/api/v1/payment/*` | Public |

---

## 4) Notes API (V2)

Base: `https://www.suganta.in/api/v2/notes`

### Important Endpoints

- `GET /api/v2/notes` - list notes
- `GET /api/v2/notes/{id}` - single note
- `GET /api/v2/notes/categories` - categories
- `GET /api/v2/notes/types` - note types
- `GET /api/v2/notes/{id}/check-access` - access state
- `POST /api/v2/notes/purchase` - create note purchase/payment
- `GET /api/v2/notes/{id}/download` - secure download
- `GET /api/v2/notes/my-purchases` - user purchase history

### Purchase Cases (`POST /notes/purchase`)

1. **Free note**
   - `payment_required = false`
   - unlock immediately

2. **Already purchased**
   - `status = "already_paid"`
   - unlock immediately

3. **Paid note**
   - returns `payment.order_id`, `checkout_url`, `payment_session_id`
   - open checkout and track status

---

## 5) Subscriptions API (V1)

Base: `https://www.suganta.in/api/v1/subscriptions`

### Important Endpoints

- `GET /api/v1/subscriptions/plans?s_type=1` - list plans
- `POST /api/v1/subscriptions/purchase` - create payment for plan
- `GET /api/v1/subscriptions/current?s_type=1` - active subscription
- `GET /api/v1/subscriptions/my-subscriptions?s_type=1` - subscription history

### Notes Access Type

- `s_type=1` is the notes/study-material access type.
- If this subscription is active, user can access paid notes without individual purchase.

---

## 6) Payments API (V1)

### Protected

- `GET /api/v1/payments` - payment history
- `GET /api/v1/payments/status?order_id={ORDER_ID}` - poll order status

Status values:
- `created`
- `pending`
- `success`
- `failed`
- `cancelled`
- `refunded`

### Public

- `GET /api/v1/payment/checkout?order_id={ORDER_ID}` - Cashfree hosted checkout
- `GET /api/v1/payment/callback?order_id={ORDER_ID}` - callback result

---

## 7) Proper Workflow (Recommended)

### A) Note Purchase Workflow

1. User clicks `Buy Note`
2. Call `POST /api/v2/notes/purchase`
3. Handle response:
   - free: unlock now
   - already paid: unlock now
   - paid: open `checkout_url`
4. Start polling `GET /api/v1/payments/status?order_id=...` every 2-3s
5. On `success`:
   - refresh note access (`check-access` or reload note list)
   - enable `Download`
6. On `failed/cancelled`:
   - show failure state and retry CTA

### B) Subscription Purchase Workflow

1. User clicks `Purchase Plan`
2. Call `POST /api/v1/subscriptions/purchase`
3. Open `checkout_url`
4. Poll payment status by `order_id`
5. On `success`:
   - reload `GET /subscriptions/current?s_type=1`
   - refresh notes listing to unlock premium notes

---

## 8) Beautiful UI Flow (Frontend Guidelines)

Use these UI states to keep flow clear and premium:

### Notes Listing Card

- Show `Free` / `Paid` badge
- Show access badge:
  - `Purchased`
  - `Subscription Access`
  - `Locked`
- Primary CTA logic:
  - free or unlocked -> `Download`
  - locked paid -> `Buy Now`

### Purchase Modal / Drawer

- Show note title, price, and trust text ("Secure payment via Cashfree")
- Confirm button: `Proceed to Pay`
- On click: disable button + show inline spinner

### Payment Progress State

- "Redirecting to secure checkout..."
- "Waiting for payment confirmation..."
- show current status badge (`pending`, `success`, `failed`)

### Success State

- success toast
- close modal
- refresh relevant blocks:
  - notes list
  - note detail access
  - payment history

### Error State

- inline error + toast
- retry button for transient failures
- human-friendly message for common issues

---

## 9) Frontend Integration Example (Vue/React Style)

```javascript
async function startNotePurchase(noteId, token) {
  const purchaseRes = await fetch('https://www.suganta.in/api/v2/notes/purchase', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json',
      Accept: 'application/json'
    },
    body: JSON.stringify({ note_id: noteId })
  });

  const payload = await purchaseRes.json();
  const data = payload?.data || {};

  if (data.payment_required === false || data.status === 'already_paid') {
    return { state: 'unlocked', orderId: null };
  }

  const orderId = data?.payment?.order_id;
  const checkoutUrl = data?.checkout_url;
  if (!orderId || !checkoutUrl) throw new Error('Invalid payment response');

  window.open(checkoutUrl, '_blank', 'noopener,noreferrer');

  return { state: 'pending', orderId };
}

async function pollPaymentStatus(orderId, token, onUpdate) {
  const terminalStates = new Set(['success', 'failed', 'cancelled', 'refunded']);

  while (true) {
    const res = await fetch(`https://www.suganta.in/api/v1/payments/status?order_id=${encodeURIComponent(orderId)}`, {
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' }
    });
    const body = await res.json();
    const status = body?.data?.status || 'pending';

    onUpdate?.(status);
    if (terminalStates.has(status)) return status;

    await new Promise(resolve => setTimeout(resolve, 2500));
  }
}
```

---

## 10) Access Rules (Source of Truth)

User can download a note if **any** condition is true:

1. note is free (`is_paid=false`)
2. note is individually purchased
3. active subscription exists with `s_type=1`

Otherwise, note remains locked.

---

## 11) Common Errors and Correct UI Messaging

| Condition | API Result | UI Message |
|---|---|---|
| Missing `order_id` | 400 | "Order ID missing. Please retry." |
| Payment not found | 404 | "Payment not found. Please contact support if amount was deducted." |
| Note inactive/not found | 404 | "This note is currently unavailable." |
| Not authorized | 401 | "Session expired. Please login again." |
| Access denied for download | 403 | "Purchase this note or activate subscription to download." |
| Payment provider not configured | 400 | "Payment service is temporarily unavailable." |
| Email not verified | 400 | "Verify your email before making a purchase." |

---

## 12) Quick Endpoint Reference

| Action | Method | Endpoint |
|---|---|---|
| List notes | `GET` | `/api/v2/notes` |
| Note details | `GET` | `/api/v2/notes/{id}` |
| Check note access | `GET` | `/api/v2/notes/{id}/check-access` |
| Purchase note | `POST` | `/api/v2/notes/purchase` |
| Download note | `GET` | `/api/v2/notes/{id}/download` |
| My note purchases | `GET` | `/api/v2/notes/my-purchases` |
| List plans | `GET` | `/api/v1/subscriptions/plans?s_type=1` |
| Purchase plan | `POST` | `/api/v1/subscriptions/purchase` |
| Current subscription | `GET` | `/api/v1/subscriptions/current?s_type=1` |
| Payment status | `GET` | `/api/v1/payments/status?order_id=...` |
| Payment history | `GET` | `/api/v1/payments` |
| Checkout | `GET` | `/api/v1/payment/checkout?order_id=...` |
| Callback | `GET` | `/api/v1/payment/callback?order_id=...` |

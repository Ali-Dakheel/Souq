# Payments Module — Implementation Spec
> Tap Payments API v2 · Bahrain Ecommerce · Phase 2

---

## 1. Scope

This module covers:
- Creating a Tap charge and redirecting user to Tap's hosted payment page (redirect flow, `src_all`)
- Handling the post-payment redirect back to the site
- Processing Tap webhooks (server-to-server, source of truth)
- Storing all payment attempts with full audit trail
- Customer-facing refund requests
- Admin refund approval → Tap API refund call
- Scheduled job to resolve stale `initiated` orders
- Filament admin panel for payment management

**Explicitly out of scope for this phase:**
- BenefitPay QR (requires domain registration with Tap support — added later)
- Saved cards (requires Phase 3 save_card + payment_agreement flow)
- Embedded card form (redirect flow only for MVP)
- Customer self-service refunds without admin approval

---

## 2. API Reference (from official Tap docs)

> All implementation must follow https://developers.tap.company/reference/api-endpoint

### 2.1 Create Charge
`POST https://api.tap.company/v2/charges/`

**Required fields used in this integration:**
```json
{
  "amount": "10.500",
  "currency": "BHD",
  "threeDSecure": true,
  "save_card": false,
  "description": "Order #<order_number>",
  "metadata": {
    "order_id": "<uuid>",
    "customer_id": "<uuid or null>",
    "attempt_number": 1,
    "environment": "production"
  },
  "reference": {
    "transaction": "<order_number>",
    "order": "<order_id>"
  },
  "receipt": {
    "email": true,
    "sms": false
  },
  "customer": {
    "first_name": "...",
    "last_name": "...",
    "email": "...",
    "phone": { "country_code": "973", "number": "..." }
  },
  "source": { "id": "src_all" },
  "redirect": { "url": "https://<domain>/en/checkout/result" }
}
```

**Amount format:** BHD stored as integer fils → divide by 1000, format to 3 decimal places.
`number_format($order->total_fils / 1000, 3, '.', '')` → `"10.500"`

**Key response fields:**
- `id` — tap_charge_id (store immediately)
- `status` — charge status (INITIATED, CAPTURED, FAILED, VOID, etc.)
- `transaction.url` — URL to redirect user to for payment
- Full response stored as JSONB

> **TBD:** Confirm full list of charge status values from Tap dashboard docs before implementation.

### 2.2 Retrieve Charge
`GET https://api.tap.company/v2/charges/{charge_id}`

Used by:
1. Post-payment redirect handler (verify what came back)
2. Stale order job (check real status of stuck `initiated` orders)
3. Admin "sync status" action in Filament

### 2.3 Create Refund
`POST https://api.tap.company/v2/refunds/`

```json
{
  "charge_id": "chg_xxx",
  "amount": 5.500,
  "currency": "BHD",
  "reason": "requested_by_customer",
  "metadata": { "refund_id": "<uuid>", "order_id": "<uuid>" }
}
```

**Reason values (from docs):** `duplicate` | `fraudulent` | `requested_by_customer`

**Amount:** Partial refunds supported. Amount must be ≤ original charge amount.

> **Note:** Docs state amount precision for BHD is up to 3 decimal places.

### 2.4 Authentication
`Authorization: Bearer <TAP_SECRET_KEY>`

### 2.5 Webhook Verification
> **Setup required:** Obtain webhook secret from Tap Dashboard → Developer → Webhooks.

Hash verification uses HMAC-SHA256. The exact fields and concatenation order for the `hashstring` must be confirmed from Tap dashboard webhook documentation before implementation. Set `TAP_WEBHOOK_SECRET` in `.env`.

General verification pattern:
```php
$computed = hash_hmac('sha256', $fieldsString, config('services.tap.webhook_secret'));
if (!hash_equals($computed, $request->header('hashstring'))) {
    abort(401);
}
```

> **TBD:** Get exact field list and concatenation format from Tap webhook docs / dashboard.

---

## 3. Database Schema

### 3.1 `payments` table
```
id                uuid PK
order_id          uuid FK orders.id NOT NULL
tap_charge_id     string UNIQUE NOT NULL
tap_customer_id   string NULLABLE           -- Tap's customer object ID
amount_fils       integer NOT NULL
currency          string(3) DEFAULT 'BHD'
status            enum(initiated, captured, failed, void, refunded) NOT NULL DEFAULT initiated
tap_status        string NULLABLE           -- raw status string from Tap
attempt_number    integer NOT NULL DEFAULT 1
tap_response      jsonb NULLABLE            -- full Tap API response
redirect_url      string NULLABLE           -- transaction.url from charge response
created_at        timestamp
updated_at        timestamp

INDEX: order_id
INDEX: status
```

### 3.2 `refunds` table
```
id                uuid PK
payment_id        uuid FK payments.id NOT NULL
order_id          uuid FK orders.id NOT NULL  -- denormalized for easy querying
tap_refund_id     string UNIQUE NULLABLE       -- set after Tap call succeeds
amount_fils       integer NOT NULL
currency          string(3) DEFAULT 'BHD'
reason            enum(duplicate, fraudulent, requested_by_customer) NOT NULL
status            enum(pending, approved, rejected, processing, refunded, failed) NOT NULL DEFAULT pending
requested_by      uuid FK users.id NOT NULL   -- customer
approved_by       uuid FK users.id NULLABLE   -- admin
customer_notes    text NULLABLE
admin_notes       text NULLABLE
tap_response      jsonb NULLABLE              -- full Tap refund response
created_at        timestamp
updated_at        timestamp

INDEX: payment_id
INDEX: order_id
INDEX: status
```

### 3.3 Column added to `users` table
```
tap_customer_id   string NULLABLE    -- Tap customer object ID (created on first charge)
INDEX: tap_customer_id
```

### 3.4 Migration rules (zero-downtime)
- All new tables: safe
- Adding `tap_customer_id` to `users`: nullable column, no default needed, safe
- All FKs indexed

---

## 4. Backend Architecture

### 4.1 Module structure
```
app/Modules/Payments/
├── Models/
│   ├── Payment.php
│   └── Refund.php
├── Services/
│   ├── TapApiService.php        -- raw HTTP calls to Tap (create charge, get charge, create refund)
│   ├── PaymentService.php       -- orchestration: create, handle result, idempotent handler
│   └── RefundService.php        -- refund request, approval, Tap call
├── Http/
│   ├── Controllers/
│   │   ├── PaymentController.php    -- POST /payments/charge, GET /payments/status
│   │   └── WebhookController.php    -- POST /webhooks/tap
│   ├── Requests/
│   │   ├── CreateChargeRequest.php
│   │   └── RefundRequestRequest.php
│   └── Resources/
│       ├── PaymentResource.php
│       └── RefundResource.php
├── Jobs/
│   └── CheckStalePaymentsJob.php
├── Events/
│   ├── PaymentCaptured.php          -- already in event map
│   ├── PaymentFailed.php            -- already in event map
│   ├── PaymentRefunded.php          -- maps to OrderRefunded in event map
│   ├── RefundRequested.php          -- new: notifies admin
│   └── RefundApproved.php           -- new: notifies customer
├── Listeners/
│   └── (none — Payments fires events, other modules listen)
├── Filament/
│   └── Resources/
│       ├── PaymentResource.php
│       └── RefundResource.php
└── PaymentsServiceProvider.php
```

### 4.2 TapApiService
Thin HTTP client only. No business logic.

```php
createCharge(array $payload): array        // POST /v2/charges/
retrieveCharge(string $chargeId): array    // GET /v2/charges/{id}
createRefund(array $payload): array        // POST /v2/refunds/
createCustomer(array $payload): array      // POST /v2/customers/ — for registered users
```

- Uses Laravel HTTP client with `timeout(15)` and `retry(1, 500)`
- On 5xx or timeout: throws `TapApiException` (caught upstream, fail fast)
- On 4xx: throws `TapApiException` with error code + description from response body
- Never logs request payloads (may contain card data). Log only charge_id + status.

### 4.3 PaymentService

**`initiatePayment(Order $order, User|null $user): Payment`**
1. If `$user` is set and `$user->tap_customer_id` is null → call `TapApiService::createCustomer()`, save to user
2. Build charge payload (amount, customer inline or by tap_customer_id, metadata, redirect URL)
3. Call `TapApiService::createCharge()`
4. On success: create `Payment` record with status `initiated`, store `tap_response`
5. Return Payment (controller redirects user to `tap_response['transaction']['url']`)
6. On `TapApiException`: do NOT create Payment record, re-throw (controller returns 422 with user-friendly message)

**`handleChargeResult(string $tapChargeId): Payment`** ← the idempotent handler
Called by BOTH redirect handler and webhook controller.

```php
// Pessimistic lock on payment row by tap_charge_id
$payment = Payment::where('tap_charge_id', $tapChargeId)
    ->lockForUpdate()
    ->firstOrFail();

if (in_array($payment->status, ['captured', 'refunded'])) {
    return $payment; // already processed, skip
}

$tapCharge = $this->tapApi->retrieveCharge($tapChargeId);
$payment->tap_response = $tapCharge;
$payment->tap_status   = $tapCharge['status'];

match ($tapCharge['status']) {
    'CAPTURED' => $this->handleCaptured($payment),
    'FAILED'   => $this->handleFailed($payment),
    'VOID'     => $this->handleVoid($payment),
    default    => null, // still in progress, no state change
};

$payment->save();
return $payment;
```

**`handleCaptured(Payment $payment)`**
- Set `payment->status = captured`
- Fire `PaymentCaptured` event (Orders module marks order paid, Notifications sends receipt)

**`handleFailed(Payment $payment)`**
- Set `payment->status = failed`
- Fire `PaymentFailed` event (Orders marks failed, Inventory releases reservation after 30-min window)

> **30-minute retry window:** When order transitions to `failed`, do NOT immediately release inventory. Dispatch a delayed `ReleaseInventoryReservationJob` with 30-min delay. If user retries and payment succeeds before 30 min, cancel the delayed job. If not, job fires and releases stock + sets order to `cancelled`.

**`getLatestPayment(Order $order): Payment|null`**

### 4.4 WebhookController
```
POST /api/v1/webhooks/tap
```
- No auth middleware (Tap calls this, not the user)
- Rate limit: 60/min from Tap's IPs (or no rate limit if Tap IPs are unpredictable)
- Step 1: Verify HMAC-SHA256 hashstring BEFORE touching anything (401 if invalid)
- Step 2: Extract `charge_id` from payload
- Step 3: Dispatch `ProcessTapWebhookJob` (queued job for reliability)
- Return 200 immediately (Tap retries twice — respond fast)

**ProcessTapWebhookJob:**
- Calls `PaymentService::handleChargeResult($chargeId)`
- Idempotent — safe to run twice

### 4.5 PaymentController
```
POST /api/v1/payments/charge
```
- Auth: `auth:sanctum`
- Rate limit: 10/min (checkout rate limit from CLAUDE.md)
- Body: `{ order_id: uuid }`
- Validates order belongs to authenticated user, is in `pending` status
- Calls `PaymentService::initiatePayment()`
- Returns: `{ payment_id, redirect_url }` → frontend redirects user

```
GET /api/v1/payments/result
```
- No auth required (user may not be logged in on return)
- Query params: `tap_id` (tap charge ID from Tap's redirect)
- Calls `PaymentService::handleChargeResult($tapId)` directly (not queued — user is waiting)
- Returns: `{ status, order_id }` → frontend uses this to show result page

```
GET /api/v1/payments/order/{order_id}
```
- Auth: `auth:sanctum`
- Returns latest Payment for an order (for frontend polling)

### 4.6 RefundService

**`requestRefund(Payment $payment, User $customer, array $data): Refund`**
- Validates payment status is `captured`
- Validates no existing pending/approved refund for this payment
- Validates requested amount ≤ `payment->amount_fils`
- Creates `Refund` record with status `pending`
- Fires `RefundRequested` event → Notifications module alerts admin

**`approveRefund(Refund $refund, User $admin, ?string $adminNotes): Refund`**
- Sets status to `processing`, records `approved_by`
- Calls `TapApiService::createRefund()` with amount, charge_id, reason
- On success: sets status to `refunded`, stores `tap_refund_id` + `tap_response`
- Fires `PaymentRefunded` event (maps to `OrderRefunded` in event map → Orders update status, Inventory return stock)
- Fires `RefundApproved` event → Notifications module emails customer
- On `TapApiException`: sets status to `failed`, re-throws

**`rejectRefund(Refund $refund, User $admin, string $adminNotes): Refund`**
- Sets status to `rejected`, records admin notes
- Fires `RefundRejected` event → Notifications module emails customer

---

## 5. API Endpoints Summary

| Method | Path | Auth | Rate Limit | Description |
|--------|------|------|-----------|-------------|
| POST | `/api/v1/payments/charge` | sanctum | 10/min | Initiate charge, get Tap redirect URL |
| GET | `/api/v1/payments/result` | none | 30/min | Handle redirect return from Tap |
| GET | `/api/v1/payments/order/{id}` | sanctum | 60/min | Get payment status for order |
| POST | `/api/v1/payments/{payment}/refund` | sanctum | 10/min | Customer requests a refund |
| POST | `/api/v1/webhooks/tap` | none (HMAC) | 60/min | Tap webhook receiver |

---

## 6. Frontend Pages

### 6.1 Checkout → Payment initiation
On the existing `/[locale]/checkout` page:
- "Place Order" button calls `POST /api/v1/payments/charge`
- On success: `window.location.href = response.redirect_url` (hard redirect to Tap)
- On error: show inline error toast ("Payment service unavailable, please try again")
- Loading state on button during API call

### 6.2 `/[locale]/checkout/result` (new page)
Tap redirects here after payment with query params (e.g. `?tap_id=chg_xxx&status=CAPTURED`).

**Flow:**
1. Page mounts → calls `GET /api/v1/payments/result?tap_id=<tap_id>`
2. While awaiting response: show skeleton / spinner with "Verifying your payment..."
3. On `status: captured` → show success state (order number, green checkmark, "View Order" CTA → `/orders/[id]`)
4. On `status: failed` → show failure state (red, "Payment unsuccessful", "Try Again" button → back to checkout with same order) + reason if available
5. On still `initiated` (webhook not yet arrived): poll `GET /api/v1/payments/order/{order_id}` every 3s up to 30s. If still unresolved after 30s: show "Your payment is being verified — check your orders page" with link to `/orders`.
6. RTL: all layouts use logical CSS (`ms-*`, `me-*`, `ps-*`)

**Zod schema:**
```ts
const PaymentResultSchema = z.object({
  status: z.enum(['initiated', 'captured', 'failed', 'void', 'refunded']),
  order_id: z.string().uuid(),
  payment_id: z.string().uuid(),
})
```

### 6.3 `/[locale]/orders/[id]/refund` (new page)
Customer refund request form.

- Only accessible if order status is `paid` (guard redirect otherwise)
- Fields:
  - Reason (select): "I want to cancel my order" | "Item not as described" | "Other" → maps to `requested_by_customer`
  - Notes (textarea, optional)
  - Amount (pre-filled with order total, read-only for now — partial by item not yet in scope)
- Submit → `POST /api/v1/payments/{payment_id}/refund`
- Success: redirect to `/orders/[id]` with toast "Refund request submitted, we'll review within 2 business days"
- Error: inline validation messages

### 6.4 Orders list/detail updates
- Add payment status badge to order detail (existing page) — "Paid", "Payment Failed", "Refund Pending", "Refunded"
- On `failed` status: show "Retry Payment" button → re-initiates charge on same order
- On `refund_pending` status: show "Refund requested — under review"
- "Request Refund" link on paid orders → `/orders/[id]/refund`

---

## 7. Jobs & Scheduling

### 7.1 `CheckStalePaymentsJob`
- Schedule: every 15 minutes (`->everyFifteenMinutes()` in Console/Kernel or `routes/console.php`)
- Query: `Payment::where('status', 'initiated')->where('created_at', '<', now()->subMinutes(30))`
- For each: call `TapApiService::retrieveCharge($payment->tap_charge_id)`
- Pass result through `PaymentService::handleChargeResult()` (idempotent, safe)
- If Tap returns unknown status or API error: log warning, skip (retry next run)

### 7.2 `ReleaseInventoryReservationJob` (delayed)
- Dispatched on payment failure with 30-minute delay
- Checks if order is still in `failed` status before acting
- If still failed: fires `OrderCancelled` event (Inventory releases reservation)
- If order is now `paid` (user retried and succeeded): no-op

---

## 8. Events Added / Updated

| Event | Fired by | New? |
|-------|----------|------|
| `PaymentCaptured` | PaymentService | Exists in event map |
| `PaymentFailed` | PaymentService | Exists in event map |
| `PaymentRefunded` | RefundService | Maps to `OrderRefunded` in event map |
| `RefundRequested` | RefundService | New — Notifications alerts admin |
| `RefundApproved` | RefundService | New — Notifications emails customer |
| `RefundRejected` | RefundService | New — Notifications emails customer |

---

## 9. Filament Admin Panel

### 9.1 PaymentResource
- List view: tap_charge_id, order number, customer name, amount (BHD), status, attempt, created_at
- Filterable by: status, date range
- Detail view shows:
  - All fields above
  - Full `tap_response` JSON (collapsible code block)
  - "Sync Status" action → calls `TapApiService::retrieveCharge()`, updates local record
- Charges are read-only (no create/edit — all created via API flow)

### 9.2 RefundResource
- List view: order number, customer, requested amount, reason, status, requested_at
- Filterable by: status (pending, approved, rejected, refunded, failed)
- Detail view:
  - Customer notes, requested amount, associated payment
  - Admin notes field (editable)
  - "Approve Refund" action → calls `RefundService::approveRefund()` (shows confirmation modal with amount)
  - "Reject Refund" action → calls `RefundService::rejectRefund()` (requires admin notes)
  - After approval: shows Tap refund ID + full `tap_response`

---

## 10. Environment Configuration

### `.env` additions
```dotenv
# Tap Payments
TAP_SECRET_KEY_TEST=sk_test_xxxx
TAP_SECRET_KEY_LIVE=sk_live_xxxx
TAP_WEBHOOK_SECRET=your_webhook_secret_from_tap_dashboard
TAP_API_BASE_URL=https://api.tap.company/v2
```

### `config/services.php` addition
```php
'tap' => [
    'secret_key'     => app()->isProduction()
                          ? env('TAP_SECRET_KEY_LIVE')
                          : env('TAP_SECRET_KEY_TEST'),
    'webhook_secret' => env('TAP_WEBHOOK_SECRET'),
    'base_url'       => env('TAP_API_BASE_URL', 'https://api.tap.company/v2'),
],
```

### Tap Dashboard setup checklist (one-time)
1. Log into Tap Dashboard → Developer → API Credentials → copy test + live secret keys
2. Developer → Webhooks → Add webhook URL: `https://<yourdomain>/api/v1/webhooks/tap`
3. Select events: Charge (all statuses), Refund (all statuses)
4. Copy webhook secret → `TAP_WEBHOOK_SECRET` in `.env`
5. Add redirect domain to Tap's allowed list (if required)

---

## 11. Security

- **Webhook HMAC:** Verified BEFORE any DB reads/writes. 401 on failure, no body logged.
- **Never log:** amount values, card data, full customer PII from Tap response
- **Rate limits:** charge endpoint 10/min, webhook 60/min, result 30/min
- **Order ownership:** `POST /payments/charge` validates `order->user_id === auth()->id()`
- **Refund ownership:** `POST /payments/{payment}/refund` validates payment's order belongs to user
- **Amount bounds:** Refund amount validated server-side against original charge amount
- **Idempotency on retries:** `tap_charge_id` UNIQUE constraint on payments table prevents double-inserts

---

## 12. Edge Cases

| Scenario | Handling |
|----------|----------|
| User closes tab after Tap redirect, before paying | `CheckStalePaymentsJob` fires every 15 min, retrieves charge from Tap, resolves status |
| Webhook arrives before user returns from redirect | `handleChargeResult` is idempotent — redirect finds order already `captured`, shows success |
| User hits "Pay" twice quickly | Second request fails at `POST /payments/charge` because order is already `initiated` (validated) |
| Tap API down during charge creation | `TapApiException` caught, 422 returned, order stays `pending`, user shown retry message |
| Tap API down during webhook processing | `ProcessTapWebhookJob` fails, Laravel queue retries with backoff, 200 already returned to Tap |
| Partial refund > original amount | Validated in `RefundService` before Tap call, 422 returned |
| Admin approves refund, Tap returns error | `Refund->status` set to `failed`, exception re-thrown, admin sees error in Filament |
| Order retry after failure (same order) | New `Payment` record created with `attempt_number + 1`. Previous failed payment row preserved. |
| Guest checkout (no user) | `tap_customer_id` not created, customer passed inline in charge payload |
| Registered user first payment | `TapApiService::createCustomer()` called, `tap_customer_id` saved to user for future charges |

---

## 13. Test Plan

### Feature tests (Pest)
- `PaymentService::initiatePayment` — creates Payment record, returns redirect URL
- `PaymentService::handleChargeResult` — CAPTURED: fires PaymentCaptured, updates status
- `PaymentService::handleChargeResult` — FAILED: fires PaymentFailed, updates status
- `PaymentService::handleChargeResult` — idempotency: calling twice with same charge_id is a no-op after first
- `WebhookController` — valid HMAC: processes charge
- `WebhookController` — invalid HMAC: returns 401, no DB changes
- `RefundService::requestRefund` — creates Refund record, fires RefundRequested
- `RefundService::approveRefund` — calls Tap, updates status, fires PaymentRefunded
- `RefundService::rejectRefund` — updates status, fires RefundRejected
- `CheckStalePaymentsJob` — stale initiated payment: calls retrieve, resolves status
- `CheckStalePaymentsJob` — fresh initiated payment: skipped
- `POST /api/v1/payments/charge` — unauthenticated: 401
- `POST /api/v1/payments/charge` — wrong user's order: 403
- `GET /api/v1/payments/result` — valid tap_id: returns status
- `POST /api/v1/webhooks/tap` — returns 200 immediately, dispatches job

### Frontend tests (Vitest + Playwright)
- `/checkout/result` — shows spinner while loading
- `/checkout/result` — shows success state on `captured`
- `/checkout/result` — shows failure state + retry button on `failed`
- `/checkout/result` — polls when `initiated`, resolves after webhook
- `/orders/[id]/refund` — form validation, submit success flow
- RTL: all new pages render correctly in `dir="rtl"`

---

## 14. Open Questions (confirm before implementing)

1. **Webhook hashstring fields:** Exact fields and concatenation order for HMAC-SHA256 — must be confirmed from Tap dashboard webhook docs or Tap support.
2. **Tap charge status values:** Full enum of possible `status` strings returned by Tap (INITIATED, CAPTURED, FAILED, VOID — are there others like PENDING, AUTHORIZED?).
3. **Redirect query params:** Exact query params Tap appends to redirect URL (e.g. `tap_id`, `status`) — confirm from Tap docs.
4. **Guest checkout:** Does `POST /api/v1/payments/charge` need to support guest (unauthenticated) users, or is checkout always authenticated? (Cart module supports guests — align with Orders module behavior.)
5. **Tap customer create endpoint:** Confirm exact payload for `POST /v2/customers/` from Tap docs before implementing `TapApiService::createCustomer()`.

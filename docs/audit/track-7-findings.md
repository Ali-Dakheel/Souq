# Track 7 Audit: Returns, Loyalty, Inventory, Notifications

**Date:** 2026-04-10
**Modules:** Returns, Loyalty, Inventory, Notifications
**Summary:** 4 blocking issues, 5 should-fix, 4 minor notes

---

## Architecture Issues [BLOCKING]

### A1. Mail::send() used in queued listeners instead of Mail::queue()

**Files:**
- `app/Modules/Notifications/Listeners/SendOrderConfirmationEmail.php:18`
- `app/Modules/Notifications/Listeners/SendPaymentReceiptEmail.php:20`
- `app/Modules/Notifications/Listeners/SendShippingUpdateEmail.php:18`
- `app/Modules/Notifications/Listeners/SendInvoiceEmail.php:18`
- `app/Modules/Notifications/Listeners/SendShipmentCreatedEmail.php:18`

**Rule:** CLAUDE.md: "Mail::queue() not Mail::send() in queued listeners"

**Current:** 5 of 6 notification listeners use `Mail::send()`. Only `SendCodCollectedEmail` correctly uses `Mail::queue()`.

**Impact:** Although the listeners themselves implement `ShouldQueue` (so they already run on the queue), the Mailable classes also implement `ShouldQueue`. When `Mail::send()` is called on a `ShouldQueue` mailable inside an already-queued listener, the mailable gets double-queued — first the listener is queued, then when the listener runs, `Mail::send()` sees `ShouldQueue` on the mailable and queues it again. This adds unnecessary overhead. More critically, if the `ShouldQueue` interface were ever removed from the Mailable (leaving queue behavior to the listener), `Mail::send()` would block the queue worker synchronously.

**Fix:** Change `Mail::send(...)` to `Mail::queue(...)` in all 5 listeners, matching `SendCodCollectedEmail`.

---

### A2. Guest order emails crash with null pointer on 3 mailables

**Files:**
- `app/Modules/Notifications/Mail/OrderConfirmationMail.php:29` — `$this->order->user->email`
- `app/Modules/Notifications/Mail/PaymentReceiptMail.php:32` — `$this->order->user->email`
- `app/Modules/Notifications/Mail/ShippingUpdateMail.php:29` — `$this->order->user->email`

**Rule:** CLAUDE.md: "Guest orders: $order->user?->email ?? $order->guest_email everywhere — $order->user can be null"

**Current:** These 3 mailables access `$this->order->user->email` without null-safe operator. For guest orders where `user_id` is null, this throws a fatal error.

**Correctly implemented in:** `CodCollectedMail`, `ShipmentCreatedMail` (use `$this->order->user?->email ?? $this->order->guest_email`). `InvoiceMail` uses `$this->order->user->email ?? $this->order->guest_email` which is also wrong — `->email` on null throws before `??` is reached.

**Fix:** Change all 4 to `$this->order->user?->email ?? $this->order->guest_email`.

---

### A3. EarnPointsJob lacks idempotency guard — double-earn on retry

**File:** `app/Modules/Loyalty/Jobs/EarnPointsJob.php:27-39`

**Rule:** CLAUDE.md: "ALL queue jobs must be idempotent (check state before acting, safe to run twice)"

**Current:** The job has `ShouldBeUnique` + `uniqueId()`, which prevents concurrent dispatch but does NOT prevent re-earning after the unique lock expires. If the job is retried (e.g., after a transient failure), it will create duplicate loyalty transactions and double-credit points because there is no check for an existing `LoyaltyTransaction` with `reference_type='order'` and `reference_id=$this->order->id`.

**Fix:** Add an idempotency guard at the start of `handle()`:
```php
$existing = LoyaltyTransaction::where('reference_type', 'order')
    ->where('reference_id', $this->order->id)
    ->where('type', 'earn')
    ->exists();
if ($existing) {
    return;
}
```

---

### A4. ReturnCompleted event has no listener for inventory movement recording

**File:** `app/Modules/Inventory/InventoryServiceProvider.php`

**Rule:** Audit spec: "Every stock change creates InventoryMovement"

**Current:** When a return is completed, `ReturnService::completeReturn()` restocks inventory via `InventoryItem::increment('quantity_available', ...)` and dispatches `ReturnCompleted`. However, no listener in the Inventory module records an `InventoryMovement` for this restock. The audit ledger has a gap — stock increases from returns are invisible in the movement history.

**Fix:** Create a `RecordMovementOnReturnCompleted` listener that records a movement with type `return_restock` for each returned item, and register it in `InventoryServiceProvider`.

---

## Bugs [BLOCKING]

(Covered above in A2 and A3)

---

## Quality Issues [SHOULD FIX]

### Q1. Redundant catch-rethrow in ReturnRequestController::store()

**File:** `app/Modules/Returns/Controllers/ReturnRequestController.php:46-48`

**Current:**
```php
} catch (ValidationException $e) {
    throw $e;
}
```

**Issue:** Catching an exception only to immediately rethrow it is dead code. It adds noise without changing behavior.

**Fix:** Remove the try/catch block entirely — `ValidationException` will propagate automatically.

---

### Q2. N+1 query risk in ReturnRequestController::index()

**File:** `app/Modules/Returns/Controllers/ReturnRequestController.php:27`

**Current:** `$returns = $order->returnRequests()->latest()->get();`

**Issue:** The `ReturnRequestResource` conditionally renders `items` via `whenLoaded()`, but items are never eager-loaded in the `index` endpoint. If the frontend ever expects items in the list response, this would cause N+1 queries. Even now, it's inconsistent with the `store` response which does load items.

**Fix:** Change to `$order->returnRequests()->with('items')->latest()->get();`

---

### Q3. Return window checks `created_at` instead of delivery date

**File:** `app/Modules/Returns/Services/ReturnService.php:43`

**Current:** `$order->created_at->diffInDays(now()) > self::RETURN_WINDOW_DAYS`

**Issue:** The 14-day return window is calculated from order creation date, not the delivery date. An order placed on day 1 but delivered on day 12 would only have 2 days to request a return. The window should start from when `order_status` changed to `delivered`.

**Fix:** Use a `delivered_at` timestamp if available, or document this as intentional business logic.

---

### Q4. LoyaltyService does not set `expires_at` on earn transactions

**File:** `app/Modules/Loyalty/Services/LoyaltyService.php:50-58`

**Rule:** Config key `points_expiry_days` exists but is never used.

**Current:** The `LoyaltyTransaction` model has an `expires_at` cast to datetime, and the config table seeds `points_expiry_days = 365`. However, `earnPoints()` never sets `expires_at` on created transactions, and no expiration logic exists anywhere.

**Fix:** Set `'expires_at' => now()->addDays((int) $this->config('points_expiry_days'))` on earn transactions, and add a scheduled command or job to expire old points.

---

### Q5. Inventory movement `quantity_after` is stale due to race condition

**Files:**
- `app/Modules/Inventory/Listeners/RecordMovementOnOrderPlaced.php:18-24`
- `app/Modules/Inventory/Listeners/RecordMovementOnOrderCancelled.php:18-24`

**Current:** These listeners read inventory state without `lockForUpdate()` to compute `quantity_after`, but the actual inventory mutation happens in a separate listener (`ReserveInventoryOnOrderPlaced`/`ReleaseInventoryOnOrderCancelled`). Since both listeners fire on the same event, execution order is not guaranteed, so `quantity_after` may be computed before or after the actual inventory change — making the snapshot unreliable.

**Fix:** Either (a) merge movement recording into the same transaction as the inventory mutation, or (b) read inventory with `lockForUpdate()` and ensure listener ordering.

---

## Minor Notes [OPTIONAL]

### M1. `$admin` parameter unused in `approveReturn()` and `rejectReturn()`

**File:** `app/Modules/Returns/Services/ReturnService.php:80,105`

The `$admin` User parameter is accepted but never stored. Consider recording which admin approved/rejected for audit trail purposes.

### M2. LoyaltyService `manualAdjust` allows negative balance

**File:** `app/Modules/Loyalty/Services/LoyaltyService.php:183`

When `$points` is negative, `decrement` can push `points_balance` below zero. No guard exists unlike `redeemPoints()` which checks balance.

### M3. `ReturnRequestController` does not validate that `order_item_id` belongs to the order

**File:** `app/Modules/Returns/Requests/CreateReturnRequest.php:22`

The validation rule `'items.*.order_item_id' => ['required', 'integer', 'exists:order_items,id']` checks the item exists in ANY order, not the specific order being returned. A user could reference items from a different order.

### M4. No `RecordMovementOnPaymentFailed` listener

**File:** `app/Modules/Inventory/InventoryServiceProvider.php:30`

`ReleaseInventoryOnPaymentFailed` releases reserved stock on `PaymentFailed` but no corresponding `InventoryMovement` is recorded, creating another audit ledger gap (similar to A4).

---

## Verdict

**VERDICT: REQUEST CHANGES (4 blocking issues)**

- A1: Mail::send() vs Mail::queue() inconsistency (5 listeners)
- A2: Guest order null pointer crash (4 mailables)
- A3: EarnPointsJob missing idempotency guard (double-earn risk)
- A4: No inventory movement recorded for return restocks (audit gap)

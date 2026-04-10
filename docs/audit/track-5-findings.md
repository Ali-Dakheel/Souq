# Track 5 Audit: Orders & Payments Modules

**Audited:** 2026-04-10
**Scope:** `app/Modules/Orders/` and `app/Modules/Payments/` -- all controllers, services, models, requests, resources, events, listeners, jobs
**Files reviewed:** 50+

## Summary

| Severity | Count |
|---|---|
| BLOCKING | 5 |
| SHOULD FIX | 7 |
| OPTIONAL | 4 |

---

## Architecture Issues [BLOCKING]

### ARC-1: OrderPlaced event dispatched INSIDE DB::transaction()

- **File:** `app/Modules/Orders/Services/OrderService.php` line 190
- **Rule:** CLAUDE.md Section 4: "Events must be dispatched OUTSIDE `DB::transaction()`"
- **Current:** `OrderPlaced::dispatch($order, $eventItems)` is inside the `DB::transaction()` closure at line 104-193. Queued listeners (Inventory reservation, Notification emails) will be dispatched even if the transaction rolls back.
- **Fix:** Collect `$eventItems` inside the transaction, return both `$order` and `$eventItems` from the closure, then dispatch `OrderPlaced` after the transaction completes (same pattern already used for `CODCollected` at line 243 and `ShipmentCreated` in ShipmentService).

### ARC-2: GenerateInvoiceJob dispatched INSIDE DB::transaction() for COD orders

- **File:** `app/Modules/Orders/Services/OrderService.php` line 184
- **Rule:** CLAUDE.md Section 4: "Events must be dispatched OUTSIDE `DB::transaction()`" (applies to queued jobs too -- a queued job dispatched inside a tx fires even on rollback)
- **Current:** `GenerateInvoiceJob::dispatch($order->id)` is inside the transaction closure for COD checkout.
- **Fix:** Move the COD invoice job dispatch to after the transaction, alongside the event dispatch.

### ARC-3: PaymentCaptured / PaymentFailed events dispatched INSIDE DB::transaction()

- **File:** `app/Modules/Payments/Services/PaymentService.php` lines 131, 146
- **Rule:** CLAUDE.md Section 4: "Events must be dispatched OUTSIDE `DB::transaction()`"
- **Current:** `handleChargeResult()` dispatches `PaymentCaptured` and `PaymentFailed` inside the `DB::transaction()` closure (lines 73-101). These events trigger order status changes, invoice generation, and notification emails -- all of which fire even if the encompassing transaction rolls back.
- **Fix:** Use `$result = DB::transaction(...)` pattern. Capture the tap status inside the transaction, save the transaction record, but move event dispatch to after the closure returns. Example:
  ```php
  $result = DB::transaction(function () use ($tapChargeId) {
      // ... lock, update, save ...
      return ['transaction' => $transaction, 'action' => $tapStatus];
  });
  // Dispatch events here based on $result['action']
  ```

### ARC-4: InvoiceGenerated event dispatched INSIDE DB::transaction()

- **File:** `app/Modules/Orders/Services/InvoiceService.php` line 130
- **Rule:** CLAUDE.md Section 4: "Events must be dispatched OUTSIDE `DB::transaction()`"
- **Current:** `InvoiceGenerated::dispatch($invoice, $order)` is inside the `DB::transaction()` closure. The Notifications module listener will fire even on rollback.
- **Fix:** Return the invoice from the transaction closure, then dispatch the event outside.

---

## Bugs [BLOCKING]

### BUG-1: ReleaseInventoryReservationJob can never cancel a failed order

- **File:** `app/Modules/Payments/Jobs/ReleaseInventoryReservationJob.php` lines 40-53
- **File:** `app/Modules/Orders/Models/Order.php` line 115-118
- **Rule:** Idempotent jobs must actually perform their intended action
- **Current:** The job checks `$order->order_status !== 'failed'` and bails if not failed. Then calls `OrderService::cancelOrder()` which calls `$order->isCancellable()`. But `isCancellable()` only returns true for `['pending', 'initiated', 'pending_collection']` -- it does NOT include `'failed'`. So the cancel always throws `InvalidArgumentException`, which the job catches and logs as "not cancellable." **Result: inventory is never released for failed orders that are not retried.**
- **Fix:** Either add `'failed'` to `isCancellable()` or create a dedicated `cancelFailedOrder()` method that directly transitions `failed -> cancelled` and fires `OrderCancelled`.

---

## Quality Issues [SHOULD FIX]

### QUA-1: N+1 query in OrderListResource

- **File:** `app/Modules/Orders/Resources/OrderListResource.php` line 22
- **File:** `app/Modules/Orders/Services/OrderService.php` line 405-412
- **Rule:** No N+1 queries -- eager loading with `with()` on collections
- **Current:** `OrderListResource` accesses `$this->items_count ?? $this->items->sum('quantity')`. The `getUserOrders()` method does not call `withCount('items')` or `with('items')`, so each order in the paginated list triggers a lazy load.
- **Fix:** Add `->withCount('items')` to the query in `getUserOrders()`, or use `->with('items')`. `withCount` is preferred since only the count is needed.

### QUA-2: CheckoutRequest missing locale validation

- **File:** `app/Modules/Orders/Requests/CheckoutRequest.php`
- **Rule:** Form Requests handle ALL validation
- **Current:** The `locale` parameter is accepted by the controller (`$request->string('locale', 'ar')`) but has no validation rule in `CheckoutRequest`. A malicious user could pass any string as locale.
- **Fix:** Add `'locale' => ['nullable', 'string', 'in:ar,en']` to the rules array.

### QUA-3: cancelOrder / cancelOrderAsAdmin not wrapped in DB::transaction()

- **File:** `app/Modules/Orders/Services/OrderService.php` lines 253-283, 333-357
- **Rule:** Database transactions on multi-step operations
- **Current:** Both methods do `$order->update()` + `recordStatusChange()` without a transaction. If the status history insert fails, the order status is updated but the audit trail is incomplete. Event dispatch should remain outside the transaction.
- **Fix:** Wrap the update + recordStatusChange in `DB::transaction()`, keep the `OrderCancelled::dispatch()` outside.

### QUA-4: fulfillOrder not wrapped in DB::transaction()

- **File:** `app/Modules/Orders/Services/OrderService.php` lines 289-302
- **Rule:** Database transactions on multi-step operations
- **Current:** `$order->update()` + `$this->recordStatusChange()` are two separate DB writes without a transaction.
- **Fix:** Same as QUA-3 -- wrap in transaction, dispatch event outside.

### QUA-5: MarkOrderRefundedOnOrderRefunded does not capture $oldStatus before update

- **File:** `app/Modules/Orders/Listeners/MarkOrderRefundedOnOrderRefunded.php` line 24-27
- **Rule:** CLAUDE.md Section 10: "`$oldStatus = $order->order_status` BEFORE `$order->update()`"
- **Current:** `$order->update(['order_status' => 'refunded'])` then `recordStatusChange($order, 'refunded', 'system', 'Refund processed.')` -- no `$oldStatus` captured. The `recordStatusChange` falls back to `$order->getOriginal('order_status')` but after `update()` the original is already overwritten by `syncOriginal()`.
- **Fix:** Capture `$oldStatus = $order->order_status` before `$order->update()` and pass it to `recordStatusChange()`.

### QUA-6: Payments module cross-calls OrderService directly

- **File:** `app/Modules/Payments/Services/PaymentService.php` line 60-61
- **Rule:** CLAUDE.md Section 4: "Services fire Events; other modules listen via Events -- never cross-call Services"
- **Current:** `PaymentService::initiatePayment()` calls `app(OrderService::class)->recordStatusChange()` to transition order status to `initiated`. This is a cross-module service call from Payments into Orders.
- **Fix:** Fire a `PaymentInitiated` event, have the Orders module listen and update the status. Or move the status transition into the Orders module via the existing event pattern.

### QUA-7: overrideOrderStatus not wrapped in DB::transaction()

- **File:** `app/Modules/Orders/Services/OrderService.php` lines 315-326
- **Rule:** Database transactions on multi-step operations
- **Current:** `$order->update()` + `recordStatusChange()` without transaction.
- **Fix:** Wrap in transaction.

---

## Minor Notes [OPTIONAL]

### MIN-1: CheckStalePaymentsJob lacks ShouldBeUnique

- **File:** `app/Modules/Payments/Jobs/CheckStalePaymentsJob.php`
- **Current:** The scheduled job implements `ShouldQueue` but not `ShouldBeUnique`. If the scheduler fires overlapping runs (e.g., previous run took > 15 min), multiple instances could process the same stale transactions simultaneously.
- **Note:** `handleChargeResult` has `lockForUpdate()` so this is not a data corruption risk, just wasted work. Consider adding `ShouldBeUnique` or `->withoutOverlapping()` on the schedule.

### MIN-2: Guest order lookup has no rate limiting differentiation

- **File:** `app/Modules/Orders/routes.php` line 9
- **Current:** Guest order lookup uses `throttle:60,1` (60/min). Since it accepts an email parameter and uses `hash_equals` for verification, this is timing-safe. But 60 attempts/min on a guest endpoint that reveals order existence could be used for enumeration.
- **Note:** Consider reducing to `throttle:10,1` or requiring a CAPTCHA token.

### MIN-3: TapTransactionResource does not expose `tap_response` to API consumers

- **File:** `app/Modules/Payments/Http/Resources/TapTransactionResource.php`
- **Current:** The full `tap_response` JSON is stored per CLAUDE.md rules but never returned in the API resource. This is likely intentional (don't leak Tap internals), but worth documenting explicitly.

### MIN-4: RefundService.requestRefund does not accept partial amount from controller

- **File:** `app/Modules/Payments/Http/Controllers/PaymentController.php` line 143-148
- **Current:** The controller never passes `amountFils` to `requestRefund()`, so all customer-initiated refunds default to full refund. The `RefundRequestRequest` has no `amount` field. This may be intentional for MVP but limits partial refund capability from the customer side.

---

## Positive Observations

These patterns are well-implemented and worth noting:

1. **Checkout orchestration** follows the prescribed order: shipping rate before transaction, `lockForUpdate()` on inventory, `attachShippingToOrder` after transaction, `$result = DB::transaction(...)` pattern used (except for the event dispatch issue noted above).
2. **Address ownership** is defense-in-depth: checked in both `CheckoutRequest` (via `Rule::exists` scoped to `user_id`) and `OrderService::checkout()`.
3. **All money in integer fils** -- no floats in DB columns, casts, or business logic. Tap amount conversion uses `number_format($fils / 1000, 3, '.', '')` correctly.
4. **Invoice compliance** -- CR number, VAT number, sequential number, all in same transaction. Sequence increment + invoice creation are atomic.
5. **Webhook HMAC verification** follows spec exactly with `hash_equals()`, amount normalization, and production-only enforcement.
6. **Idempotent handlers** -- `handleChargeResult` locks the row and checks terminal states before acting. `GenerateInvoiceJob` re-checks for existing invoice inside locked transaction.
7. **Controllers are thin** -- all business logic delegated to services, Form Requests for validation, API Resources for responses.
8. **COD flow** properly skips Tap, uses dedicated event (`CODCollected`), dispatches event outside transaction.
9. **All queue jobs** have `ShouldBeUnique` + `uniqueId()` (ProcessTapWebhookJob, GenerateInvoiceJob) or idempotency guards (CheckStalePaymentsJob, ReleaseInventoryReservationJob).

---

**VERDICT: REQUEST CHANGES (5 blocking issues)**

The 4 architecture violations (events/jobs dispatched inside transactions) are the same class of bug and could be fixed in a single pass. BUG-1 (ReleaseInventoryReservationJob silently failing) is a real production issue that would leave inventory permanently reserved for failed payments.

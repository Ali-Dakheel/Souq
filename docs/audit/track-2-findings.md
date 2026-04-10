# Security & Auth Audit — Track 2 Findings

**Date:** 2026-04-10
**Auditor:** Code Review Agent
**Scope:** Route protection, ownership scoping, Tap Payments security, input sanitization, admin access

## Summary

| Severity | Count |
|----------|-------|
| CRITICAL | 2 |
| HIGH | 3 |
| MEDIUM | 4 |
| LOW | 3 |
| **Total** | **12** |

---

## CRITICAL

### C-1: Catalog CRUD endpoints are completely unprotected

**File:** `app/Modules/Catalog/routes.php`
**Rule:** Route protection — data-modifying endpoints must have `auth:sanctum`

**Current:** All category, product, variant, and attribute CRUD routes (POST, PUT, PATCH, DELETE) are public — no `auth:sanctum` middleware, no admin/role check. Anyone can create, update, or delete products, categories, attributes, and variants.

```php
// These are all public:
Route::post('categories', [CategoryController::class, 'store']);
Route::put('categories/{category}', [CategoryController::class, 'update']);
Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
Route::post('products', [ProductController::class, 'store']);
Route::put('products/{product}', [ProductController::class, 'update']);
Route::delete('products/{product}', [ProductController::class, 'destroy']);
// ... same for variants, attributes, attribute values
```

**Fix:** Wrap all write operations (POST/PUT/PATCH/DELETE) for catalog resources in `auth:sanctum` middleware plus an admin role/permission check. Read endpoints (GET) can remain public. Example:

```php
Route::middleware(['auth:sanctum', 'role:super_admin'])->group(function () {
    Route::post('categories', [CategoryController::class, 'store']);
    // ... all write routes
});
```

---

### C-2: Customer Group admin CRUD has no admin role check

**File:** `app/Modules/Customers/routes.php` (lines 47-53)
**Rule:** Route protection — admin operations must verify admin role

**Current:** Customer group write endpoints (store, update, destroy, setPrice, removePrice) are behind `auth:sanctum` but have no admin/role guard. Any authenticated customer can create, modify, or delete customer groups and set variant pricing.

```php
// Inside auth:sanctum + throttle:30,1 — same group as profile/address endpoints
Route::post('groups', [CustomerGroupController::class, 'store']);
Route::put('groups/{group}', [CustomerGroupController::class, 'update']);
Route::delete('groups/{group}', [CustomerGroupController::class, 'destroy']);
Route::post('groups/{group}/prices', [CustomerGroupController::class, 'setPrice']);
Route::delete('groups/{group}/prices/{variant}', [CustomerGroupController::class, 'removePrice']);
```

**Fix:** Move these routes into a separate group with admin middleware:

```php
Route::middleware(['auth:sanctum', 'role:super_admin'])->group(function () {
    Route::post('groups', ...);
    // ... all group write routes
});
```

---

## HIGH

### H-1: Events dispatched inside DB::transaction in PaymentService

**File:** `app/Modules/Payments/Services/PaymentService.php` (lines 71-101)
**Rule:** CLAUDE.md section 4 — "Events must be dispatched OUTSIDE `DB::transaction()` — queued listeners fire even if the tx rolls back"

**Current:** `PaymentCaptured::dispatch($order)` (line 131) and `PaymentFailed::dispatch($order)` (line 146) are called from `handleCaptured()` and `handleFailed()`, which are invoked inside the `DB::transaction()` closure in `handleChargeResult()`.

If the transaction rolls back (e.g., `$transaction->save()` fails), the events will still have been dispatched and queued listeners (inventory release, email notifications) will fire on incorrect state.

**Fix:** Collect the event to dispatch, save the transaction inside the DB transaction, then dispatch the event after the transaction commits:

```php
$event = null;
$transaction = DB::transaction(function () use ($tapChargeId, &$event) {
    // ... lock, fetch, update
    // Instead of dispatching, set $event
});
if ($event) { $event::dispatch(...); }
```

Or use Laravel's `DB::afterCommit()` callback.

---

### H-2: Sanctum tokens never expire

**File:** `config/sanctum.php` (line 50)
**Rule:** Security best practice — API tokens should have bounded lifetimes

**Current:** `'expiration' => null` — tokens live forever once created. A leaked token grants permanent access.

**Fix:** Set a reasonable expiration, e.g., `'expiration' => 60 * 24 * 7` (7 days), and implement token refresh in the auth flow. At minimum, set `'expiration' => 60 * 24 * 30` (30 days).

---

### H-3: Cart endpoints (add/update/remove/clear) lack auth enforcement for logged-in users

**File:** `app/Modules/Cart/routes.php` (lines 14-20)
**Rule:** Route protection — data-modifying endpoints accessible without auth may allow cart manipulation

**Current:** `POST cart/add-item`, `PUT cart/items/{cartItem}`, `DELETE cart/items/{cartItem}`, `POST cart/clear`, `POST cart/remove-coupon` are all public — no `auth:sanctum`. They use `X-Cart-Session` header for guest identification.

While guest cart is a valid use case, the `resolveCart()` method in `CartController` calls `Auth::id()` which will be null for unauthenticated requests — meaning unauthenticated users with a session header can operate on guest carts. The risk is that:
1. `X-Cart-Session` is a user-supplied value with no validation — anyone who guesses/enumerates a session ID can modify another guest's cart.
2. `updateItem` and `removeItem` verify `$cartItem->cart_id === $cart->id`, but this only checks that the item belongs to the resolved cart — not that the requester owns that cart.

**Fix:**
- For guest carts: ensure `X-Cart-Session` is a cryptographically random token (UUID v4) generated client-side, document this requirement.
- Consider using `auth:sanctum` as optional middleware and adding validation that the session ID is reasonably unpredictable.
- Add rate limiting note: the 30/min throttle is correctly applied, which mitigates brute-force enumeration.

---

## MEDIUM

### M-1: Guest order lookup vulnerable to order number enumeration

**File:** `app/Modules/Orders/Controllers/OrderController.php` (lines 109-125)
**Rule:** Ownership scoping — users should only access their own data

**Current:** `showGuest()` takes `orderNumber` in the URL and `email` as a query parameter. The order is first looked up by `order_number`, then the email is checked with `hash_equals()`. If the order number exists but the email doesn't match, a 401 is returned. If the order number doesn't exist, a 404 is returned.

This difference in response codes allows an attacker to enumerate valid order numbers by distinguishing 404 (order doesn't exist) from 401 (order exists, wrong email).

**Fix:** Return the same error response regardless of whether the order exists:

```php
$order = Order::where('order_number', $orderNumber)
    ->whereNotNull('guest_email')
    ->first();

if (! $order || ! hash_equals($order->guest_email, (string) $email)) {
    abort(404, 'Order not found.');
}
```

---

### M-2: PaymentController::result() processes charge before ownership check

**File:** `app/Modules/Payments/Http/Controllers/PaymentController.php` (lines 69-103)
**Rule:** Security — verify ownership before performing side effects

**Current:** `result()` calls `$this->paymentService->handleChargeResult($tapChargeId)` (which updates DB state, fires events, etc.) BEFORE checking `$transaction->order->user_id !== $request->user()->id`. An authenticated user can trigger state changes on another user's payment by providing their `tap_id`, even though the response is blocked.

**Fix:** Look up the transaction first, verify ownership, then process:

```php
$transaction = TapTransaction::where('tap_charge_id', $tapChargeId)->firstOrFail();
if ($transaction->order->user_id !== $request->user()->id) {
    abort(403);
}
$transaction = $this->paymentService->handleChargeResult($tapChargeId);
```

---

### M-3: No rate limiting on Returns endpoints

**File:** `app/Modules/Returns/routes.php`
**Rule:** Rate limiting — all endpoints should have throttle middleware

**Current:** Return request routes have `auth:sanctum` but no `throttle:` middleware. An attacker could spam return requests.

**Fix:** Add `'throttle:60,1'` (or stricter) to the route group.

---

### M-4: Register and login endpoints share the 60/min auth rate limit

**File:** `app/Modules/Customers/routes.php` (lines 13-18)
**Rule:** Rate limiting — registration and login should have stricter limits

**Current:** `auth/register` and `auth/login` share `throttle:60,1` with password-reset endpoints. 60 login attempts per minute is generous for credential stuffing. Registration at 60/min enables spam account creation.

**Fix:** Apply stricter rate limits:
- Login: `throttle:10,1` (10 attempts per minute)
- Register: `throttle:5,1` (5 registrations per minute)
- Keep forgot-password and reset-password at `throttle:5,1`

---

## LOW

### L-1: super_admin Gate::before bypasses all authorization

**File:** `app/Providers/AppServiceProvider.php` (lines 24-29)
**Rule:** Admin access — super_admin bypass should be documented and reviewed

**Current:** `Gate::before()` returns `true` for any user with `super_admin` role, bypassing ALL gate/policy checks application-wide. This is a common pattern for Filament but should be explicitly noted:
- Any future policy or gate will be silently bypassed for super_admin.
- There is no secondary admin role (e.g., `store_manager`) with restricted permissions.

**Fix:** This is acceptable for current scope but document it. When adding additional admin roles in Phase 3F, consider scoped permissions instead of blanket bypass.

---

### L-2: Filament admin panel has no IP restriction or 2FA

**File:** `app/Providers/Filament/AdminPanelProvider.php`
**Rule:** Security — admin panels should have additional protection in production

**Current:** Admin panel at `/admin` is accessible to anyone who can reach the server. Only protection is `canAccessPanel()` checking `hasRole('super_admin')` and Filament's login form.

**Fix:** For production:
- Consider IP allowlisting for the `/admin` path via Cloudflare or middleware.
- Enable 2FA for admin users (Filament v5 supports this via plugins).

---

### L-3: Password cast to 'hashed' in User model may double-hash

**File:** `app/Models/User.php` (line 43) and `app/Modules/Customers/Services/AuthService.php` (line 35)

**Current:** The User model casts `password` to `'hashed'`, which means Eloquent automatically hashes the value on assignment. But `AuthService::register()` explicitly calls `Hash::make($data['password'])` before passing to `User::create()`. This results in the password being hashed twice — `Hash::make()` in the service, then the `hashed` cast hashes the already-hashed value again.

Similarly, `AuthService::resetPassword()` at line 109 calls `Hash::make($newPassword)` before `forceFill()`.

**Fix:** Either:
- Remove the `'hashed'` cast from the User model and keep explicit `Hash::make()` calls, OR
- Remove `Hash::make()` from `AuthService::register()` and `resetPassword()` and rely on the cast.

The current code means users cannot log in after registration or password reset because the stored hash is a hash-of-a-hash. **This is likely a bug if the hashed cast is active.** (Verify by checking if `password` appears in the `casts()` method — it does at line 43.)

**Severity escalation:** If the `hashed` cast is indeed active in this Laravel version, this is a **CRITICAL** authentication bug — no user can log in after registering. Test immediately.

---

## Architecture Violations (non-security, noted for completeness)

### A-1: PaymentService constructor-injects OrderService indirectly via app()

**File:** `app/Modules/Payments/Services/PaymentService.php` (line 60)
**Current:** Uses `app(OrderService::class)` inline — this follows CLAUDE.md guidance for cross-module calls.
**Status:** Compliant.

### A-2: CartController returns raw array data in some responses

**File:** `app/Modules/Cart/Controllers/CartController.php` (lines 51-56, 101-113)
**Current:** `applyCoupon` returns a manually constructed array with coupon data instead of using an API Resource. The `cartSummary()` helper also returns a raw array.
**Status:** Minor violation of "API Resources for all JSON responses."

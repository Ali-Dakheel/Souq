# Track 1 Audit: Controllers & Services

**Date:** 2026-04-10
**Auditor:** Code Review Agent
**Scope:** All controllers in `app/Modules/*/Controllers/` and services in `app/Modules/*/Services/`

## Summary

| Severity | Count |
|----------|-------|
| HIGH     | 5     |
| MEDIUM   | 9     |
| LOW      | 5     |

---

## HIGH Severity

### [SEVERITY: HIGH] Events dispatched inside DB::transaction in OrderService::checkout
**File:** `app/Modules/Orders/Services/OrderService.php:190`
**Rule:** CLAUDE.md: "Events must be dispatched OUTSIDE `DB::transaction()` -- queued listeners fire even if the tx rolls back"
**Current:** `OrderPlaced::dispatch($order, $eventItems)` is called at line 190 inside the `DB::transaction()` closure (lines 104-193). `GenerateInvoiceJob::dispatch($order->id)` is also dispatched inside the transaction at line 184. If the transaction rolls back, queued listeners (inventory reservation, notification emails, invoice generation) will still execute against non-existent data.
**Fix:** Restructure to `$order = DB::transaction(function() { ... return $order; });` then dispatch `OrderPlaced` and `GenerateInvoiceJob` after the transaction closure, similar to how `CODCollected::dispatch` is correctly handled at line 243.

### [SEVERITY: HIGH] Events dispatched inside DB::transaction in PaymentService::handleChargeResult
**File:** `app/Modules/Payments/Services/PaymentService.php:73-100`
**Rule:** CLAUDE.md: "Events must be dispatched OUTSIDE `DB::transaction()`"
**Current:** `PaymentCaptured::dispatch($order)` (line 131) and `PaymentFailed::dispatch($order)` (line 146) plus `ReleaseInventoryReservationJob::dispatch` (line 149) are called inside `handleCaptured`/`handleFailed` methods, which are invoked within the `DB::transaction()` at lines 73-100. Multiple critical listeners react to these events (MarkOrderPaid, ClearCart, GenerateInvoice, EarnPoints, ReleaseInventory).
**Fix:** Collect the event to dispatch as a return value from the transaction, then dispatch outside: `[$transaction, $eventToFire] = DB::transaction(...); $eventToFire?->dispatch();`

### [SEVERITY: HIGH] OrderService constructor-injects cross-module services
**File:** `app/Modules/Orders/Services/OrderService.php:28-31`
**Rule:** CLAUDE.md: "Cross-module service calls: use `app(ServiceClass::class)` not constructor injection (avoids circular deps)"
**Current:** `OrderService` constructor-injects `CartService` (Cart module) and `ShippingService` (Shipping module) -- both are different modules. This creates tight coupling and risks circular dependency resolution.
**Fix:** Replace constructor injection with `app(CartService::class)` and `app(ShippingService::class)` at point of use within methods, or store them via lazy resolution.

### [SEVERITY: HIGH] CartService constructor-injects cross-module services
**File:** `app/Modules/Cart/Services/CartService.php:28-31`
**Rule:** CLAUDE.md: "Cross-module service calls: use `app(ServiceClass::class)` not constructor injection"
**Current:** `CartService` constructor-injects `CustomerGroupService` (Customers module) and `PromotionService` (Promotions module). Both are cross-module dependencies that should use `app()`.
**Fix:** Use `app(CustomerGroupService::class)` and `app(PromotionService::class)` at call sites.

### [SEVERITY: HIGH] CategoryController contains business logic (image CRUD in controller)
**File:** `app/Modules/Catalog/Controllers/CategoryController.php:37-44, 64-76`
**Rule:** CLAUDE.md: "Controllers are thin -- ALL business logic in Service classes"
**Current:** `store()` directly creates `CategoryImage` records (lines 37-44). `update()` handles image create/update/delete logic inline (lines 64-76) including conditional branching for null vs non-null image URLs. This is business logic that belongs in `CategoryService`.
**Fix:** Move image handling into `CategoryService::createCategory()` and `CategoryService::updateCategory()` methods. Controller should only call service methods.

---

## MEDIUM Severity

### [SEVERITY: MEDIUM] AttributeController uses inline $request->validate() instead of FormRequests
**File:** `app/Modules/Catalog/Controllers/AttributeController.php:31,56,82,100`
**Rule:** CLAUDE.md: "Form Requests handle ALL validation -- never validate in controllers"
**Current:** Four methods (`store`, `update`, `storeValue`, `updateValue`) all use `$request->validate([...])` inline. This means no `authorize()` method for authorization checks, and validation rules are not reusable.
**Fix:** Create `StoreAttributeRequest`, `UpdateAttributeRequest`, `StoreAttributeValueRequest`, `UpdateAttributeValueRequest` FormRequest classes.

### [SEVERITY: MEDIUM] ProductController uses inline $request->validate() for bundle/downloadable endpoints
**File:** `app/Modules/Catalog/Controllers/ProductController.php:94,114,136`
**Rule:** CLAUDE.md: "Form Requests handle ALL validation"
**Current:** `storeBundleOption()`, `addBundleOptionProduct()`, and `storeDownloadableLink()` all use inline `$request->validate()`.
**Fix:** Create dedicated FormRequest classes for each endpoint.

### [SEVERITY: MEDIUM] CouponController::validate uses inline $request->validate()
**File:** `app/Modules/Cart/Controllers/CouponController.php:38`
**Rule:** CLAUDE.md: "Form Requests handle ALL validation"
**Current:** `$request->validate(['code' => ..., 'subtotal_fils' => ...])` inline in controller.
**Fix:** Create `ValidateCouponRequest` FormRequest.

### [SEVERITY: MEDIUM] AttributeController returns raw Eloquent models
**File:** `app/Modules/Catalog/Controllers/AttributeController.php:93,111`
**Rule:** CLAUDE.md: "API Resources handle ALL JSON responses -- never return Eloquent models directly"
**Current:** `storeValue()` returns `response()->json($value, 201)` -- raw model. `updateValue()` returns `response()->json($updated)` -- raw model. Both should wrap in an API Resource.
**Fix:** Create `AttributeValueResource` and wrap: `return (new AttributeValueResource($value))->response()->setStatusCode(201);`

### [SEVERITY: MEDIUM] AttributeController::destroy has business logic
**File:** `app/Modules/Catalog/Controllers/AttributeController.php:74-78`
**Rule:** CLAUDE.md: "Controllers are thin -- ALL business logic in Service classes"
**Current:** Controller directly calls `$attribute->values()->delete()` and `$attribute->delete()` -- cascading delete logic belongs in service.
**Fix:** Create `AttributeService::deleteAttribute()` that handles both deletions, potentially in a transaction.

### [SEVERITY: MEDIUM] PromotionController queries Cart model directly
**File:** `app/Modules/Promotions/Controllers/PromotionController.php:27`
**Rule:** Controllers should not query DB directly; CLAUDE.md thin controller rule
**Current:** `Cart::where('user_id', $user->id)->first()` is a direct DB query in the controller instead of delegating to a service.
**Fix:** Inject or `app()` CartService and use `getOrCreateCart()`, or add a method on PromotionService that accepts user context.

### [SEVERITY: MEDIUM] WishlistController::moveToCart returns raw JSON without API Resource
**File:** `app/Modules/Customers/Controllers/WishlistController.php:92-95`
**Rule:** CLAUDE.md: "API Resources handle ALL JSON responses"
**Current:** Returns `response()->json(['message' => ..., 'cart' => $result['cart']])` where `$result['cart']` is a raw Eloquent model with loaded relations.
**Fix:** Wrap cart data in a CartResource.

### [SEVERITY: MEDIUM] WishlistController::generateShareToken returns raw JSON without Resource
**File:** `app/Modules/Customers/Controllers/WishlistController.php:77-80`
**Rule:** CLAUDE.md: "API Resources handle ALL JSON responses"
**Current:** Returns raw `share_token` and `share_url` as inline JSON array, not wrapped in a Resource.
**Fix:** Either use WishlistResource or create a dedicated ShareTokenResource.

### [SEVERITY: MEDIUM] ReturnRequestController queries Order model directly
**File:** `app/Modules/Returns/Controllers/ReturnRequestController.php:23-25, 34-36`
**Rule:** Controllers should delegate to services, not query DB directly
**Current:** Both `index()` and `store()` perform `Order::where('order_number', $orderNumber)->where('user_id', ...)->firstOrFail()` directly in the controller. This ownership check + lookup is business logic.
**Fix:** Move order resolution and ownership validation into `ReturnService` or use an `OrderService` method like `getOrderByNumber()`.

---

## LOW Severity

### [SEVERITY: LOW] ProductService uses whereRaw for search
**File:** `app/Modules/Catalog/Services/ProductService.php:56`
**Rule:** Avoid raw SQL bypassing Eloquent where possible
**Current:** `$sub->whereRaw('LOWER(name::text) LIKE ?', [$term])` -- PostgreSQL-specific cast (`name::text`) ties this to PostgreSQL and bypasses Eloquent's database-agnostic query builder. This will fail on SQLite (used in tests).
**Fix:** Consider using `->where('name', 'ILIKE', $term)` for PostgreSQL or `whereJsonContains` / Scout search for cross-DB compatibility. Note: if tests use PostgreSQL this is acceptable, but still a portability concern.

### [SEVERITY: LOW] CartController::applyCoupon returns inline coupon data instead of Resource
**File:** `app/Modules/Cart/Controllers/CartController.php:101-113`
**Rule:** Consistency -- API Resources for all JSON responses
**Current:** Coupon data is built inline as an associative array (`['code' => ..., 'description' => ...]`) rather than using a CouponResource. While cart summary uses a helper method, the coupon portion is hand-crafted.
**Fix:** Use a dedicated CouponResource or PublicCouponResource for the coupon portion.

### [SEVERITY: LOW] OrderController::index builds pagination meta manually
**File:** `app/Modules/Orders/Controllers/OrderController.php:81-89`
**Rule:** Consistency -- API Resources for all JSON responses
**Current:** Builds `response()->json(['data' => ..., 'meta' => [...]])` manually instead of using Laravel's built-in resource collection pagination which auto-generates meta. Functional but inconsistent with other endpoints.
**Fix:** Use `OrderListResource::collection($paginator)` which auto-wraps with pagination meta.

### [SEVERITY: LOW] VariantController::update bypasses service for update
**File:** `app/Modules/Catalog/Controllers/VariantController.php:44-49`
**Rule:** Controllers should delegate to services
**Current:** `$variant->update($request->validated())` is called directly in the controller rather than through ProductService.
**Fix:** Add `ProductService::updateVariant()` method and delegate.

### [SEVERITY: LOW] VariantController::destroy has inline delete logic
**File:** `app/Modules/Catalog/Controllers/VariantController.php:53-59`
**Rule:** Controllers should delegate to services
**Current:** `$variant->inventory?->delete(); $variant->delete();` -- cascading delete with two separate operations not in a transaction.
**Fix:** Add `ProductService::deleteVariant()` that wraps both deletes in a DB transaction.

### [SEVERITY: LOW] WishlistController::removeItem queries DB directly
**File:** `app/Modules/Customers/Controllers/WishlistController.php:56-59`
**Rule:** Thin controllers -- delegate DB queries to services
**Current:** `$wishlist->items()->where('variant_id', $variantId)->first()` is a DB query in the controller to check item existence before calling `removeItem()`.
**Fix:** Let `WishlistService::removeItem()` handle the existence check and throw 404 if not found.

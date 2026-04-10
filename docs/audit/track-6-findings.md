# Track 6 Audit: Customers, Shipping, Promotions Modules

**Audited:** 2026-04-10
**Modules:** Customers (61 files), Shipping (21 files), Promotions (13 files)
**Auditor:** Code Review Agent

## Summary

| Severity | Count |
|----------|-------|
| BLOCKING | 5 |
| SHOULD FIX | 8 |
| OPTIONAL | 4 |

---

## Architecture Issues [BLOCKING]

### B-01: Events dispatched INSIDE `DB::transaction()` in AddressService

**File:** `app/Modules/Customers/Services/AddressService.php` lines 48-58, 68-90, 128-138
**Rule:** CLAUDE.md Section 4 -- "Events must be dispatched OUTSIDE `DB::transaction()` -- queued listeners fire even if the tx rolls back"
**Current:** `AddressAdded::dispatch()`, `AddressUpdated::dispatch()` are called inside `return DB::transaction(function () { ... })` closures. If a queued listener is ever attached to these events, it will fire even on transaction rollback.
**Fix:** Restructure using `$result = DB::transaction(...)` pattern, then dispatch events after the transaction closes:
```php
$address = DB::transaction(function () use ($user, $data) {
    if (! empty($data['is_default'])) {
        $this->clearDefault($user, $data['address_type']);
    }
    return $user->addresses()->create($data);
});
AddressAdded::dispatch($address);
return $address;
```
Apply the same pattern to `updateAddress()` and `setDefaultAddress()`.

---

### B-02: ShippingController directly calls CartService -- cross-module service violation

**File:** `app/Modules/Shipping/Controllers/ShippingController.php` lines 19-20
**Rule:** CLAUDE.md Section 4 -- "Services fire Events; other modules listen via Events -- never cross-call Services" and "Cross-module service calls: use `app(ServiceClass::class)` not constructor injection"
**Current:** `CartService` is constructor-injected into `ShippingController`. This is a cross-module dependency (Shipping depends on Cart). While CLAUDE.md allows `app()` for cross-module service calls to avoid circular deps, the rule says services should communicate via events, not direct calls. At minimum, this should use `app(CartService::class)` instead of constructor injection.
**Fix:** Change constructor injection to `app()` resolution inside the method body to match the established pattern (see `WishlistService::moveItemToCart()`). The `CartService` import can remain but should be resolved at call time.

---

### B-03: N+1 query in PromotionRule usage exhaustion checks

**File:** `app/Modules/Promotions/Services/PromotionService.php` lines 36-41
**Rule:** No N+1 queries
**Current:** `getApplicableRules()` eagerly loads `conditions` and `actions` but NOT `usages`. Then inside the `foreach` loop, `$rule->isExhaustedGlobally()` calls `$this->usages()->count()` and `$rule->isExhaustedForUser($user->id)` calls `$this->usages()->where(...)->count()`. For N rules, this fires up to 2N additional queries.
**Fix:** Either eager-load `usages` in the initial query (`->with(['conditions', 'actions', 'usages'])`) and use collection counts, or use `->withCount('usages')` and a subquery count for user-scoped exhaustion. The withCount approach is more memory-efficient:
```php
$rules = PromotionRule::active()
    ->with(['conditions', 'actions'])
    ->withCount('usages')
    ->orderBy('priority', 'asc')
    ->get();
```

---

### B-04: N+1 queries in Filament PromotionRuleResource table

**File:** `app/Modules/Promotions/Filament/Resources/PromotionRuleResource.php` lines 176-179
**Rule:** No N+1 queries
**Current:** The table columns for "Conditions" and "Actions" use `fn (PromotionRule $record) => $record->conditions()->count()` and `$record->actions()->count()`. This fires 2 queries per row in the table listing.
**Fix:** Override `getEloquentQuery()` to add `->withCount(['conditions', 'actions'])` and use `TextColumn::make('conditions_count')` / `TextColumn::make('actions_count')` instead.

---

### B-05: Customer group admin routes lack authorization

**File:** `app/Modules/Customers/routes.php` lines 48-53
**Rule:** Security -- admin-only operations must be protected
**Current:** Customer group CRUD routes (POST, PUT, PATCH, DELETE for groups and group prices) are guarded only by `auth:sanctum`. Any authenticated customer can create, update, or delete customer groups and set group pricing. There is no admin/role middleware.
**Fix:** Add an admin middleware or policy gate. At minimum add a gate check or separate admin-only middleware group for these routes.

---

## Bugs [BLOCKING]

(No pure bugs found beyond the architectural issues above that could cause data integrity problems.)

---

## Quality Issues [SHOULD FIX]

### Q-01: WishlistController::moveToCart returns raw array, not API Resource

**File:** `app/Modules/Customers/Controllers/WishlistController.php` lines 83-99
**Rule:** CLAUDE.md Section 4 -- "API Resources handle ALL JSON responses -- never return Eloquent models directly"
**Current:** `moveToCart()` returns `response()->json(['message' => '...', 'cart' => $result['cart']])` where `$result['cart']` is a raw Eloquent model with loaded relations. This bypasses the API Resource layer.
**Fix:** Use a CartResource (or WishlistResource) to format the response. The `WishlistService::moveItemToCart()` should not return raw models either.

---

### Q-02: WishlistController::removeItem does redundant query

**File:** `app/Modules/Customers/Controllers/WishlistController.php` lines 50-65
**Rule:** Controllers should be thin, delegate to services
**Current:** The controller queries `$wishlist->items()->where('variant_id', $variantId)->first()` to check existence, then calls `$this->wishlistService->removeItem()` which runs the same query again. The existence check is business logic that belongs in the service.
**Fix:** Move the 404 check into `WishlistService::removeItem()` and have it throw a ModelNotFoundException if the item does not exist.

---

### Q-03: WishlistService::moveItemToCart does not remove item from wishlist

**File:** `app/Modules/Customers/Services/WishlistService.php` lines 60-68
**Rule:** Functional correctness -- "move to cart" semantics
**Current:** `moveItemToCart()` adds the item to the cart but never removes it from the wishlist. The method name and the controller response ("Item moved to cart.") imply it should be removed.
**Fix:** Call `$this->removeItem($wishlist, $variantId)` after successfully adding to cart.

---

### Q-04: Shipping rates cached with stale cart data

**File:** `app/Modules/Shipping/Services/ShippingService.php` lines 66-103
**Rule:** Data correctness
**Current:** `getAvailableRates()` caches results for 10 minutes keyed by `cart_id + address_id`. If the user adds/removes items from the cart, the cached rates (especially `FreeThresholdCarrier` which depends on cart subtotal) will be stale. The `FreeThresholdCarrier` calculates based on `$item->price_fils_snapshot * $item->quantity`, so cart changes invalidate the cache.
**Fix:** Include a cart content hash in the cache key, or invalidate the cache when cart items change. Alternatively, reduce TTL significantly or remove caching here (rates are computed from already-loaded data).

---

### Q-05: PromotionController directly queries Cart model

**File:** `app/Modules/Promotions/Controllers/PromotionController.php` lines 27-28
**Rule:** CLAUDE.md Section 4 -- cross-module communication should use events or `app(ServiceClass::class)`
**Current:** `Cart::where('user_id', $user->id)->first()` is a direct Eloquent query against the Cart module's model from the Promotions controller. Should use `app(CartService::class)->getOrCreateCart()` or similar.
**Fix:** Resolve `CartService` via `app()` and use the service method to retrieve the cart.

---

### Q-06: CustomerGroupService not registered as singleton in ServiceProvider

**File:** `app/Modules/Customers/CustomersServiceProvider.php` lines 24-28
**Rule:** Proper service container registration
**Current:** `AuthService`, `ProfileService`, and `AddressService` are registered as singletons, but `CustomerGroupService` and `WishlistService` are not registered at all. They rely on auto-resolution, which works but is inconsistent and may cause issues with testing or service mocking.
**Fix:** Add `$this->app->singleton(CustomerGroupService::class)` and `$this->app->singleton(WishlistService::class)` to the `register()` method.

---

### Q-07: Shipping rate ownership check bypassed for guests

**File:** `app/Modules/Shipping/Controllers/ShippingController.php` lines 36-39
**Rule:** Security -- defense-in-depth
**Current:** The route requires `auth:sanctum`, so theoretically no guest can access it. However, the ownership check `if (Auth::id() !== null && ...)` guards only authenticated users. If this route's auth middleware were ever loosened (e.g., for guest checkout), any user could query rates for any address. The `Auth::id() !== null` check is redundant given the route middleware and creates a false sense of future-safety.
**Fix:** Remove the null check and always verify ownership, or add a comment documenting that the route is auth-only by design.

---

### Q-08: `Str::uuid()` stored as string may cause comparison issues

**File:** `app/Modules/Customers/Services/WishlistService.php` line 48
**Rule:** Type safety
**Current:** `Str::uuid()` returns a `UuidInterface` object in newer Laravel versions, not a string. Storing it directly may work due to `__toString()` but could cause type comparison issues.
**Fix:** Use `Str::uuid()->toString()` explicitly.

---

## Minor Notes [OPTIONAL]

### M-01: Unused import in ShippingController

**File:** `app/Modules/Shipping/Controllers/ShippingController.php` line 14
**Current:** `use Illuminate\Support\Facades\Auth;` is used but could be replaced with `$request->user()` for consistency with other controllers in the Customers module.

---

### M-02: PromotionService `compareValues` uses loose comparison for `eq`

**File:** `app/Modules/Promotions/Services/PromotionService.php` line 178
**Current:** `'eq' => $left == $right` uses loose comparison (`==`). With JSONB values that could be strings vs integers, `"5000" == 5000` is true but may not be the intended behavior.
**Fix:** Consider using strict comparison (`===`) or casting both sides to the same type.

---

### M-03: Wishlist share_token exposed in authenticated endpoint response

**File:** `app/Modules/Customers/Resources/WishlistResource.php` line 22
**Current:** The `share_token` is always included in the response. If a user has not generated a share token, this is `null` (harmless). But once generated, it is always visible. Consider conditionally including it.

---

### M-04: BOGO discount logic uses `line_total_fils` not unit price

**File:** `app/Modules/Promotions/Services/PromotionService.php` lines 234-241
**Current:** `calculateBogoDiscount()` sorts by `line_total_fils` (which is `price * quantity`) and gives 50% off the lowest `line_total_fils`. If an item has quantity=3 and unit price=1000, `line_total_fils` is 3000. The BOGO gives 50% off 3000 = 1500 fils discount, which is more than the price of one item. Standard BOGO typically gives 100% off one unit of the cheapest item.
**Fix:** Consider sorting by unit price (`price_fils_snapshot`) and discounting one unit at 100%, which is the standard BOGO interpretation.

---

## VERDICT: REQUEST CHANGES (5 blocking issues)

**Critical items to address:**
1. Events inside transactions (AddressService) -- data integrity risk if queued listeners are added
2. Cross-module constructor injection (ShippingController) -- architecture violation
3. N+1 queries in PromotionService and Filament PromotionRuleResource -- performance
4. Missing authorization on customer group admin routes -- security

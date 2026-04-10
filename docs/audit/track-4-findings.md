# Track 4 Audit: Catalog & Cart Modules

**Auditor:** Claude Opus 4.6 (code-reviewer agent)
**Date:** 2026-04-10
**Scope:** `app/Modules/Catalog/`, `app/Modules/Cart/`, `config/cart.php`

## Summary

| Severity | Count |
|---|---|
| BLOCKING | 5 |
| SHOULD FIX | 8 |
| OPTIONAL | 5 |

---

## Architecture Issues [BLOCKING]

### ARC-1: CartService cross-calls PromotionService and CustomerGroupService directly via constructor injection

**File:** `app/Modules/Cart/Services/CartService.php:28-31`
**Rule:** CLAUDE.md -- "Services fire Events; other modules listen via Events -- never cross-call Services"
**Current:**
```php
public function __construct(
    private readonly CouponService $couponService,
    private readonly CustomerGroupService $customerGroupService,
    private readonly PromotionService $promotionService,
) {}
```
**Fix:** `CouponService` is same-module so it is fine. `CustomerGroupService` (Customers module) and `PromotionService` (Promotions module) are cross-module direct injections. Per CLAUDE.md, cross-module service calls should use `app(ServiceClass::class)` not constructor injection to avoid circular deps. Additionally, the architecture rule says modules should communicate via events, not direct service calls. However, `calculateTotals` is a synchronous read operation that cannot be event-driven, so at minimum switch to `app()` resolution. Consider extracting a `PricingService` that encapsulates cross-module pricing logic.

### ARC-2: VAT rate stored as float 0.10 in config, violates integer-only money rule

**File:** `config/cart.php:19`, `app/Modules/Cart/Services/CartService.php:278-279`
**Rule:** CLAUDE.md -- "NEVER use float or decimal for money anywhere"; also `vat_rate` is "stored as integer percentage (10 = 10%)" per gotchas
**Current:**
```php
// config/cart.php
'vat_rate' => 0.10,

// CartService.php
$vatRate = config('cart.vat_rate', 0.10);
$vatFils = (int) round($taxable * $vatRate);
```
**Fix:** Store as integer percentage `'vat_rate' => 10` and compute as `$vatFils = (int) round($taxable * $vatRate / 100)`. This is consistent with how `vat_rate` is stored in `invoice_items` (integer 10 = 10%).

### ARC-3: ProductService::createProduct has no DB::transaction for multi-step create

**File:** `app/Modules/Catalog/Services/ProductService.php:95-115`
**Rule:** CLAUDE.md -- "Database transactions on multi-step operations"
**Current:** Creates Product, then loops creating Variants + InventoryItems, then syncs tags -- all without a transaction. A failure mid-way (e.g., duplicate SKU on second variant) leaves orphaned records.
**Fix:** Wrap the entire `createProduct` body in `DB::transaction(function () { ... })`.

---

## Bugs [BLOCKING]

### BUG-1: CartResource triggers N+1 query on coupon every time show() is called

**File:** `app/Modules/Cart/Resources/CartResource.php:24`
**Rule:** No N+1 queries
**Current:**
```php
$coupon = $this->coupon_code ? $this->coupon()->first() : null;
```
This executes a query inside the Resource's `toArray()`. The `coupon` relationship is never eager-loaded by the controller.
**Fix:** Either eager-load `coupon` in the controller (`$cart->loadMissing('items.variant.product', 'coupon')`) or use `$this->whenLoaded('coupon')`.

### BUG-2: Catalog CRUD routes (products, categories, attributes) have no auth middleware

**File:** `app/Modules/Catalog/routes.php:10-52`
**Rule:** Security -- write operations must be authenticated/authorized
**Current:** `POST/PUT/DELETE` for products, categories, and attributes are open to unauthenticated requests. Only bundle options and downloads are behind `auth:sanctum`.
**Fix:** Wrap all mutating routes (`POST`, `PUT`, `PATCH`, `DELETE`) in `auth:sanctum` middleware. Read routes (`GET`) can remain public. Add admin authorization (policy or gate) for write operations.

---

## Quality Issues [SHOULD FIX]

### Q-1: AttributeController validates inline instead of using FormRequests

**File:** `app/Modules/Catalog/Controllers/AttributeController.php:31, 54, 80, 100`
**Rule:** CLAUDE.md -- "Form Requests handle ALL validation -- never validate in controllers"
**Current:** `store()`, `update()`, `storeValue()`, `updateValue()` all use `$request->validate([...])` inline.
**Fix:** Create `StoreAttributeRequest`, `UpdateAttributeRequest`, `StoreAttributeValueRequest`, `UpdateAttributeValueRequest` FormRequest classes.

### Q-2: CouponController::validate() validates inline instead of using FormRequest

**File:** `app/Modules/Cart/Controllers/CouponController.php:38-40`
**Rule:** CLAUDE.md -- "Form Requests handle ALL validation"
**Current:** `$request->validate(['code' => [...], 'subtotal_fils' => [...]])` inline.
**Fix:** Create a `ValidateCouponRequest` FormRequest.

### Q-3: ProductController::storeBundleOption and addBundleOptionProduct validate inline

**File:** `app/Modules/Catalog/Controllers/ProductController.php:94, 114`
**Rule:** CLAUDE.md -- "Form Requests handle ALL validation"
**Current:** Uses `$request->validate([...])` inline for bundle option and downloadable link creation.
**Fix:** Create `StoreBundleOptionRequest`, `AddBundleOptionProductRequest`, `StoreDownloadableLinkRequest` FormRequest classes.

### Q-4: ProductService::listProducts uses whereRaw with PostgreSQL-specific cast

**File:** `app/Modules/Catalog/Services/ProductService.php:56`
**Rule:** No raw SQL bypassing Eloquent; also breaks test portability with SQLite
**Current:**
```php
$sub->whereRaw('LOWER(name::text) LIKE ?', [$term])
```
**Fix:** Use `$sub->where('name', 'ilike', $term)` on PostgreSQL or use JSON column search. This `name::text` cast is PostgreSQL-specific and will fail on SQLite test databases.

### Q-5: CartController::applyCoupon returns raw coupon data instead of using PublicCouponResource

**File:** `app/Modules/Cart/Controllers/CartController.php:104-109`
**Rule:** CLAUDE.md -- "API Resources handle ALL JSON responses -- never return Eloquent models directly"
**Current:** Manually builds coupon array inline instead of using the existing `PublicCouponResource`.
**Fix:** Use `'coupon' => new PublicCouponResource($result['coupon'])`.

### Q-6: AttributeController::storeValue and updateValue return raw model data

**File:** `app/Modules/Catalog/Controllers/AttributeController.php:93, 110`
**Rule:** CLAUDE.md -- "API Resources handle ALL JSON responses"
**Current:** `return response()->json($value, 201)` and `return response()->json($updated)` return raw Eloquent models.
**Fix:** Create an `AttributeValueResource` and use it.

### Q-7: CartController::addItem and updateItem return mixed Resource + raw array

**File:** `app/Modules/Cart/Controllers/CartController.php:51-55, 67-71`
**Rule:** API Resources for all JSON responses
**Current:** Returns `CartItemResource` for the item but `cartSummary()` is a raw array. The pattern is inconsistent with the `show()` endpoint which uses `CartResource`.
**Fix:** Return a full `CartResource` response for consistency, or create a dedicated response structure using a Resource.

### Q-8: DownloadService not registered as singleton in CatalogServiceProvider

**File:** `app/Modules/Catalog/CatalogServiceProvider.php`
**Rule:** Consistency -- all other services are registered as singletons
**Current:** `DownloadService` and `CompareService` are not registered in the service provider. They work via auto-resolution but lack explicit registration.
**Fix:** Add `$this->app->singleton(DownloadService::class)` and `$this->app->singleton(CompareService::class)`.

---

## Minor Notes [OPTIONAL]

### M-1: CartMerged event fires after transaction but references deleted guestCart

**File:** `app/Modules/Cart/Services/CartService.php:231-237`
**Detail:** `CartMerged::dispatch()` uses `$guestCart->session_id` after the guest cart has been deleted inside the transaction. This works because the in-memory model still holds the attribute, but `SerializesModels` will fail to re-hydrate the deleted cart if a queued listener tries to access it. Currently only sync listeners are registered, so no bug yet, but will break if a queued listener is added.

### M-2: CategoryController::store creates CategoryImage directly instead of delegating to service

**File:** `app/Modules/Catalog/Controllers/CategoryController.php:38-43`
**Detail:** The controller creates a `CategoryImage` model directly. This is minor business logic that should live in `CategoryService::createCategory()`.

### M-3: Cart config `max_quantity_per_item` default 10 vs AddToCartRequest max:100 mismatch

**File:** `config/cart.php:13` vs `app/Modules/Cart/Requests/AddToCartRequest.php:22`
**Detail:** The FormRequest allows `quantity` up to 100, but `CartService::assertQuantity()` enforces the config default of 10. The request-level max should match the config or be removed (service already enforces it).

### M-4: Product model lacks factory (HasFactory trait not used)

**File:** `app/Modules/Catalog/Models/Product.php`
**Detail:** `Product` does not use `HasFactory`. The `Coupon` model does. If factories exist for testing, the trait should be added for consistency.

### M-5: `lowest_price_fils` eager-evaluates even when conditionally included

**File:** `app/Modules/Catalog/Resources/ProductResource.php:24-28`
**Detail:**
```php
'lowest_price_fils' => $this->when(
    $this->relationLoaded('variants'),
    $this->lowest_price_fils  // <-- evaluates regardless of condition
),
```
The value argument is eagerly evaluated. If `variants` is not loaded, `lowest_price_fils` still runs and returns `base_price_fils`. Use a closure: `fn () => $this->lowest_price_fils`.

---

## VERDICT: REQUEST CHANGES (5 blocking issues)

**Blocking issues requiring resolution before merge:**
1. ARC-1: Cross-module constructor injection violates module boundary rules
2. ARC-2: VAT rate as float violates integer-only money convention
3. ARC-3: Multi-step product creation without DB transaction
4. BUG-1: N+1 query in CartResource
5. BUG-2: Unprotected Catalog CRUD routes (no auth on write endpoints)

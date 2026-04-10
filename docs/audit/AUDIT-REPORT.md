# Souq Backend Audit Report — 2026-04-10

> 8 parallel audit tracks, 12 modules, 81 migrations, 428 tests reviewed

---

## Critical (fix before production)

| # | Finding | Module | File | Track |
|---|---------|--------|------|-------|
| C1 | **Catalog CRUD endpoints completely unprotected** — all POST/PUT/DELETE on products, categories, variants, attributes have zero `auth:sanctum`. Anyone can modify the entire catalog. | Catalog | `Catalog/routes.php` | 2,4 |
| C2 | **Events dispatched inside `DB::transaction()` in 5 places** — OrderPlaced, GenerateInvoiceJob, PaymentCaptured, PaymentFailed, InvoiceGenerated all fire inside transactions. If tx rolls back, queued listeners execute against stale/missing data. | Orders, Payments | `OrderService:184,190` `PaymentService:131,146` `InvoiceService:130` | 1,5 |
| C3 | **Events dispatched inside `DB::transaction()` in AddressService** — AddressAdded, AddressUpdated fire inside transaction closures. | Customers | `AddressService:48-58,68-90` | 6 |
| C4 | **`ReleaseInventoryReservationJob` silently fails** — `isCancellable()` rejects `failed` status, so inventory stays reserved permanently on failed orders. | Orders | `ReleaseInventoryReservationJob` | 5 |
| C5 | **`Mail::send()` instead of `Mail::queue()` in 5 of 6 notification listeners** — blocks request thread, defeats purpose of queued listeners. | Notifications | `Send*Email.php` (5 files) | 7 |
| C6 | **Guest order emails crash** — 4 mailables use `$this->order->user->email` without null-safe operator. Fatal error on guest orders. | Notifications | `OrderConfirmationMail`, `PaymentReceiptMail`, `ShippingUpdateMail`, `InvoiceMail` | 7 |
| C7 | **`ProductGroupVisibility` missing `$table`** — Laravel looks for `product_group_visibilities` but migration creates `product_group_visibility`. Runtime crash. | Customers | `ProductGroupVisibility.php` | 3 |

---

## High (fix before first client)

| # | Finding | Module | File | Track |
|---|---------|--------|------|-------|
| H1 | **No admin role check on Customer Group CRUD** — any authenticated customer can create/delete groups and set variant pricing. | Customers | `Customers/routes.php:48-53` | 2,6 |
| H2 | **Payment result processes charge before ownership check** — `handleChargeResult()` runs DB updates before verifying user owns the payment. | Payments | `PaymentController:69-103` | 2 |
| H3 | **Sanctum tokens never expire** — `config/sanctum.php` has `expiration => null`. Leaked tokens grant permanent access. | Auth | `config/sanctum.php:50` | 2 |
| H4 | **Possible double-hashing passwords** — User model has `'password' => 'hashed'` cast AND AuthService calls `Hash::make()`. Needs verification — if active, registration is broken. | Customers | `User.php:43` + `AuthService:35` | 2 |
| H5 | **Cross-module constructor injection** in OrderService (CartService, ShippingService), CartService (CustomerGroupService, PromotionService), ShippingController (CartService). Should use `app()`. | Orders, Cart, Shipping | Multiple | 1,6 |
| H6 | **No `DB::transaction()` on ProductService::createProduct** — creates Product, Variants, InventoryItems, syncs tags without transaction. Partial failure leaves orphans. | Catalog | `ProductService::createProduct` | 4 |
| H7 | **Missing `DB::transaction()` on cancelOrder, fulfillOrder, overrideOrderStatus** — multi-step writes without atomicity. | Orders | `OrderService` | 5 |
| H8 | **`EarnPointsJob` lacks idempotency guard** — ShouldBeUnique prevents concurrent dispatch but not re-execution after lock expiry. Retries double-credit points. | Loyalty | `EarnPointsJob` | 7 |
| H9 | **No `InventoryMovement` recorded on return restock or payment failure release** — audit ledger has gaps. | Inventory | Missing listener | 7 |
| H10 | **`User::wishlists()` is `HasMany` but DB has UNIQUE on `user_id`** — should be `HasOne`. Second create throws exception. | Customers | `User.php` | 3 |
| H11 | **N+1 query in CartResource** — `$this->coupon()->first()` fires a query on every serialization. Never eager-loaded. | Cart | `CartResource` | 4 |
| H12 | **N+1 queries in PromotionService** — usage checks fire 2N queries per cart calculation. | Promotions | `PromotionService:36-41` | 6 |
| H13 | **`WishlistService::moveItemToCart` doesn't remove item from wishlist** — logic bug. | Customers | `WishlistService` | 6 |
| H14 | **BOGO discount uses `line_total_fils` instead of unit price** — inflated discounts. | Promotions | `PromotionService` | 6 |
| H15 | **`MarkOrderRefundedOnOrderRefunded` doesn't capture `$oldStatus` before update** — status history records wrong value. | Orders | Listener | 5 |
| H16 | **Currency module marked complete but doesn't exist** — CLAUDE.md Phase 3D.3 checkmark is misleading. No migration, model, or service. | Currency | N/A | 3 |
| H17 | **17 untested Catalog endpoints** — Category CRUD, Product list/delete, Variant CRUD, Attribute CRUD have zero test coverage. | Catalog | `tests/` | 8 |

---

## Medium (fix in next sprint)

| # | Finding | Module | Track |
|---|---------|--------|-------|
| M1 | VAT rate stored as float `0.10` in `config/cart.php` — should be integer `10` per CLAUDE.md | Cart | 4 |
| M2 | `CreateReturnRequest` validates `order_item_id` exists in ANY order, not the specific order | Returns | 7 |
| M3 | Return window uses `created_at` not delivery date — unfair to late deliveries | Returns | 7 |
| M4 | Missing FormRequests: AttributeController (4 methods), ProductController (3 bundle/download), CouponController | Catalog, Cart | 1 |
| M5 | Raw Eloquent models returned: AttributeController storeValue/updateValue, WishlistController moveToCart/shareToken | Catalog, Customers | 1 |
| M6 | Business logic in controllers: CategoryController (images), AttributeController (delete), VariantController (update/delete) | Catalog | 1 |
| M7 | Shipping rate cache ignores cart content changes — stale FreeThresholdCarrier results | Shipping | 6 |
| M8 | `LoyaltyTransaction` and `InventoryMovement` missing datetime cast on `created_at` | Loyalty, Inventory | 3 |
| M9 | Missing indexes on `cart_abandonments.cart_id` and `shipments.created_by` FK columns | DB | 3 |
| M10 | `ReturnRequest` and `ReturnRequestItem` have no `$casts` — money fields, integers uncast | Returns | 3 |
| M11 | `CouponUsage` model missing `order()` relationship despite `order_id` FK | Cart | 3 |
| M12 | Guest order lookup leaks order existence (404 vs 401 enables enumeration) | Orders | 2 |
| M13 | Login/register rate limit 60/min too generous for credential stuffing | Auth | 2 |
| M14 | No rate limiting on Returns endpoints | Returns | 2 |
| M15 | `points_expiry_days` config exists but `expires_at` never set — expiry feature stubbed not wired | Loyalty | 7 |
| M16 | PaymentService cross-calls OrderService directly instead of events | Payments | 5 |
| M17 | `config/database.php` defaults to `sqlite` not `pgsql` — risky if `.env` missing in prod | Config | 3 |
| M18 | Only 5 factories for 50+ models — `makeVariantWithStock()` copy-pasted across 15 test files (~300 lines) | Tests | 8 |
| M19 | Pest vs PHPUnit mismatch — CLAUDE.md says Pest v3 but all 428 tests use PHPUnit class syntax | Tests | 8 |

---

## Low (nice to have)

| # | Finding | Track |
|---|---------|-------|
| L1 | 3 duplicate test stubs in `tests/Feature/Feature/Customers/` — delete | 8 |
| L2 | OrderFactory exists but unused — tests use `Order::create()` directly | 8 |
| L3 | `ProductService::listProducts` uses PostgreSQL-specific `whereRaw('LOWER(name::text)')` | 1 |
| L4 | OrderController::index builds pagination meta manually instead of Resource collection | 1 |
| L5 | `$admin` parameter unused in `approveReturn()`/`rejectReturn()` — no audit trail | 7 |
| L6 | `manualAdjust` can push `points_balance` negative — no guard unlike `redeemPoints()` | 7 |
| L7 | 6 tables with no Eloquent models (store_hours, closures, notification_preferences, etc.) | 3 |
| L8 | Extensive `enum` columns requiring fragile ALTER TABLE for expansion | 3 |
| L9 | No IP restriction or 2FA on Filament admin for production | 2 |
| L10 | `InventoryMovement` `quantity_after` computed outside transaction — race condition risk | 7 |

---

## Test Coverage Summary

| Module | Total Endpoints | Tested | Untested | Coverage |
|--------|----------------|--------|----------|----------|
| Catalog | 25+ | 8 | **17** | ~32% |
| Cart | 10 | 8 | 2 | 80% |
| Customers | 20+ | 18 | 2 | ~90% |
| Orders | 7 | 7 | 0 | 100% |
| Payments | 5 | 5 | 0 | 100% |
| Shipping | 1 | 1 | 0 | 100% |
| Promotions | 1 | 1 | 0 | 100% |
| Returns | 2 | 2 | 0 | 100% |
| Settings | 0 (admin) | N/A | N/A | N/A |
| Inventory | 0 (events) | N/A | N/A | N/A |
| Loyalty | 0 (events) | N/A | N/A | N/A |
| Notifications | 0 (events) | N/A | N/A | N/A |

**Biggest gap: Catalog module at ~32% endpoint coverage.**

---

## Missing Factories

These models need factories (currently only User, Coupon, Order, Refund, TapTransaction exist):

**High priority:** Product, Variant, Category, InventoryItem, Cart, CartItem, CustomerAddress, CustomerProfile
**Medium priority:** ShippingZone, ShippingMethod, PromotionRule, PromotionCondition, PromotionAction, ReturnRequest, ReturnRequestItem
**Lower priority:** Wishlist, WishlistItem, Invoice, InvoiceItem, Shipment, ShipmentItem, LoyaltyAccount, LoyaltyTransaction, InventoryMovement

---

## What's Solid

The audit found significant strengths worth noting:

- **Currency handling** — fils (integer) used consistently across all money columns
- **Checkout orchestration** — address defense-in-depth, shipping before transaction, `lockForUpdate()` on inventory
- **Tap webhook HMAC** — verified before processing, `hash_equals()`, normalized amounts
- **Invoice compliance** — sequential numbering in same transaction, CR/VAT numbers
- **Modular architecture** — clean module boundaries, service providers, event-driven communication (when used correctly)
- **Rate limiting** — applied on critical endpoints (checkout 10/min, cart 30/min)
- **428 passing tests** — strong foundation to build on

---

## Recommended Fix Order

1. **C1** — Protect Catalog routes (5 min, highest risk)
2. **C4** — Fix `isCancellable()` to include `failed` status (1 line)
3. **C5+C6** — Fix notification listeners (`Mail::queue`, null-safe operator)
4. **C7** — Add `$table` to `ProductGroupVisibility`
5. **C2+C3** — Move events outside `DB::transaction()` (systematic, same pattern)
6. **H1** — Add admin middleware to Customer Group routes
7. **H2** — Move ownership check before charge processing
8. **H3** — Set Sanctum token expiration
9. **H4** — Verify password double-hashing (test register→login flow)
10. Everything else by priority

# Track 8: Test Suite Audit Findings

**Audited:** 2026-04-10
**Test count:** 428 tests (per CLAUDE.md)
**Test framework:** PHPUnit class syntax (NOT Pest syntax -- see Section 4)

---

## 1. Coverage Gap Summary

### Endpoint vs Test Coverage Matrix

| Module | Endpoint | Method | Test File | Tested? |
|--------|----------|--------|-----------|---------|
| **Customers** | `auth/register` | POST | AuthControllerTest | YES (happy + 422) |
| | `auth/login` | POST | AuthControllerTest | YES (happy + 401) |
| | `auth/forgot-password` | POST | AuthControllerTest | YES (happy + unknown email) |
| | `auth/reset-password` | POST | AuthControllerTest | YES (happy + invalid token) |
| | `auth/logout` | POST | AuthControllerTest | YES |
| | `auth/me` | GET | AuthControllerTest | YES (happy + 401) |
| | `customers/profile` | GET | ProfileControllerTest | YES (happy + 401 + auto-create) |
| | `customers/profile` | PUT/PATCH | ProfileControllerTest | YES (happy + 422) |
| | `customers/change-password` | POST | ProfileControllerTest | YES (happy + 422) |
| | `customers/addresses` | GET | AddressControllerTest | YES (happy + filter + 401) |
| | `customers/addresses` | POST | AddressControllerTest | YES (happy + event + default) |
| | `customers/addresses/{id}` | PUT | AddressControllerTest | YES (happy + 404 ownership) |
| | `customers/addresses/{id}` | DELETE | AddressControllerTest | YES (happy + last-address guard) |
| | `customers/addresses/{id}/set-default` | PUT | AddressControllerTest | YES |
| | `groups` | GET | CustomerGroupTest | YES |
| | `groups/{id}` | GET | CustomerGroupTest | YES |
| | `groups` | POST | CustomerGroupTest | YES (happy + 401) |
| | `groups/{id}` | PUT | CustomerGroupTest | YES |
| | `groups/{id}` | DELETE | CustomerGroupTest | YES (happy + default guard + 401) |
| | `groups/{id}/prices` | POST | CustomerGroupTest | YES (happy + upsert) |
| | `groups/{id}/prices/{variant}` | DELETE | CustomerGroupTest | YES |
| | `wishlist` | GET | WishlistTest | YES (happy + 401 + auto-create) |
| | `wishlist/items` | POST | WishlistTest | YES (happy + 422 + duplicate) |
| | `wishlist/items/{id}` | DELETE | WishlistTest | YES (happy + 404 + cross-user) |
| | `wishlist/share` | POST | WishlistTest | YES (happy + idempotent) |
| | `wishlists/shared/{token}` | GET | WishlistTest | YES (happy + 404 + private) |
| | `wishlist/items/{id}/move-to-cart` | POST | WishlistTest | YES |
| **Catalog** | `categories` | GET | **NONE** | **NO** |
| | `categories` | POST | **NONE** | **NO** |
| | `categories/{id}` | GET | **NONE** | **NO** |
| | `categories/{id}` | PUT/PATCH | **NONE** | **NO** |
| | `categories/{id}` | DELETE | **NONE** | **NO** |
| | `categories/{id}/products` | GET | **NONE** | **NO** |
| | `products` | GET | **NONE** | **NO** |
| | `products` | POST | ProductTypeTest | Partial (type field only) |
| | `products/{id}` | GET | ProductTypeTest | Partial (type field only) |
| | `products/{id}` | PUT/PATCH | ProductTypeTest | Partial (type update only) |
| | `products/{id}` | DELETE | **NONE** | **NO** |
| | `products/{id}/variants` | GET | **NONE** | **NO** |
| | `products/{id}/variants` | POST | **NONE** | **NO** |
| | `products/{id}/variants/{id}` | GET | **NONE** | **NO** |
| | `products/{id}/variants/{id}` | PUT/PATCH | **NONE** | **NO** |
| | `products/{id}/variants/{id}` | DELETE | **NONE** | **NO** |
| | `search` | GET | SearchTest | YES (validation + functional) |
| | `compare` | POST | CompareTest | YES (thorough) |
| | `attributes` | GET | **NONE** | **NO** |
| | `attributes` | POST | **NONE** | **NO** |
| | `attributes/{id}` | GET | **NONE** | **NO** |
| | `attributes/{id}` | PUT/PATCH | **NONE** | **NO** |
| | `attributes/{id}` | DELETE | **NONE** | **NO** |
| | `attributes/{id}/values` | POST | **NONE** | **NO** |
| | `attributes/{id}/values/{id}` | PUT/PATCH | **NONE** | **NO** |
| | `attributes/{id}/values/{id}` | DELETE | **NONE** | **NO** |
| | `products/{id}/bundle-options` | POST | ProductTypeTest | YES |
| | `products/{id}/bundle-options/{id}/products` | POST | ProductTypeTest | YES |
| | `products/{id}/downloadable-links` | POST | ProductTypeTest | YES |
| | `downloads/{token}` | GET | DownloadTest | YES (thorough) |
| **Cart** | `cart` | GET | CartControllerTest | YES (guest + auth) |
| | `cart/add-item` | POST | CartControllerTest | YES (happy + 422 stock + merge qty) |
| | `cart/items/{id}` | PUT | CartControllerTest | YES (happy + 403 cross-user) |
| | `cart/items/{id}` | DELETE | CartControllerTest | YES |
| | `cart/apply-coupon` | POST | CartControllerTest | YES (happy + 422) |
| | `cart/remove-coupon` | POST | CartControllerTest | YES |
| | `cart/clear` | POST | CartControllerTest | YES |
| | `cart/merge` | POST | CartMergeTest | YES (thorough) |
| | `coupons/active` | GET | **NONE** | **NO** |
| | `coupons/validate` | POST | **NONE** | **NO** |
| **Orders** | `checkout` | POST | CheckoutTest | YES (happy + validation + business rules) |
| | `orders` | GET | OrderListTest | YES (pagination + filter + isolation) |
| | `orders/{num}` | GET | OrderDetailTest | YES (happy + 403 + 404 + history) |
| | `orders/{num}/guest` | GET | OrderDetailTest | YES (happy + wrong email + no guest) |
| | `orders/{num}/cancel` | POST | CancelOrderTest | YES (thorough) |
| | `orders/{num}/invoice` | GET | InvoiceTest | YES (happy + 404 + 403 + 401) |
| | `orders/{num}/shipments` | GET | ShipmentTest | YES (happy + 403 + 401) |
| **Payments** | `payments/charge` | POST | PaymentChargeTest | YES (happy + 401 + 403 + retry + API fail) |
| | `payments/{tx}/refund` | POST | RefundTest | YES (happy + 401 + 403 + 422) |
| | `payments/order/{id}` | GET | OrderPaymentTest | YES (happy + 403 + null) |
| | `payments/result` | GET | PaymentResultTest | YES (captured + failed + idempotent + 403) |
| | `webhooks/tap` | POST | WebhookTest | YES (happy + 400 + 401 signature) |
| **Shipping** | `shipping/rates` | GET | ShippingTest | YES (happy + 401 + 422 + 403) |
| **Promotions** | `promotions/applicable` | GET | PromotionTest | YES (via service tests) |
| **Returns** | `orders/{num}/returns` | GET | ReturnTest | YES |
| | `orders/{num}/returns` | POST | ReturnTest | YES (happy + 422) |

### Summary Table

| Module | Total Endpoints | Tested | Untested | Coverage |
|--------|----------------|--------|----------|----------|
| Customers | 17 | 17 | 0 | 100% |
| Catalog | 24 | 7 | 17 | 29% |
| Cart | 9 | 7 | 2 | 78% |
| Orders | 7 | 7 | 0 | 100% |
| Payments | 5 | 5 | 0 | 100% |
| Shipping | 1 | 1 | 0 | 100% |
| Promotions | 1 | 1 | 0 | 100% |
| Returns | 2 | 2 | 0 | 100% |
| **TOTAL** | **66** | **47** | **19** | **71%** |

---

## 2. Missing Factories

Only **5 factories** exist. The following **45 models** have no factory:

### Critical (used frequently in tests via manual Model::create)
- `Product` -- created manually in 15+ test files with identical boilerplate
- `Variant` -- created manually in 15+ test files
- `Category` -- created manually in 15+ test files
- `InventoryItem` -- created manually in 15+ test files
- `Cart` -- created manually in 8+ test files
- `CartItem` -- created manually in 8+ test files
- `CustomerAddress` -- created manually in 8+ test files
- `Order` -- factory exists but many tests still use raw Order::create()
- `OrderItem` -- created manually in 5+ test files

### Important (used in multiple tests)
- `CustomerProfile`
- `CustomerGroup`
- `VariantGroupPrice`
- `Wishlist`
- `WishlistItem`
- `ShippingZone`
- `ShippingMethod`
- `PromotionRule`
- `PromotionCondition`
- `PromotionAction`
- `PromotionUsage`
- `ReturnRequest`
- `ReturnRequestItem`
- `LoyaltyAccount`
- `LoyaltyConfig`
- `LoyaltyTransaction`
- `InventoryMovement`
- `OrderStatusHistory`
- `Shipment`
- `ShipmentItem`
- `Invoice`
- `InvoiceItem`
- `StoreSetting`

### Lower priority (less test impact)
- `Attribute`
- `AttributeValue`
- `CategoryImage`
- `ProductReview`
- `ProductReviewVote`
- `ProductTag`
- `CartAbandonment`
- `CouponApplicableItem`
- `CouponUsage`
- `BundleOption`
- `BundleOptionProduct`
- `DownloadableLink`
- `DownloadableLinkPurchase`
- `ProductGroupVisibility`
- `OrderShipping`

**Impact:** Every test file duplicates `makeVariantWithStock()` / `makeAddress()` / `makeOrder()` helper methods. A `ProductFactory`, `VariantFactory`, `CategoryFactory`, and `InventoryItemFactory` would eliminate ~300 lines of duplicated helper code.

---

## 3. Test Quality Issues

### 3a. Massive helper duplication [SHOULD FIX]
The `makeVariantWithStock()` helper is copy-pasted identically in **15 test files**:
- CartControllerTest, CartMergeTest, CartPruneJobTest, CheckoutTest, InvoiceTest, ShipmentTest, OrderStatusHistoryTest, CodTest, ShippingTest, CheckoutWithShippingTest, PromotionTest, CustomerGroupTest, WishlistTest, CompareTest, SearchTest

The `makeAddress()` helper is duplicated in **8 test files**.

This is a maintenance hazard. If the Product schema changes (e.g., a new required column), all 15 files break simultaneously.

### 3b. PHPUnit class syntax instead of Pest [BLOCKING consistency]
CLAUDE.md specifies **Pest v3** for testing. However, **100% of tests** use PHPUnit class syntax (`class FooTest extends TestCase` with `public function test_*`). Zero tests use Pest syntax (`it()`, `test()`, `expect()`).

The project has Pest installed (as a dependency), but it is not actually used. All tests are vanilla PHPUnit classes.

### 3c. Tests creating models manually instead of factories [SHOULD FIX]
Nearly every test creates models via `Model::create([...])` with inline data. Examples:
- `CustomerGroup::create([...])` in CustomerGroupTest (11 inline creates)
- `Order::create([...])` in OrderListTest, CancelOrderTest, OrderDetailTest (despite OrderFactory existing)
- `ShippingZone::create([...])` and `ShippingMethod::create([...])` in ShippingTest

### 3d. Missing assertJsonStructure() calls [SHOULD FIX]
Most tests check individual JSON paths but do not validate the full response structure. Only `OrderListTest` uses `assertJsonStructure()`. Endpoints like:
- GET `/cart` -- no structure assertion for response shape
- GET `/wishlist` -- no structure assertion
- POST `/checkout` -- no structure assertion for the full order resource
- GET `/shipping/rates` -- no structure assertion

### 3e. Inconsistent use of OrderFactory [MINOR]
`OrderFactory` exists but is only used in admin tests. Feature tests for orders (OrderListTest, CancelOrderTest, OrderDetailTest, CodTest, InvoiceTest) all manually create orders via `Order::create()` with inline data. The factory is underutilized.

### 3f. Admin tests create User via User::create instead of factory [MINOR]
`AdminPanelAccessTest` uses `User::create([...])` instead of `User::factory()->create()`.

---

## 4. Pest vs PHPUnit Inconsistency [BLOCKING]

**CLAUDE.md says:** "Testing (BE): Pest v3"
**Reality:** All 428 tests use PHPUnit class syntax.

This is technically functional since Pest is a wrapper around PHPUnit and can run PHPUnit tests. However, it means:
- No tests use Pest-specific features (`it()`, `test()`, `expect()`, `describe()`)
- The project does not match its documented testing standard
- New tests should follow whichever convention is chosen -- currently the codebase convention is PHPUnit

**Recommendation:** Either update CLAUDE.md to reflect "PHPUnit class syntax via Pest runner" or migrate tests to Pest syntax. Do NOT mix both.

---

## 5. Duplicate Test Stubs [DELETE]

Three empty stub files exist under `tests/Feature/Feature/Customers/` (double-nested due to artisan make:test bug documented in CLAUDE.md):

1. `tests/Feature/Feature/Customers/AuthControllerTest.php`
2. `tests/Feature/Feature/Customers/AddressControllerTest.php`
3. `tests/Feature/Feature/Customers/ProfileControllerTest.php`

These contain only a class declaration extending TestCase with no test methods. They should be deleted. The real tests live in `tests/Feature/Customers/`.

---

## 6. Missing Test Scenarios (from CLAUDE.md Gotchas)

### 6a. Currency arithmetic (fils, not float) [COVERED]
- VAT calculation tested in CartControllerTest (`test_cart_totals_include_correct_vat`)
- Invoice VAT tested in InvoiceTest (multiple tests)
- All amounts consistently use `_fils` suffix integers
- No float/decimal currency usage found in tests

### 6b. Guest checkout (null user) [PARTIALLY COVERED]
- `CheckoutTest::test_guest_can_checkout_with_email` exists but **asserts 401** because the checkout route requires `auth:sanctum`. The test documents this limitation but does not test actual guest checkout flow.
- `OrderDetailTest::test_guest_can_view_order_with_correct_email` covers guest order lookup.
- `CodTest::test_cod_collected_mail_uses_guest_email_when_no_user` covers guest email fallback.
- **Gap:** No test for actual guest checkout (if the route ever gets opened up)

### 6c. Cart abandonment with nullOnDelete [PARTIALLY COVERED]
- `CartPruneJobTest` tests prune command with abandonment recording.
- **Gap:** No explicit test verifying that deleting a Cart sets `cart_abandonments.cart_id` to NULL (the nullOnDelete behavior from the migration).

### 6d. Webhook idempotency [PARTIALLY COVERED]
- `PaymentResultTest::test_idempotent_handler_skips_already_captured` tests idempotency on the result endpoint.
- **Gap:** No test for `ProcessTapWebhookJob` idempotency (ShouldBeUnique + uniqueId). WebhookTest only verifies the job is dispatched, not that duplicate webhooks are deduplicated.

### 6e. Promotion exclusive rule priority [NOT FOUND]
- PromotionTest covers condition types and action types thoroughly.
- **Gap:** No explicit test verifying that exclusive rules with lower priority numbers stop evaluation (the "exclusive rules stop evaluation on first match" behavior per CLAUDE.md). Need to verify `is_exclusive` flag behavior.

### 6f. lockForUpdate() on inventory operations [NOT TESTED]
- CLAUDE.md requires `lockForUpdate()` for all inventory decrement operations.
- **Gap:** No test verifies that concurrent inventory decrements are serialized. This would require a concurrency/race-condition test.

### 6g. Events dispatched OUTSIDE DB::transaction [NOT TESTED]
- CLAUDE.md says "Events must be dispatched OUTSIDE DB::transaction."
- **Gap:** No test verifies event dispatch timing relative to transactions. All tests use `Event::fake()` which bypasses this concern entirely.

---

## 7. Untested Endpoints (19 total)

### Catalog Module (17 untested endpoints) -- CRITICAL GAP
The entire Category CRUD (6 endpoints), Product listing/delete (2 endpoints), Variant CRUD (5 endpoints), and Attribute CRUD (4 endpoints) have **zero test coverage**. This is the largest gap in the test suite.

These are public-facing, unauthenticated endpoints that form the core of the storefront.

### Cart Module (2 untested endpoints)
- `GET /coupons/active` -- list active coupons (public)
- `POST /coupons/validate` -- validate a coupon code

---

## 8. Positive Observations

- **Auth flow:** Thoroughly tested including edge cases (duplicate email, password mismatch, token reset)
- **Ownership isolation:** Consistently tested across modules (orders, addresses, wishlists, payments)
- **Event dispatching:** All major events are verified with `Event::assertDispatched()`
- **DB assertions:** Tests use `assertDatabaseHas()` / `assertDatabaseMissing()` extensively
- **Error paths:** Good coverage of 401, 403, 404, 422 responses
- **Business rules:** Cart merge, coupon application, VAT calculation, order number format, shipment lifecycle all well covered
- **RefreshDatabase:** Used consistently in all test classes
- **Shipping module:** Excellent coverage including carrier unit tests, virtual cart skip, wrong zone rejection

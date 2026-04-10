# Backend Code Audit — Comprehensive Review Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:dispatching-parallel-agents to run independent audit tracks simultaneously. Each track is a self-contained subagent task. After all tracks complete, a consolidation task merges findings.

**Goal:** Audit the entire Souq backend (12 modules, 81 migrations, ~406 tests, 428 passing) for code quality, Laravel best practices, security, logic correctness, and test coverage — then produce a prioritized list of fixes.

**Architecture:** The audit is split into 8 independent tracks that can run in parallel. Each track produces a findings file in `docs/audit/`. A final consolidation task merges all findings into a single prioritized report.

**Tech Stack:** Laravel 13, PHP 8.4, Pest v3, Sanctum v4, Filament v5, PostgreSQL 17 (SQLite for tests), Redis 7

**References used:**
- Laravel 13 official docs (FormRequests, API Resources, Sanctum, testing)
- JustSteveKing (Steve McDougall) — API architecture, DTOs, Action pattern, Query classes
- Laravel Boost guidelines — `pint --dirty`, `make:` commands, feature tests, factories
- Bahrain ecommerce compliance (VAT 10%, PDPL, Resolution 43)
- CLAUDE.md project rules (currency in fils, zero-downtime migrations, thin controllers)

---

## Audit Structure — 8 Parallel Tracks

```
Track 1: Architecture & Laravel Patterns     (read-only, cross-module)
Track 2: Security & Auth                     (read-only, cross-module)
Track 3: Database & Migrations               (read-only, schema-focused)
Track 4: Module Logic — Catalog + Cart       (read-only, 2 modules)
Track 5: Module Logic — Orders + Payments    (read-only, 2 modules)
Track 6: Module Logic — Customers + Shipping + Promotions  (read-only, 3 modules)
Track 7: Module Logic — Returns + Loyalty + Inventory + Notifications  (read-only, 4 modules)
Track 8: Test Coverage & Quality             (read-only, tests/ directory)
```

Each track outputs: `docs/audit/track-N-findings.md`
Final consolidation: `docs/audit/AUDIT-REPORT.md`

---

## Track 1: Architecture & Laravel Patterns

### Task 1.1: Controller thickness audit

**Files to read:**
- Every `Controllers/*.php` in each module under `app/Modules/`
- `app/Http/Middleware/ForceJsonResponse.php`
- `bootstrap/app.php`

**Checklist — flag violations of:**

- [ ] **Step 1: Thin controllers** — Controllers must ONLY handle HTTP concerns (receive request, call service, return resource). Flag any controller that:
  - Contains business logic (calculations, conditionals beyond simple delegation)
  - Queries the database directly (any `Model::where()` or `DB::` call)
  - Contains more than ~15 lines per method
  - Validates inline (should use FormRequest)

- [ ] **Step 2: Service layer consistency** — Every controller action should delegate to a Service. Flag:
  - Controllers that skip the service layer
  - Services that call other services directly (should use events for cross-module)
  - Services that access `$request` (should receive validated data, not the request object)
  - Services injected via constructor across modules (should use `app()` per CLAUDE.md)

- [ ] **Step 3: FormRequest usage** — All validation must be in FormRequest classes. Flag:
  - Any `$request->validate()` in controllers
  - Missing `authorize()` returning `true` (or actual authorization logic)
  - FormRequests not using `$request->validated()` to pass data

- [ ] **Step 4: API Resource consistency** — Never return raw Eloquent models. Flag:
  - Any `return $model` or `return Model::find()` without wrapping in a Resource
  - Inconsistent response wrapping (some with `->additional()`, some without)
  - Missing `->response()->setStatusCode()` where needed (201 for creates)
  - Resources not using `whenLoaded()` for optional relationships

- [ ] **Step 5: Event system audit** — Cross-reference `CLAUDE.md` event map vs actual code. Flag:
  - Events listed in CLAUDE.md but not dispatched anywhere
  - Events dispatched inside `DB::transaction()` (must be outside per CLAUDE.md)
  - Listeners that call services from other modules directly (should only listen)
  - Missing listeners for events that should have them

- [ ] **Step 6: Write findings to `docs/audit/track-1-findings.md`**

Format each finding as:
```
### [SEVERITY: HIGH|MEDIUM|LOW] Finding title
**File:** path/to/file.php:line
**Rule:** Which best practice is violated
**Current:** What the code does now
**Fix:** What it should do
```

---

## Track 2: Security & Auth

### Task 2.1: Authentication & authorization audit

**Files to read:**
- `bootstrap/app.php`
- `app/Http/Middleware/ForceJsonResponse.php`
- `app/Modules/Customers/Services/AuthService.php`
- `app/Modules/Customers/Controllers/AuthController.php`
- All `routes.php` files in every module
- `config/sanctum.php`, `config/auth.php`
- `app/Providers/AppServiceProvider.php`

**Checklist:**

- [ ] **Step 1: Route protection** — Every endpoint that modifies data or reads private data must have `auth:sanctum`. Flag:
  - Unprotected routes that should be protected
  - Admin-only routes accessible to regular users (check for role/permission middleware)
  - Missing rate limiting on sensitive endpoints (login, register, checkout, payments)
  - Rate limit values: auth 60/min, checkout 10/min, add-to-cart 30/min per CLAUDE.md

- [ ] **Step 2: Ownership scoping** — Users must only access their own data. Flag:
  - Endpoints where `$request->user()->id` is not checked against the resource owner
  - Address endpoints not scoping by user
  - Order endpoints not scoping by user
  - Wishlist endpoints not scoping by user
  - Missing ownership check on update/delete operations

- [ ] **Step 3: Tap Payments security** — Per CLAUDE.md:
  - Webhook HMAC-SHA256 verification happens BEFORE any processing
  - `hash_equals()` used (not `===`) for timing-safe comparison
  - Amount normalized to 3 decimal places before hash
  - Full `tap_response` JSON stored for debugging
  - No payment amounts or card data logged anywhere
  - Webhook secret bypass only in non-production

- [ ] **Step 4: Input sanitization** — Check all FormRequests for:
  - SQL injection vectors (raw `DB::raw()` with user input)
  - XSS vectors (user input stored and returned without escaping)
  - Mass assignment protection (`$fillable` or `$guarded` on all models)

- [ ] **Step 5: Write findings to `docs/audit/track-2-findings.md`**

---

## Track 3: Database & Migrations

### Task 3.1: Migration safety & schema audit

**Files to read:**
- All 81 migrations in `database/migrations/`
- All models in every module (check `$table`, `$casts`, `$fillable`)
- `config/database.php`

**Checklist:**

- [ ] **Step 1: Zero-downtime migration rules** — Per CLAUDE.md, flag violations:
  - Column renames in a single migration (must be add → copy → drop across releases)
  - Non-nullable columns added without defaults
  - Columns dropped that existing code still reads
  - Missing `down()` rollback methods
  - Missing indexes on foreign keys and filtered columns

- [ ] **Step 2: Currency storage** — ALL money amounts must be integer fils (1 BHD = 1000 fils). Flag:
  - Any column storing money as `decimal`, `float`, or `double`
  - Column names not ending in `_fils` for money fields
  - Any code doing float arithmetic on money values
  - Tap API conversion: must use `number_format($fils / 1000, 3, '.', '')`

- [ ] **Step 3: Model-migration alignment** — For each model, verify:
  - `$table` is set if table name is irregular
  - `$casts` matches column types in migration (especially JSON/JSONB, booleans, dates)
  - `$fillable` covers all user-writable columns
  - Relationships match foreign keys in migrations
  - No missing indexes on frequently queried columns

- [ ] **Step 4: VAT storage** — `vat_rate` must be stored as integer percentage (10 = 10%), not decimal

- [ ] **Step 5: PostgreSQL vs SQLite compatibility** — Flag:
  - CHECK constraints that use `ALTER TABLE ... DROP CONSTRAINT` without `if (DB::getDriverName() === 'pgsql')` guard
  - JSONB-specific syntax without SQLite fallback
  - Enum columns that might behave differently

- [ ] **Step 6: Write findings to `docs/audit/track-3-findings.md`**

---

## Track 4: Module Logic — Catalog + Cart

### Task 4.1: Catalog module review

**Files to read:**
- All files under `app/Modules/Catalog/`
- `tests/Feature/Catalog/`

**Checklist:**

- [ ] **Step 1: Product types** — Simple, Bundle, Downloadable product handling:
  - Virtual products skip shipping requirement
  - Bundle options correctly validated
  - Download links generate secure tokens
  - `product_type` enum consistency between migration and code

- [ ] **Step 2: Variant handling** — `variants.attributes` is JSONB NOT NULL:
  - All Variant::create() calls include `'attributes' => []`
  - Variant stock checking uses `lockForUpdate()` per CLAUDE.md
  - Price stored in fils (integer)

- [ ] **Step 3: Search** — Scout/Meilisearch integration:
  - Search is throttled (120/min per routes)
  - SearchRequest validates input
  - Fallback to collection driver when Meilisearch unavailable

### Task 4.2: Cart module review

**Files to read:**
- All files under `app/Modules/Cart/`
- `tests/Feature/Cart/`
- `config/cart.php`

**Checklist:**

- [ ] **Step 4: Cart calculations** — Verify:
  - All prices in fils (integer arithmetic, never float)
  - VAT calculated at 10% correctly
  - Coupon discounts applied before promotion discounts (per CLAUDE.md: promotion discount on post-coupon subtotal)
  - Cart totals recalculated on every change (item add/remove/update, coupon apply/remove)

- [ ] **Step 5: Cart abandonment** — `cart_abandonments.cart_id` is nullable with `nullOnDelete()`:
  - Records survive cart pruning
  - PruneExpiredCartsCommand handles edge cases

- [ ] **Step 6: Guest cart merge** — On login/register:
  - Guest cart items merge into user cart
  - Duplicate items increase quantity (not duplicate rows)
  - CartMerged event fires

- [ ] **Step 7: Write findings to `docs/audit/track-4-findings.md`**

---

## Track 5: Module Logic — Orders + Payments

### Task 5.1: Checkout flow review

**Files to read:**
- All files under `app/Modules/Orders/`
- All files under `app/Modules/Payments/`
- `tests/Feature/Orders/`, `tests/Feature/Payments/`

**Checklist:**

- [ ] **Step 1: Checkout orchestration** — The most critical flow. Verify:
  - Shipping rate computed BEFORE `DB::transaction()` (per CLAUDE.md)
  - Inventory reservation happens inside transaction with `lockForUpdate()`
  - `ShippingService::attachShippingToOrder()` called AFTER transaction closes
  - Events dispatched OUTSIDE `DB::transaction()`
  - `$result = DB::transaction(...)` used (not `return DB::transaction(...)`) when post-tx work follows
  - Address ownership checked in BOTH CheckoutRequest AND OrderService (defense-in-depth)

- [ ] **Step 2: Order status flow** — Must follow: `pending → initiated → paid | failed → refunded`
  - `$oldStatus = $order->order_status` captured BEFORE `$order->update()` (Eloquent syncOriginal caveat)
  - Status history recorded for every transition
  - OrderCancelled properly releases inventory and coupon usage

- [ ] **Step 3: Invoice compliance** — Per CLAUDE.md Bahrain rules:
  - Invoice sequence increment + invoice creation in SAME DB transaction
  - Sequential invoice numbers (no gaps)
  - CR number, VAT registration, sequential number on every invoice
  - `getNextInvoiceSequence()` throws if row missing

- [ ] **Step 4: Tap Payments flow** — Verify:
  - Charge creation sends correct amount format (`number_format($fils / 1000, 3, '.', '')`)
  - `src_all` used for all payment methods
  - Webhook job is `ShouldBeUnique` with `uniqueId()` (Tap retries twice)
  - Full `tap_response` JSON stored
  - Refunds tracked in `refunds` table with partial support
  - Payment result endpoint checks `$transaction->order->user_id === $request->user()->id`

- [ ] **Step 5: COD handling** — Cash on delivery:
  - COD orders skip Tap Payments flow
  - Uses `CodCollectedMail` not `PaymentReceiptMail`
  - `CODCollected` event fires correctly

- [ ] **Step 6: Idempotent jobs** — ALL queue jobs must check state before acting:
  - `ProcessTapWebhookJob` — won't double-process same webhook
  - `GenerateInvoiceJob` — won't create duplicate invoices
  - `CheckStalePaymentsJob` — safe to run multiple times
  - `ReleaseInventoryReservationJob` — checks if already released

- [ ] **Step 7: Write findings to `docs/audit/track-5-findings.md`**

---

## Track 6: Module Logic — Customers + Shipping + Promotions

### Task 6.1: Customers module review

**Files to read:**
- All files under `app/Modules/Customers/`
- `tests/Feature/Customers/`

**Checklist:**

- [ ] **Step 1: Auth flow** — Verify Sanctum token-based auth:
  - Login creates token via `createToken()`, returns `plainTextToken`
  - Logout revokes `currentAccessToken()`
  - Register creates user + profile + fires `CustomerRegistered`
  - Password reset uses secure tokens
  - Guest orders: `$order->user?->email ?? $order->guest_email` everywhere

- [ ] **Step 2: Address validation** — Bahrain-specific:
  - `CustomerAddress` has no `country` column — hardcoded `'BH'` in ShippingService
  - Required fields: `address_type`, `recipient_name`, `phone`, `governorate`, `street_address`

### Task 6.2: Shipping module review

**Files to read:**
- All files under `app/Modules/Shipping/`
- `tests/Feature/Shipping/`

- [ ] **Step 3: Carrier strategy pattern** — FlatRate, FreeThreshold, Aramex/DHL stubs:
  - `ShippingCarrierFactory` resolves correct carrier
  - `ShippingCarrierInterface` defines consistent contract
  - Virtual cart (all `product_type = 'virtual'`) skips shipping entirely

### Task 6.3: Promotions module review

**Files to read:**
- All files under `app/Modules/Promotions/`
- `tests/Feature/Promotions/`

- [ ] **Step 4: Promotion rule engine** — Verify:
  - Rules ordered by `priority ASC`
  - Exclusive rules stop evaluation on first match
  - `PromotionAction.value` is JSONB — correct structure per action type
  - `PromotionUsage::recordUsage()` only at checkout, NEVER during `calculateTotals()`
  - Discount on post-coupon subtotal, not raw subtotal

- [ ] **Step 5: Write findings to `docs/audit/track-6-findings.md`**

---

## Track 7: Module Logic — Returns + Loyalty + Inventory + Notifications

### Task 7.1: Returns/RMA review

**Files to read:**
- All files under `app/Modules/Returns/`
- `tests/Feature/Returns/`

- [ ] **Step 1: RMA flow** — Verify:
  - Sequential RMA numbers (`RMA-YYYY-NNNNNN`)
  - 4 events: ReturnRequested/Approved/Rejected/Completed
  - Inventory restocked on complete (not on approve)
  - Only paid/fulfilled orders can be returned

### Task 7.2: Loyalty points review

**Files to read:**
- All files under `app/Modules/Loyalty/`
- `tests/Feature/Loyalty/`

- [ ] **Step 2: Points economy** — Verify:
  - `EarnPointsJob` is `ShouldBeUnique` with `uniqueId()`
  - Config keys: `points_per_fil`, `fils_per_point`, `max_redeem_percent`, `points_expiry_days`
  - Redemption correctly deducts from balance
  - Points don't expire prematurely (check Carbon usage)

### Task 7.3: Inventory movements review

- [ ] **Step 3: Audit ledger** — Verify:
  - Every stock change creates an `InventoryMovement` record
  - OrderPlaced → reservation movement
  - OrderCancelled → release movement
  - Movements are append-only (never updated/deleted)

### Task 7.4: Notifications review

**Files to read:**
- All files under `app/Modules/Notifications/`
- `tests/Feature/Notifications/`

- [ ] **Step 4: Email handling** — Verify:
  - `Mail::queue()` used (not `Mail::send()`) in queued listeners
  - Guest orders handle null user: `$order->user?->email ?? $order->guest_email`
  - COD uses `CodCollectedMail`, not `PaymentReceiptMail`
  - All mailables have correct view templates

- [ ] **Step 5: Write findings to `docs/audit/track-7-findings.md`**

---

## Track 8: Test Coverage & Quality

### Task 8.1: Test coverage analysis

**Files to read:**
- All files under `tests/`
- All factories under `database/factories/`
- `phpunit.xml` or `pest.php` config

**Checklist:**

- [ ] **Step 1: Coverage gaps** — For each module, compare API endpoints vs test files:
  - List every route endpoint
  - Check if there's a test for: happy path, validation failure (422), unauthorized (401), not found (404), ownership violation (403)
  - Flag endpoints with zero tests

- [ ] **Step 2: Test quality** — Flag:
  - Tests using `RefreshDatabase` vs `DatabaseTransactions` inconsistency
  - Tests creating models manually instead of using factories
  - Tests not asserting response structure (`assertJsonStructure`)
  - Tests that only check status code but not response body
  - Duplicate test stubs in `tests/Feature/Feature/` (these exist — should be deleted)

- [ ] **Step 3: Factory completeness** — Only 5 factories exist:
  - `UserFactory`, `CouponFactory`, `OrderFactory`, `RefundFactory`, `TapTransactionFactory`
  - Flag missing factories for: Product, Variant, Category, Cart, CartItem, CustomerAddress, CustomerProfile, ShippingZone, ShippingMethod, PromotionRule, ReturnRequest, LoyaltyAccount, Wishlist, Invoice, Shipment
  - Check existing factories have useful states (e.g., `->paid()`, `->cancelled()`)

- [ ] **Step 4: Pest vs PHPUnit** — CLAUDE.md says Pest v3. Check:
  - Are tests using Pest syntax (`it()`, `test()`, `expect()`) or PHPUnit class syntax?
  - Should be consistent across all test files

- [ ] **Step 5: Write findings to `docs/audit/track-8-findings.md`**

---

## Track 9 (Post-Audit): Consolidation & Bruno Tests

### Task 9.1: Merge findings

**After all 8 tracks complete:**

- [ ] **Step 1: Read all track findings files**

Read:
- `docs/audit/track-1-findings.md`
- `docs/audit/track-2-findings.md`
- `docs/audit/track-3-findings.md`
- `docs/audit/track-4-findings.md`
- `docs/audit/track-5-findings.md`
- `docs/audit/track-6-findings.md`
- `docs/audit/track-7-findings.md`
- `docs/audit/track-8-findings.md`

- [ ] **Step 2: Consolidate into prioritized report**

Write `docs/audit/AUDIT-REPORT.md` with sections:
```markdown
# Souq Backend Audit Report — 2026-04-10

## Critical (fix before production)
## High (fix before first client)
## Medium (fix in next sprint)
## Low (nice to have)
## Observations (no action needed, just noted)

## Test Coverage Summary
| Module | Routes | Tested | Coverage |
|--------|--------|--------|----------|

## Missing Factories List

## Recommended Bruno Test Additions
```

- [ ] **Step 3: Create Bruno test backlog**

Based on audit findings, list which Bruno `.bru` files need to be created or updated to cover gaps found in Track 8. Prioritize by module criticality:
1. Orders/Checkout (most critical)
2. Payments
3. Cart
4. Catalog
5. Others

---

## Execution Strategy — Parallel Subagents

```
┌─────────────────────────────────────────────┐
│            DISPATCH IN PARALLEL             │
├──────────┬──────────┬──────────┬────────────┤
│ Track 1  │ Track 2  │ Track 3  │ Track 4    │
│ Arch &   │ Security │ Database │ Catalog +  │
│ Patterns │ & Auth   │ & Migr.  │ Cart       │
├──────────┼──────────┼──────────┼────────────┤
│ Track 5  │ Track 6  │ Track 7  │ Track 8    │
│ Orders + │ Cust +   │ Returns +│ Test       │
│ Payments │ Ship+Pro │ Loy+Inv  │ Coverage   │
└──────────┴──────────┴──────────┴────────────┘
              │ all complete │
              ▼              ▼
        ┌─────────────────────────┐
        │  Track 9: Consolidate   │
        │  → AUDIT-REPORT.md      │
        └─────────────────────────┘
```

**Subagent config per track:**
- Type: `code-reviewer` or `security-reviewer` (depending on track)
- Tools: Read, Glob, Grep only (read-only audit — no writes except findings file)
- Model: `sonnet` for tracks 4-8 (module logic), `opus` for tracks 1-3 (cross-cutting)

**Each subagent prompt must include:**
1. The exact checklist from the track above
2. Path to `CLAUDE.md` for project rules
3. Output format (findings file with severity/file/rule/current/fix structure)
4. Instruction to write findings to `docs/audit/track-N-findings.md`

---

## Post-Audit Actions (manual, after report review)

1. **Fix critical findings** — security issues, data integrity bugs
2. **Fix high findings** — broken best practices, missing ownership checks
3. **Add missing factories** — needed for better test coverage
4. **Expand Bruno collection** — cover all modules, not just Customers
5. **Run `vendor/bin/pint --dirty`** — format all changed files
6. **Run full test suite** — `php artisan test --parallel` — must stay at 428/428
7. **Update CLAUDE.md** — add any new gotchas discovered during audit

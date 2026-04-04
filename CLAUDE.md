# Bahrain Ecommerce — Project Brain

> Read this at the start of EVERY session. All architectural decisions here
> are final. Do not suggest alternatives to anything listed in section 2.

---

## 1. What this project is

A production-ready, reusable ecommerce base template for the Bahrain market.
Bilingual Arabic/English, VAT-compliant (10%), integrated with Tap Payments
(covers BENEFIT, BenefitPay QR, cards, Apple Pay). Self-hosted on Hetzner +
Coolify with zero-downtime blue-green deploys.

**Developer:** Ali (DonPollo) — full stack, based in Bahrain.
**Goal:** Ship to first real client, then white-label per client from this base.

---

## 2. Stack — final, do not suggest alternatives

| Layer | Technology |
|---|---|
| Frontend framework | Next.js 16, App Router |
| UI components | shadcn/ui (RTL-ready) |
| Animations | Framer Motion — LazyMotion + domAnimation ONLY |
| Client state | Zustand v5 (persisted for cart/wishlist) |
| Server state | TanStack Query v5 |
| Validation | Zod v4 |
| i18n | next-intl (AR primary, EN secondary) |
| Arabic font | IBM Plex Sans Arabic via next/font |
| Backend | Laravel 13, modular monolith |
| Admin panel | Laravel Filament v3 |
| Database | PostgreSQL 17 |
| Cache/Queue | Redis 7 (DB0=cache, DB1=queue, DB2=sessions) |
| Queue monitor | Laravel Horizon |
| WebSockets | Laravel Reverb (no separate Node.js) |
| Testing (BE) | Pest v3 |
| Testing (FE) | Vitest + Playwright |
| Email | Resend |
| Payments | Tap Payments API v2 |
| Search | Meilisearch (when catalog > 200 products) |
| Deploy | Coolify + Hetzner VPS + Cloudflare CDN |
| CI/CD | GitHub Actions |

**Not used and not to be suggested:**
- Shopify Headless (Payments unavailable in Bahrain)
- GraphQL (REST + Sanctum is the decision)
- Microservices (modular monolith is the decision)
- MySQL (PostgreSQL is the decision — JSONB attributes)
- Node.js service alongside Laravel (Reverb handles WebSockets)
- Medusa.js (we own the Laravel backend)

---

## 3. Repository structure

```
bahrain-ecomm/
├── frontend/              ← Next.js 16 App Router
│   ├── src/
│   │   ├── app/
│   │   │   └── [locale]/ ← AR/EN routing
│   │   ├── components/
│   │   │   └── ui/       ← shadcn components
│   │   ├── stores/        ← Zustand stores
│   │   ├── lib/
│   │   │   ├── api/       ← TanStack Query fetchers
│   │   │   ├── query-keys.ts
│   │   │   └── currency.ts
│   │   └── schemas/       ← Zod schemas (shared with backend via API contract)
│   ├── components.json    ← shadcn config
│   └── package.json
├── backend/               ← Laravel 13
│   └── app/
│       └── Modules/
│           ├── Catalog/   ← products, variants, categories
│           ├── Orders/    ← cart, checkout, order lifecycle
│           ├── Payments/  ← Tap Payments, refunds, webhooks
│           ├── Inventory/ ← stock tracking, reservations
│           ├── Customers/ ← auth, profiles, addresses
│           └── Notifications/ ← emails, order confirmations
├── docker-compose.yml
├── .github/workflows/
├── CLAUDE.md              ← this file
└── AGENTS.md              ← cross-tool context
```

---

## 4. Non-negotiable rules — enforce always

### Currency
- ALL BHD amounts stored as **integer fils** (1 BHD = 1000 fils)
- NEVER use float or decimal for money anywhere
- DB column type: `integer`, name ends in `_fils` (e.g. `price_fils`, `total_fils`)
- Display: `Intl.NumberFormat('ar-BH', { style: 'currency', currency: 'BHD' })`
- Tap API amount: `number_format($fils / 1000, 3, '.', '')` → `"10.500"`

### Database migrations (zero-downtime)
- NEVER rename a column in one migration
- NEVER add non-nullable column without a default value
- NEVER drop a column that existing code still reads
- ALWAYS provide `down()` rollback method
- ALWAYS add indexes for foreign keys and filtered columns
- Migrations run BEFORE traffic switches in blue-green deploy

### Laravel architecture
- Controllers are thin — ALL business logic in Service classes
- Services fire Events; other modules listen via Events — never cross-call Services
- ALL queue jobs must be idempotent (check state before acting, safe to run twice)
- Use `lockForUpdate()` for ALL inventory decrement operations
- Form Requests handle ALL validation — never validate in controllers
- API Resources handle ALL JSON responses — never return Eloquent models directly

### Frontend architecture
- NEVER import full Framer Motion — always `LazyMotion` + `domAnimation`
- ALL components must work in LTR (EN) and RTL (AR)
- Use logical CSS properties: `ms-*` not `ml-*`, `me-*` not `mr-*`, `ps-*` not `pl-*`
- Icons that represent direction MUST have `rtl:rotate-180`
- TanStack Query for server data, Zustand for client-only state — never mix

### Security
- Tap webhook: verify HMAC-SHA256 BEFORE processing anything
- Normalize amount before hash comparison (consistent decimal format)
- Rate limit: auth 60/min, checkout 10/min, add-to-cart 30/min
- NEVER log payment amounts or card data

---

## 5. Module event map (canonical — check before adding new events)

| Event | Fired by | Listened by |
|---|---|---|
| `OrderPlaced` | Orders | Inventory (reserve stock), Notifications (confirm email) |
| `PaymentCaptured` | Payments | Orders (mark paid), Notifications (receipt) |
| `PaymentFailed` | Payments | Orders (mark failed), Inventory (release reservation) |
| `OrderFulfilled` | Orders | Notifications (shipping update) |
| `StockLow` | Inventory | Notifications (admin alert) |
| `OrderRefunded` | Payments | Orders (update status), Inventory (return stock) |
| `RefundRequested` | Payments | Notifications (admin alert) |
| `RefundApproved` | Payments | Notifications (customer email) |
| `RefundRejected` | Payments | Notifications (customer email) |
| `CartItemAdded` | Cart | Cart (log) |
| `CartItemRemoved` | Cart | Cart (log) |
| `CartMerged` | Cart | Cart (log + Phase 3 WebSocket stub) |
| `CouponApplied` | Cart | Cart (log) |
| `CouponRemoved` | Cart | Cart (log) |
| `CartAbandoned` | Cart | Cart (log — email stub TODO) |
| `OrderCancelled` | Orders | Inventory (release reservation), Cart (release coupon usage) |
| `InvoiceGenerated` | Orders | Notifications (send invoice email) |
| `ShipmentCreated` | Orders | Notifications (send shipment tracking email) |
| `CODCollected` | Payments | Notifications (send payment receipt email) |

---

## 6. Tap Payments integration rules

- Use redirect flow for MVP; embedded card for v2
- Source: `src_all` (all methods), `src_benefit_pay` (BenefitPay QR only)
- Webhook: verify `hashstring` HMAC-SHA256 BEFORE processing
- Normalize amount to full decimal before hash comparison
- Tap retries webhooks TWICE only — implement idempotent receiving
- Order status flow: `pending` → `initiated` → `paid` | `failed` → `refunded`
- Refunds: `POST /v2/refunds/` — support partial, track in `refunds` table
- ALWAYS store full `tap_response` JSON for debugging
- BenefitPay QR: register domain with Tap support BEFORE going live

---

## 7. Bahrain compliance

- VAT: 10% on all taxable goods, show breakdown at checkout
- Every invoice: CR number, VAT registration number, sequential invoice number
- Required pages: /privacy-policy, /returns-policy, /terms-of-service, /about
- Footer: CR number, VAT number, physical/virtual address
- Cookie consent BEFORE any analytics load (PDPL compliance)
- Resolution No. 43 (2024): electronic payment is legally required for all businesses

---

## 8. Current build phase

**PHASE 1 — Foundation** (complete)

- [x] Repository initialized with frontend/ and backend/ structure
- [x] `pnpm dlx shadcn@latest init -t next` run inside frontend/
- [x] RTL migration: `pnpm dlx shadcn@latest migrate rtl`
- [x] next-intl configured with AR/EN and `app/[locale]/` routing
- [x] IBM Plex Sans Arabic loaded via next/font
- [x] Laravel 13 created in backend/
- [x] Laravel Boost installed and configured
- [x] Modular structure in backend/app/Modules/
- [x] PostgreSQL 17 schema — products, variants, inventory_items
- [x] Redis configured (3 separate databases)
- [x] docker-compose.yml wiring both apps
- [x] GitHub Actions CI (lint + tests + smoke)
- [ ] Coolify deploy pipeline connected — requires COOLIFY_WEBHOOK_TOKEN + COOLIFY_WEBHOOK_URL secrets set in GitHub repo settings

**PHASE 2 — Commerce** (in progress)

- [x] Catalog module — products, variants, categories, attributes, reviews (full CRUD + feature tests)
- [x] Customers module — auth (register/login/logout/password reset), profiles, addresses (30/30 tests)
- [x] Cart module — guest (X-Cart-Session header, 30-day TTL) + authenticated (DB), merge on login/register, full coupon system, VAT 10%, abandonment tracking, prune job
- [x] Orders module — checkout, order lifecycle, status history (42/42 tests) + full frontend (checkout page, orders list, order detail, cancel dialog, address selector, status timeline)
- [x] Payments module — Tap Payments redirect flow (src_all), webhooks (HMAC-SHA256 with amount+currency+status), refunds (customer request + admin approve), ShouldBeUnique job dedup, ownership guards on result endpoint, 28/28 tests + frontend (checkout→Tap redirect, /checkout/result with polling, /orders/[id]/refund, retry payment on failure)
- [x] Filament admin panel — TapTransactionResource (read-only), RefundResource (approve/reject), CustomerResource (read-only + orders), CouponResource (full CRUD), AdminSeeder, 17/17 admin tests
- [x] Notifications module — Resend installed, OrderConfirmationMail + PaymentReceiptMail + ShippingUpdateMail (queued, bilingual), listeners wired to OrderPlaced/PaymentCaptured/OrderFulfilled, 10/10 tests

**PHASE 2 — Commerce** (complete ✅ — 94/94 tests)

**PHASE 3 — Complete Platform** (in progress)

**Phase 3A — Foundation Fixes** (complete ✅ — 221/221 tests)

- [x] 3A.1 Store Settings — `Settings` module, `store_settings` table (key/value/group), `StoreSettingsService` singleton (in-memory cache, `lockForUpdate()` sequence counter), `StoreSettingsPage` Filament page (Legal/Branding/Commerce sections)
- [x] 3A.2 Invoice Model — `invoices` + `invoice_items` tables in Orders module, `InvoiceService` (idempotent, atomic sequence + creation in single transaction, VAT on discounted subtotal, total derived from components), `GenerateInvoiceJob` (ShouldBeUnique), listener on `PaymentCaptured`, `InvoiceResource` API, `InvoiceResource` Filament (read-only), `InvoiceRelationManager` on OrderResource
- [x] 3A.3 Shipment Model — `shipments` + `shipment_items` tables, `ShipmentService` (createShipment validates qty, DB-scoped item fetch prevents cross-order injection, dispatch outside transaction, markShipped/markDelivered fires OrderFulfilled), `ShipmentCreated` event, `ShipmentsRelationManager` with Mark Shipped/Delivered row actions, `GET /orders/{n}/shipments` API
- [x] 3A.4 COD Payment — `cod` payment_method + `pending_collection`/`collected` order statuses, `CodCollectedMail`, `CODCollected` event (Payments namespace), `markCodCollected()` on OrderService (wrapped in transaction, refresh() for fresh state), "Mark Collected" Filament action on OrderResource
- [x] Security fixes — address ownership scoped in `CheckoutRequest` + `OrderService::checkout()` defense-in-depth; cross-order item injection fixed in `ShipmentService`; VAT computed on discounted subtotal in `InvoiceService`; status allowlist on `overrideOrderStatus()`; constructor injection in `OrderController`; empty CR/VAT guard in `InvoiceService`
- [x] Tests — 64 tests across `StoreSettingsTest`, `InvoiceTest`, `ShipmentTest`, `CodTest`

**Phase 3B — Catalog Expansion** (next)

- [x] 3B.1 Product Types — `product_type` enum (`simple`, `configurable`, `bundle`, `downloadable`, `virtual`), bundle tables (`bundle_options`, `bundle_option_products`), downloadable tables (`downloadable_links`, `downloadable_link_purchases`), download token endpoint (265/265 tests)
- [x] 3B.2 Meilisearch — `Searchable` trait on Product, bilingual index config, `GET /search` API, 20 SearchTests (360/360 tests)

**Phase 3C — Customer Features** (next, parallel with 3B)

- [x] 3C.1 Customer Groups — `customer_groups` table, `variant_group_prices`, `product_group_visibility`, group-aware pricing in CartService (285/285 tests)
- [x] 3C.2 Wishlist — `wishlists` + `wishlist_items` tables, shareable token (UUID), `is_public` flag, move-to-cart via CartService, full API (318/318 tests)
- [x] 3C.3 Product Compare — `POST /compare` accepts up to 4 variant IDs, returns attribute matrix (null-padded), stateless, no DB (318/318 tests)

**Phase 3D — Platform Expansion** (in progress)

- [x] 3D.1 Shipping module — `shipping_zones`, `shipping_methods`, `order_shipping` tables, `ShippingCarrierInterface` + `FlatRateCarrier` + `FreeThresholdCarrier` + Aramex/DHL stubs, `ShippingService` (resolveZone, getAvailableRates, attachShippingToOrder, isVirtualCart, validateShippingMethodForCart), `GET /shipping/rates` API, OrderService checkout integration (shipping outside transaction), `OrderShippingResource`, Filament `ShippingZoneResource` + `ShippingMethodsRelationManager` + `OrderShippingRelationManager`, `ShippingSeeder` (BH zone, 2 methods, SA+UAE stubs), 360/360 tests
- [ ] 3D.2 Promotion rule engine
- [ ] 3D.3 Multi-currency display

**Phase 3E–3F** (locked)

- 3E: RMA/Returns, Loyalty points (earn/redeem), Inventory audit ledger
- 3F: Complete Filament admin for all modules, Analytics dashboard (KPIs, charts, CSV export)

---

## 9. Commands quick reference

```bash
# Frontend (from frontend/)
pnpm dev
pnpm build
pnpm lint
pnpm vitest
pnpm playwright test

# Backend (from backend/)
php artisan serve
php artisan test --parallel
php artisan horizon
./vendor/bin/pint

# From root
docker compose up -d
docker compose down
```

---

## 10. Session learnings

### Phase 3D.1 — 2026-04-04

**`return DB::transaction(...)` makes all code after the closing `});` unreachable**
If you write `return DB::transaction(function () { ... });`, PHP returns from the function immediately with the transaction result. Any code after the closing `});` (like `attachShippingToOrder()`) is dead code — never executed. Always use `$result = DB::transaction(...)` when you need to do work after the transaction, then `return $result` at the end.

**Shipping attachment must happen OUTSIDE the DB transaction**
`ShippingService::attachShippingToOrder()` creates an `OrderShipping` row. Doing this inside the checkout transaction means: if the transaction rolls back (e.g., inventory fail), you've attached shipping to a non-existent order. Pattern: compute shipping rate before the transaction, run the transaction, then call `attachShippingToOrder()` after `});`.

**`CustomerAddress` has no `country` field — hardcode 'BH' for zone resolution**
The project is Bahrain-only. `CustomerAddress` stores governorate/city but no ISO country code. `ShippingService::resolveZoneForAddress()` hardcodes `$country = 'BH'` and matches against `ShippingZone.countries` (JSON array). Never try to read a `country` column off the address model.

**`OrderShipping` model needs explicit `protected $table = 'order_shipping'`**
The migration creates the table as `order_shipping` (singular). Eloquent auto-pluralizes to `order_shippings`. Set `protected $table = 'order_shipping'` explicitly on the model.

**Virtual cart bypass: if ALL items are `product_type = 'virtual'`, skip shipping entirely**
`ShippingService::isVirtualCart()` returns true only when every cart item has `product_type === 'virtual'`. In `OrderService::checkout()`, virtual carts skip the `shipping_method_id` requirement. Existing test carts with physical products must pass a valid `shipping_method_id` — update existing checkout tests to use `product_type: 'virtual'` to bypass this requirement where shipping isn't the focus.

**Rate computation must happen BEFORE the DB transaction**
`ShippingService::getAvailableRates()` hits the DB (and Cache) to look up zone and method rates. This must run before `DB::transaction()` so that `delivery_fee_fils` and `total_fils` are correct when the `Order` row is inserted inside the transaction. Calling it inside the transaction adds unnecessary lock time and risks a read-inside-write anti-pattern.

**Shipping rate cache key: `"shipping_rates_{cartId}_{addressId}"`, TTL: 600 seconds**
Cache key format for `ShippingService::getAvailableRates()`. Both cart ID and address ID are part of the key so different address/cart combos don't collide. TTL is 600 seconds (10 minutes) — balances freshness vs DB load for rate shopping.

**`ShippingMethodsRelationManager` uses `protected static string $relationship = 'methods'`**
`ShippingZone` has a `methods()` hasMany relationship to `ShippingMethod`. The Filament RelationManager must set `$relationship = 'methods'` (not `'shippingMethods'`). Check the model's actual relationship method name before writing the RelationManager.

**Filament v5: countries JSONB field in form requires explicit dehydrate/hydrate conversion**
`ShippingZone.countries` is a JSONB array (`['BH', 'SA']`). In a Filament TextInput, the state is a string. Convert bidirectionally:
- `formatStateUsing(fn ($state) => implode(', ', $state ?? []))` — array to comma string for display
- `dehydrateStateUsing(fn ($state) => array_filter(array_map('trim', explode(',', $state ?? ''))))` — comma string back to array on save

<!-- /learn command appends here -->

### Phase 3A — 2026-04-03

**`StoreSettingsService` in-memory cache must store `{value, group}` per key, not just `value`**
If the cache is a flat `[key => value]` map, `getGroup()` cannot filter by group without a DB query. Store as `[key => ['value' => ..., 'group' => ...]]` and filter in-memory. This makes both `get()` and `getGroup()` fully cache-coherent within a request.

**`bulkUpdate()` on settings must have an explicit key allowlist (EDITABLE_KEYS const)**
Without an allowlist, `bulkUpdate()` accepts `last_invoice_sequence` as a key and would reset it via the admin form. Allowlist the keys that are safe to update via the UI; enforce it inside the service method, not just in the Filament page.

**`getNextInvoiceSequence()` must NOT have a create-branch — row must be guaranteed present by seeder**
The `lockForUpdate()` only protects the update path. If the row is missing, two concurrent requests both try `StoreSetting::create()` and one gets a unique violation. Remove the else-branch; throw `RuntimeException` if the row is missing. Use `firstOrCreate()` (not `updateOrCreate()`) in the seeder for `last_invoice_sequence` to never reset a live counter on re-seed.

**Invoice sequence increment and invoice row creation must be in the same DB transaction**
If `getNextInvoiceSequence()` commits its own inner transaction before `Invoice::create()`, a crash between them burns a sequence number permanently — a Bahrain VAT compliance violation. Wrap the entire `generateInvoice()` body in `DB::transaction()` with `lockForUpdate()` on the Order row. The inner `DB::transaction()` participates via savepoint and rolls back atomically with the outer.

**`unique('order_id')` constraint required on `invoices` table**
Without a DB-level unique constraint on `invoices.order_id`, the application-level idempotency check + order lock is a best-effort guard. The unique constraint is the definitive safeguard against duplicate invoices.

**`vat_rate` must be stored as integer percentage (10 = 10%), not decimal (0.1000)**
CLAUDE.md bans decimal columns. Store VAT rate as `unsignedSmallInteger` with default 10. Update model cast to `integer`. Service writes `10` not `'0.1000'`. VAT calculation: `$vat = (int) round($subtotal * 0.10)` (multiply by 0.10 in PHP, store result as integer fils — the rate column is just for audit display).

**`price_fils_per_unit` on order_items is VAT-EXCLUSIVE — document this explicitly**
The cart adds VAT on top of the product price. Invoice items must apply VAT the same way (`item_subtotal * 0.10`). Add a PHPDoc comment in `InvoiceService::generateInvoice()` stating this assumption so future devs don't introduce double-VAT.

**Events must be dispatched OUTSIDE DB transactions**
Dispatching events (especially queued ones) inside a `DB::transaction()` means: if the transaction rolls back, the event has already fired. A queued listener will process a shipment/invoice that was never committed. Pattern: collect all data inside the transaction, return it, then dispatch events after the closing `});`. Applies to: `ShipmentService::createShipment()`, `InvoiceService::generateInvoice()`, `OrderService::markCodCollected()`.

**Null-safe `?->` required everywhere user relation is accessed for email**
Guest orders have `user_id = null`, making `$order->user` null. `$this->order->user->email` throws. Always use `$this->order->user?->email ?? $this->order->guest_email` in every Mail class `envelope()`.

**`Mail::queue()` not `Mail::send()` in queued listeners for ShouldQueue mailables**
`Mail::send()` sends synchronously regardless of `ShouldQueue` on the mailable. Use `Mail::queue()` to honour the mailable's queue configuration. Check existing listeners (`SendPaymentReceiptEmail`) for the project pattern.

**`$order->refresh()` at the start of admin service methods (markCodCollected, etc.)**
Filament passes the `$record` object from its table query cache. It may be stale. Call `$order->refresh()` before reading `order_status` for validation to ensure you're acting on current DB state.

**`ShipmentService::createShipment()` must scope order item fetch to the order via DB query**
Using `$order->items->get($id)` (in-memory collection) to validate submitted order_item_ids is fragile — it only works if `$order->items` was correctly eager-loaded. Use `$order->items()->whereIn('id', array_column($items, 'order_item_id'))->get()->keyBy('id')` for an authoritative DB-scoped check.

**Address ownership must be scoped in CheckoutRequest AND in OrderService**
`Rule::exists('customer_addresses','id')` without a `->where('user_id', Auth::id())` scope lets any authenticated user use another customer's address ID. Fix in request with scoped Rule::exists. Add defense-in-depth ownership check in OrderService::checkout() after findOrFail.

**`PaymentReceiptMail` requires a `TapTransaction` — create a separate `CodCollectedMail` for COD**
COD orders have no TapTransaction. Attempting to reuse PaymentReceiptMail for COD will crash. Create a dedicated `CodCollectedMail` that takes only the Order.

### Phase 1 — 2026-03-28

**Next.js 16 renamed `middleware.ts` → `proxy.ts`**
next-intl still exports `createMiddleware` (no `createProxy` yet), but the file must be named `proxy.ts` or the build emits a deprecation warning. The export shape is identical — just rename the file.

**`app/layout.tsx` must be a pass-through when `[locale]/layout.tsx` owns `<html>`/`<body>`**
Next.js technically requires the root layout to contain `<html>` and `<body>`, but in practice the pattern `export default function RootLayout({ children }) { return children }` works and is what next-intl's own docs show. The locale layout handles lang, dir, font vars, and providers.

**`next-intl/plugin` needs the path to `i18n/request.ts`, not `i18n.ts`**
The v4 convention is `createNextIntlPlugin("./i18n/request.ts")`. Passing the wrong path silently breaks locale detection at runtime with no useful error.

**IBM Plex Sans Arabic replaces Geist as `--font-sans`**
Both fonts set `variable: "--font-sans"`. The locale layout loads IBM Plex Sans Arabic (Arabic + Latin subsets, weights 300–700) and Geist Mono separately as `--font-mono`. Geist is dropped entirely — do not re-import it.

**`output: "standalone"` is required for the frontend Dockerfile**
Without it, Next.js does not emit `server.js` and the Docker runner stage has nothing to execute. Add it to `next.config.mjs` before writing any Dockerfile.

**Redis DB assignment: DB0=cache, DB1=queue(default), DB2=sessions**
Laravel's default Redis connection is named `default` and is used by the queue worker. The `cache` connection is used by `CACHE_STORE=redis`. A separate `session` connection must be explicitly added to `config/database.php` and `SESSION_CONNECTION=session` set in `.env`. The out-of-box Laravel config has `default` on DB0 and `cache` on DB1 — both are wrong for this project.

**Module service providers must be registered in `bootstrap/providers.php`**
Laravel 11+ dropped `config/app.php` providers array. The only registration point is `bootstrap/providers.php`. Each module's `ServiceProvider` must be listed there explicitly — there is no auto-discovery for `app/Modules/`.

**`.env.testing` must exist before CI can run `cp .env.testing .env`**
The `ci.yml` backend job does `cp .env.testing .env` before `key:generate`. Without this file the CI job fails immediately. Use `SESSION_DRIVER=array`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync` in testing to avoid Redis dependency in unit/feature tests.

**Migration ordering: `categories` before `products`, `variants` before `inventory_items`**
Foreign key constraints enforce this. The timestamp prefix `2026_03_28_00000N_` must reflect the dependency order within the same day.

### Phase 2 — 2026-03-28 / 2026-03-30

**`api` middleware group has no session — never call `$request->session()` in api controllers**
The `api` middleware group does not include `StartSession`, so any call to `$request->session()` throws `RuntimeException: Session store not set on request`. `SessionGuard::updateSession()` handles session migration internally during `Auth::attempt()` — manual `regenerate()`/`invalidate()` calls are redundant and break tests. `Auth::guard('web')->logout()` also handles session clearing internally.

**`JsonResource` auto-returns 201 when the underlying model's `wasRecentlyCreated` is true**
This bites GET endpoints that lazily create records (e.g. `ProfileController::show()` which calls `firstOrCreate`). The resource infers 201 from the newly created model. Fix: always call `->response()->setStatusCode(200)` explicitly on resources returned from `show()` methods that may trigger lazy creation.

**`php artisan make:test` double-nests paths under `tests/Feature/`**
Running `php artisan make:test Feature/Customers/FooTest` creates `tests/Feature/Feature/Customers/FooTest.php`. Write test files directly with the Write tool to `tests/Feature/Customers/` to avoid the duplicate nesting.

**Phase 1 `coupons` schema conflicts with any new cart migrations that try to re-create these tables**
Phase 1 already created `coupons`, and uses `coupon_applicable_items` (polymorphic: `itemable_type`/`itemable_id`) — NOT separate `coupon_categories`/`coupon_variants` tables. Delete any cart-session migrations that duplicate these tables. Only `cart_abandonments` is a genuinely new table for the Cart module.

**Phase 1 coupon column names differ from common conventions — use these exact names**
`starts_at`/`expires_at` (not `valid_from`/`valid_until`), `discount_type` ENUM values are `'percentage'` and `'fixed_fils'` (not `'fixed_amount'`), `minimum_order_amount_fils` (not `minimum_order_fils`), `maximum_discount_fils`, `max_uses_global`, `max_uses_per_user`. There is NO `current_uses` counter — count rows in `coupon_usage` table instead.

**Coupons scope to products, not variants**
`coupon_applicable_items` stores `itemable_type = 'product'` or `'category'` — never `'variant'`. CartService helpers must extract `product_ids` (via `variant->product_id`) and `category_ids` from cart items; CouponService filters applicableItems by `itemable_type`.

**Cross-module service dependency: use `app()` resolution, not constructor injection**
AuthService (Customers module) needs CartService (Cart module) for merge-on-login. Constructor injection creates a provider-level circular dependency. Use `app(CartService::class)` inside the method body instead — it resolves lazily from the container with no circular issue.

**`CartService::mergeCart()` already fires `CartMerged` — do not re-dispatch in AuthService**
Calling `mergeCart()` and then also dispatching `CartMerged` doubles the event. AuthService should only call `mergeCart()`; CartService owns the event dispatch.

**`OrderStatusHistory` model needs explicit `$table = 'order_status_history'`**
The migration creates the table as `order_status_history` (singular — matches the noun). Eloquent auto-pluralizes to `order_status_histories`. Always set `protected $table` explicitly on models whose table name doesn't follow the `snake_plural` convention.

**Capture `$oldStatus` BEFORE calling `$model->update()` — Eloquent calls `syncOriginal()` inside save**
After `$order->update(['order_status' => 'cancelled'])`, calling `$order->getOriginal('order_status')` returns `'cancelled'`, not the previous value. Always do `$oldStatus = $order->order_status;` before the update, then pass it explicitly to `recordStatusChange()`.

**`InvalidArgumentException` from service layer must be caught in controller and re-thrown as `ValidationException`**
`OrderService::cancelOrder()` throws `\InvalidArgumentException` for non-cancellable orders. Without a catch block the API returns 500. Wrap with `try/catch (\InvalidArgumentException $e)` and re-throw as `ValidationException::withMessages(['order' => [$e->getMessage()]])` to return 422.

**Zod v4 `z.record()` requires two arguments: `z.record(keySchema, valueSchema)`**
`z.record(z.string())` (one arg) was valid in Zod v3 but errors in v4 with "Expected 2-3 arguments, but got 1". Use `z.record(z.string(), z.string())` for string-valued records. This affects variant_attributes and similar JSONB fields.

### Payments Module — 2026-03-31

**`TapApiService` config values can be `null` in testing — always cast to `(string)`**
`config('services.tap.secret_key')` returns `null` when the env var is unset. Since the property is typed `string`, this causes a `TypeError`. Cast with `(string)` or provide a fallback: `(string) config('services.tap.secret_key', '')`.

**PostgreSQL CHECK constraint ALTER syntax doesn't work on SQLite**
The migration for altering the `refunds.status` enum uses `ALTER TABLE ... DROP CONSTRAINT`. SQLite (used in testing) has no CHECK constraint support. Gate the raw SQL with `if (DB::getDriverName() === 'pgsql')`.

**Eloquent auto-manages `created_at` — explicit values in `create()` are overwritten unless in `$fillable`**
When testing stale records, use `Carbon::setTestNow(now()->subMinutes(35))` before `Model::create()`, then `Carbon::setTestNow()` to reset. Don't pass `created_at` directly.

**`useCheckout` hook must not navigate — checkout now has two steps**
Checkout flow changed from: create order → navigate to order detail, to: create order → create charge → `window.location.href` redirect to Tap. The `useCheckout` hook returns the order; the calling component chains the charge creation in `onSuccess`.

**Existing table names: `tap_transactions` (not `payments`) and `refunds` — use model `$table` if needed**
Phase 1 created `tap_transactions` (not `payments`). The model is named `TapTransaction` and the table stays as-is. Multiple payment attempts per order are tracked via `attempt_number` column (unique constraint on `order_id` was dropped).

**`.env.testing` had old Tap key names (`TAP_SECRET_KEY`) — must match config keys (`TAP_SECRET_KEY_TEST`)**
Config reads `TAP_SECRET_KEY_TEST`/`TAP_SECRET_KEY_LIVE` and switches based on `APP_ENV`. Ensure `.env.testing` uses `TAP_SECRET_KEY_TEST=...` not `TAP_SECRET_KEY=...`.

**Tap webhook hashstring covers id + amount + currency + status — NOT charge ID alone**
Tap HMAC-SHA256 hashstring is computed over `"x_id{id}x_amount{amount}x_currency{currency}x_status{status}"`. Amount is normalized to 3 decimal places. Hashing only the charge ID allows signature forgery. Always verify with the full field set and use `hash_equals()` for timing safety.

**Webhook secret bypass must be production-gated**
`empty($webhookSecret)` returning `true` to skip verification is only safe in non-production. In production, an unconfigured webhook secret must reject all webhooks (return false/401). Gate with `app()->isProduction()`.

**`ShouldBeUnique` on queue jobs prevents Tap webhook retry duplicates**
Tap retries webhooks up to twice. Without `ShouldBeUnique` + `uniqueId()`, both the original and the retry can land in the queue simultaneously. Even with idempotent handling, two concurrent jobs waste resources and risk lock contention. Add to any webhook-processing job.

**Payment result endpoint must be authenticated + ownership-checked**
`GET /payments/result?tap_id=chg_xxx` was public, allowing anyone to query another customer's payment status by charge ID. The charge was always created by an authenticated user, so the result endpoint should also require `auth:sanctum` and validate `$transaction->order->user_id === $request->user()->id`.

### Filament Admin Panel — 2026-03-31

**Filament v5 is installed (NOT v3) — plan says v3 but actual installed version is v5.4.3**
`composer.json` has `"filament/filament": "^5.4"`. All plan spec code using `Form $form: Form` from `Filament\Forms\Form` is wrong. Always use the v5 Schema API.

**Filament v5 Schema API: ALL form methods use `Schema $schema: Schema`**
Both main resource `form()` methods AND RelationManager `form()` methods must use `Filament\Schemas\Schema`. Signature: `public static function form(Schema $schema): Schema { return $schema->schema([...]); }`. Using `->components([...])` instead of `->schema([...])` silently produces an empty form. The codebase standard (confirmed from ProductResource.php) is `$schema->schema([...])`.

**`BadgeColumn` does not exist in Filament v5 — use `TextColumn::make()->badge()`**
Plan spec uses `BadgeColumn::make('status')->colors([...])` which is a v3 API. In v5 use `TextColumn::make('status')->badge()->color(fn ($state) => match($state) { ... })`. Always check existing resources (ProductResource, AttributeResource) for the correct v5 table column API before writing new resources.

**Always read actual DB migrations before implementing Filament forms — plan enum values often differ**
The `attributes` table migration has `attribute_type` enum `['color','size','material','brand','custom']` and `input_type` enum `['dropdown','color_picker','text','radio']`. The plan spec had wrong values (`['select','text','color']`). Always read the migration file first; wrong enum values cause silent save failures (validation passes, DB rejects).

**Filament v5 `discoverResources()` path must match actual directory structure**
`AdminPanelProvider` calls `discoverResources(in: app_path('Modules/Catalog/Filament/Resources'), for: 'App\\Modules\\Catalog\\Filament\\Resources')` for each module. Resources auto-discovered from these paths — no manual registration needed per resource.

### Phase 2 completion + test fixes — 2026-04-02

**Phase 2 is fully complete — all 94 tests passing across Cart, Orders, Notifications, Admin**
All Phase 2 items are done: Catalog, Customers, Cart, Orders, Payments, Filament admin, Notifications. 94/94 tests green. Project is ready to enter Phase 3 (Hardening).

**`variants.attributes` is JSONB NOT NULL — always include `'attributes' => []` in test `Variant::create()` calls**
Any test helper that calls `Variant::create([...])` without `'attributes' => []` will fail with an SQLite NOT NULL constraint error. The column is mandatory. Pattern to always use:
```php
Variant::create([
    'product_id' => $product->id,
    'sku'        => 'SKU-'.uniqid(),
    'is_available' => true,
    'attributes' => [],  // required — JSONB NOT NULL
]);
```

**`cart_abandonments.cart_id` must be nullable with `nullOnDelete()`, NOT `cascadeOnDelete()`**
Using `cascadeOnDelete()` on `cart_id` silently deletes the abandonment record when the parent cart is pruned — defeating the entire purpose of the table. The column is nullable with `nullOnDelete()` so the abandonment record survives after cart deletion (cart_id becomes NULL). Migration file: `2026_03_30_000004_create_cart_abandonments_table.php`.

### Phase 3B.1 — 2026-04-03

**`InvalidArgumentException` from service type guards must be caught in controller and re-thrown as `ValidationException`**
`ProductService::createBundleOption()` and `createDownloadableLink()` throw `\InvalidArgumentException` for wrong product types. Without a try/catch in the controller, these bubble up as 500. Wrap with `try/catch (\InvalidArgumentException $e)` and re-throw as `ValidationException::withMessages(['product_type' => [$e->getMessage()]])` to return 422. Same pattern applies to any service guard methods added in future modules.

**`UniqueConstraintViolationException` from DB must be caught for user-facing 422 responses**
When adding a product to a bundle option for the second time, the DB unique constraint fires `\Illuminate\Database\UniqueConstraintViolationException`. Catch it in the controller and re-throw as `ValidationException` to return 422 instead of 500.

**`product_type` column must be a plain `string` column, NOT a DB enum, for SQLite test compatibility**
PostgreSQL supports native enums but SQLite (used in testing) does not support CHECK constraint alteration via `ALTER TABLE ... DROP CONSTRAINT`. Use `$table->string('product_type')->default('simple')` and enforce valid values via Form Request `Rule::in([...])` at the application layer only.

**`ProductController::show()` must load `bundleOptions` and `downloadableLinks` relationships**
`ProductResource` uses `whenLoaded()` for bundle_options and downloadable_links. If show() only loads `['category.image', 'variants.inventory', 'tags']`, those keys will be absent from the API response. Add `'bundleOptions'` and `'downloadableLinks'` to the load() call so the show endpoint always includes them.

**`DownloadableLinkResource` must NEVER include `file_path` in JSON output**
The file_path is a server filesystem path stored in the DB. Exposing it in the API response leaks internal path structure and allows clients to infer the storage layout. Explicitly omit it from the resource's `toArray()` — the test `assertArrayNotHasKey('file_path', ...)` enforces this.

**DownloadService token payload: use base64url (not standard base64) for URL safety**
The signed download token is included in a URL path segment (`GET /downloads/{token}`). Standard base64 uses `+` and `/` which conflict with URL parsing. Use `base64_encode` then `strtr($b64, '+/', '-_')` and strip `=` padding for URL-safe tokens. Reverse in `validateAndDecodeToken`.

**`DownloadableLinkPurchase` needs explicit `order()` relationship to get `user_id` for ownership check**
The download endpoint must verify the requesting user owns the purchase. `DownloadableLinkPurchase` has `order_id` but no direct `user_id`. Add `order()` belongsTo relationship and check `$purchase->order->user_id === $request->user()->id` in `DownloadService::validateAndDecodeToken()`.

### Phase 3C.1 — 2026-04-03

**`CustomerGroupService::getGroupPriceForUser()` fallback must be `$variant->effective_price_fils`, NOT `$variant->price_fils`**
`Variant.price_fils` is nullable — variants that inherit price from their parent product have `price_fils = null`. Using `$variant->price_fils` as the fallback returns null, violating the `int` return type and breaking all Cart tests. Always use `$variant->effective_price_fils`, which is a computed accessor that falls back to `product.base_price_fils` when `price_fils` is null.

**Session work must be committed after each task — never leave tasks uncommitted at session end**
Tasks 1–5 (models, migrations, service, controller, routes, CartService) were left uncommitted at the end of Session 2. The Session 3 seeder commit was made on top of them as untracked files, making the history misleading. Commit each task as it completes so session handoffs have a clean, accurate git log.

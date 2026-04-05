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
| Admin panel | Laravel Filament v5 |
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
- Shopify Headless, GraphQL, Microservices, MySQL, Node.js alongside Laravel, Medusa.js

---

## 3. Repository structure

```
bahrain-ecomm/
├── frontend/              ← Next.js 16 App Router
│   └── src/
│       ├── app/[locale]/  ← AR/EN routing
│       ├── components/ui/ ← shadcn components
│       ├── stores/        ← Zustand stores
│       ├── lib/api/       ← TanStack Query fetchers
│       └── schemas/       ← Zod schemas
├── backend/               ← Laravel 13
│   └── app/Modules/
│       ├── Catalog/       ← products, variants, categories
│       ├── Orders/        ← cart, checkout, order lifecycle
│       ├── Payments/      ← Tap Payments, refunds, webhooks
│       ├── Inventory/     ← stock tracking, reservations
│       ├── Customers/     ← auth, profiles, addresses
│       ├── Notifications/ ← emails, order confirmations
│       ├── Settings/      ← store settings
│       ├── Shipping/      ← zones, methods, carriers
│       ├── Promotions/    ← rule engine
│       └── Currency/      ← multi-currency display (3D.3)
├── docker-compose.yml
├── .github/workflows/
└── CLAUDE.md
```

---

## 4. Non-negotiable rules — enforce always

### Currency
- ALL BHD amounts stored as **integer fils** (1 BHD = 1000 fils)
- NEVER use float or decimal for money anywhere
- DB column type: `integer`, name ends in `_fils` (e.g. `price_fils`, `total_fils`)
- Display: `Intl.NumberFormat('ar-BH', { style: 'currency', currency: 'BHD' })`
- Tap API amount: `number_format($fils / 1000, 3, '.', '')` → `"10.500"`
- Multi-currency is **display-only** — all checkout/storage stays in BHD fils

### Database migrations (zero-downtime)
- NEVER rename a column in one migration
- NEVER add non-nullable column without a default value
- NEVER drop a column that existing code still reads
- ALWAYS provide `down()` rollback method
- ALWAYS add indexes for foreign keys and filtered columns

### Laravel architecture
- Controllers are thin — ALL business logic in Service classes
- Services fire Events; other modules listen via Events — never cross-call Services
- ALL queue jobs must be idempotent (check state before acting, safe to run twice)
- Use `lockForUpdate()` for ALL inventory decrement operations
- Form Requests handle ALL validation — never validate in controllers
- API Resources handle ALL JSON responses — never return Eloquent models directly
- Cross-module service calls: use `app(ServiceClass::class)` not constructor injection (avoids circular deps)
- Events must be dispatched OUTSIDE `DB::transaction()` — queued listeners fire even if the tx rolls back
- Use `$result = DB::transaction(...)` when work must follow; `return DB::transaction(...)` makes post-tx code unreachable
- `$order->refresh()` at start of admin service methods — Filament records may be stale

### Frontend architecture
- NEVER import full Framer Motion — always `LazyMotion` + `domAnimation`
- ALL components must work in LTR (EN) and RTL (AR)
- Use logical CSS properties: `ms-*` not `ml-*`, `me-*` not `mr-*`, `ps-*` not `pl-*`
- Icons that represent direction MUST have `rtl:rotate-180`
- TanStack Query for server data, Zustand for client-only state — never mix
- Zod v4: `z.record(z.string(), z.string())` — two args required

### Security
- Tap webhook: verify HMAC-SHA256 BEFORE processing anything
- Normalize amount before hash comparison (consistent decimal format)
- Rate limit: auth 60/min, checkout 10/min, add-to-cart 30/min
- NEVER log payment amounts or card data
- Address ownership scoped in both CheckoutRequest AND OrderService (defense-in-depth)

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
| `CartItemAdded/Removed/Merged/Abandoned` | Cart | Cart (log) |
| `CouponApplied/Removed` | Cart | Cart (log) |
| `OrderCancelled` | Orders | Inventory (release reservation), Cart (release coupon usage) |
| `InvoiceGenerated` | Orders | Notifications (send invoice email) |
| `ShipmentCreated` | Orders | Notifications (send shipment tracking email) |
| `CODCollected` | Payments | Notifications (send payment receipt email) |

---

## 6. Tap Payments integration rules

- Use redirect flow for MVP; embedded card for v2
- Source: `src_all` (all methods), `src_benefit_pay` (BenefitPay QR only)
- Webhook HMAC-SHA256 hashstring: `"x_id{id}x_amount{amount}x_currency{currency}x_status{status}"` — verify BEFORE processing; use `hash_equals()`
- Amount normalized to 3 decimal places before hash comparison
- Tap retries webhooks TWICE — use `ShouldBeUnique` + `uniqueId()` on webhook jobs
- Order status flow: `pending` → `initiated` → `paid` | `failed` → `refunded`
- Refunds: `POST /v2/refunds/` — partial supported, tracked in `refunds` table
- ALWAYS store full `tap_response` JSON for debugging
- Webhook secret bypass only allowed in non-production (`app()->isProduction()`)
- Payment result endpoint: auth + ownership-checked (`$transaction->order->user_id === $request->user()->id`)

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

**Phases 1 + 2 + 3A + 3B + 3C — complete ✅** (389/389 tests)

**Phase 3D — Platform Expansion** (in progress — 3D.1 + 3D.2 + 3D.3 complete)

- [x] 3D.1 Shipping module — zones, methods, carriers (FlatRate, FreeThreshold, Aramex/DHL stubs), `ShippingService`, `GET /shipping/rates`, checkout integration, Filament admin
- [x] 3D.2 Promotion rule engine — `PromotionRule/Condition/Action/Usage`, `PromotionService`, CartService integration, `GET /api/v1/promotions/applicable`, Filament admin, 29 tests
- [x] 3D.3 Multi-currency display — `currencies` table, `CurrencyService` (getActiveCurrencies cached 1h, getRate, convert fils→float), `GET /api/v1/currencies` public, Filament `CurrencyResource` CRUD + Refresh Rates stub, `useCurrencyStore` Zustand (persisted selectedCode), `useCurrencies()` TanStack Query, `CurrencySelector` + `PriceDisplay` components (403/403 tests)

**Phase 3E–3F** (locked)
- 3E: RMA/Returns, Loyalty points, Inventory audit ledger
- 3F: Complete Filament admin, Analytics dashboard (KPIs, charts, CSV export)

**Infrastructure pending:** Coolify deploy pipeline — requires `COOLIFY_WEBHOOK_TOKEN` + `COOLIFY_WEBHOOK_URL` secrets in GitHub repo settings.

---

## 9. Commands quick reference

```bash
# Frontend (from frontend/)
pnpm dev && pnpm build && pnpm lint && pnpm vitest

# Backend (from backend/)
php artisan serve
php artisan test --parallel
php artisan horizon
./vendor/bin/pint

# From root
docker compose up -d
```

---

## 10. Critical gotchas (session learnings)

### Filament v5

- **Filament v5 installed** (`"filament/filament": "^5.4"`) — NOT v3. All form methods use `Schema $schema: Schema` from `Filament\Schemas\Schema`; return `$schema->schema([...])` not `->components([...])`
- `BadgeColumn` removed — use `TextColumn::make()->badge()->color(fn($s) => match($s){...})`
- `discoverResources(in: app_path('Modules/X/Filament/Resources'), for: 'App\\Modules\\X\\Filament\\Resources')` per module in AdminPanelProvider
- JSONB fields in Filament forms need `formatStateUsing` (array→string) + `dehydrateStateUsing` (string→array)
- Filament Repeaters for JSONB: use `Schema::make([...])` nested blocks; hydrates/dehydrates bidirectionally
- **Always read the actual migration before writing a Filament form** — enum values in migrations differ from plan specs

### Laravel patterns

- `bootstrap/providers.php` — only registration point for module ServiceProviders (Laravel 11+, no config/app.php)
- `php artisan make:test Feature/X/FooTest` double-nests to `tests/Feature/Feature/X/`. Write test files directly with Write tool.
- `InvalidArgumentException` from services → catch in controller → re-throw as `ValidationException` (422 not 500)
- `UniqueConstraintViolationException` from DB → catch → re-throw as `ValidationException`
- `api` middleware group has no session — never call `$request->session()` in API controllers
- `JsonResource` returns 201 when `wasRecentlyCreated` is true — always `->response()->setStatusCode(200)` on show() endpoints that may `firstOrCreate`
- `$oldStatus = $order->order_status` BEFORE `$order->update([...])` — Eloquent `syncOriginal()` inside save overwrites `getOriginal()`
- Models with irregular table names need explicit `protected $table` (e.g. `order_status_history`, `order_shipping`)

### Database / Migrations

- `variants.attributes` is JSONB NOT NULL — always include `'attributes' => []` in test Variant::create()
- `cart_abandonments.cart_id` is nullable with `nullOnDelete()` — NOT cascadeOnDelete (records must survive cart pruning)
- PostgreSQL CHECK constraint `ALTER TABLE ... DROP CONSTRAINT` fails on SQLite — gate with `if (DB::getDriverName() === 'pgsql')`
- `vat_rate` stored as integer percentage (10 = 10%), not decimal
- Use `Carbon::setTestNow()` to control `created_at` in tests — don't pass `created_at` directly to create()

### Shipping

- `CustomerAddress` has no `country` column — hardcode `'BH'` in `ShippingService::resolveZoneForAddress()`
- Virtual cart (all items `product_type = 'virtual'`): skip shipping requirement entirely in checkout
- Shipping rate computation must happen BEFORE `DB::transaction()` in checkout
- `ShippingService::attachShippingToOrder()` must be called AFTER the transaction closes

### Promotions

- Promotion discount calculated on **post-coupon subtotal**, not raw subtotal
- `PromotionAction.value` is JSONB — different structure per action type (`percent_off_cart`, `fixed_off_cart`, `bogo`, `free_shipping`)
- Rules ordered by `priority ASC`; exclusive rules stop evaluation on first match
- `PromotionUsage::recordUsage()` called only at checkout, never during `calculateTotals()`

### Invoices / Settings

- Invoice sequence increment + invoice row creation must be in the **same** DB transaction (VAT compliance)
- `getNextInvoiceSequence()` — no create-branch; throw if row missing; use `firstOrCreate()` in seeder
- `StoreSettingsService` cache stores `[key => ['value' => ..., 'group' => ...]]` — not flat map
- `bulkUpdate()` has explicit `EDITABLE_KEYS` allowlist to protect `last_invoice_sequence`

### Mail / Notifications

- Guest orders: `$order->user?->email ?? $order->guest_email` everywhere — `$order->user` can be null
- `Mail::queue()` not `Mail::send()` in queued listeners
- COD orders have no TapTransaction — use dedicated `CodCollectedMail`, not `PaymentReceiptMail`

### Redis

- DB0=cache, DB1=queue (named `default`), DB2=sessions — add explicit `session` connection to `config/database.php`

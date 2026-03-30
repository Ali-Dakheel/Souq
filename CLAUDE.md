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
- [ ] Cart module — guest (session) + authenticated (DB), merge on login, coupon stub
- [ ] Orders module — checkout, order lifecycle, status history
- [ ] Payments module — Tap Payments redirect flow, webhooks, refunds
- [ ] Filament admin panel
- [ ] Notifications module — emails via Resend (order confirm, receipt, shipping)

**PHASE 3 — Hardening** (locked until Phase 2 complete)
Blue-green deploy, k6 load tests, security audit, Lighthouse CI, full RTL audit

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

<!-- /learn command appends here -->

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

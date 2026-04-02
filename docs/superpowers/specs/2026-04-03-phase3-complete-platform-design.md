# Phase 3 — Complete Platform Spec
**Date:** 2026-04-03  
**Status:** Approved  
**Goal:** Elevate the Bahrain ecommerce base template from Phase 2 completeness (~53/100 vs Bagisto) to a production-ready, white-label platform for Bahraini/Middle Eastern clients — matching and exceeding Bagisto's feature set.

---

## Context

### What exists (Phase 2 complete)
- **Catalog**: Products, Variants (JSONB attributes), Categories, Attributes, Reviews, Tags — simple/configurable product type only
- **Customers**: Auth (register/login/logout/password reset), Profiles, Addresses — 30/30 tests
- **Cart**: Guest (X-Cart-Session) + DB auth, VAT 10%, coupon system, abandonment tracking, merge on login — full events
- **Orders**: Checkout → order lifecycle → status history — 42/42 tests. **No invoice or shipment model.**
- **Payments**: Tap Payments v2 (BENEFIT, BenefitPay QR, cards, Apple Pay), HMAC webhook, partial refunds — 28/28 tests
- **Admin**: Filament v5 — Coupon, Customer, TapTransaction, Refund resources only
- **Notifications**: Resend, bilingual AR/EN, OrderConfirmation + PaymentReceipt + ShippingUpdate — 10/10 tests

### What's missing vs Bagisto + extras
See gap analysis in comparison scorecard. Summary: invoices, shipments, shipping module, product types, customer groups, wishlists, search, promotions, RMA, loyalty, inventory ledger, multi-currency, full admin, analytics.

---

## Architecture Principles (non-negotiable, inherited from CLAUDE.md)

- All BHD amounts stored as **integer fils** — never float
- Controllers thin — all business logic in Service classes
- Services fire Events — modules communicate via events only, never cross-call services
- Queue jobs must be idempotent
- `lockForUpdate()` on all inventory decrements
- Form Requests handle all validation
- API Resources handle all JSON responses
- New modules follow same structure: `app/Modules/{Name}/` with Controllers, Models, Services, Events, Listeners, Requests, Resources, Filament, Jobs

---

## Phase 3A — Foundation Fixes
**Unblocks everything else. Build first.**

### 3A.1 — Store Settings
**New table:** `store_settings`
```
key (string, unique)
value (text)
group (string)  -- 'legal', 'branding', 'commerce'
```
Seeded defaults: `cr_number`, `vat_number`, `company_name_en`, `company_name_ar`, `company_address_en`, `company_address_ar`, `logo_path`, `favicon_path`, `support_email`, `support_phone`.

Admin: Filament Settings page (not a Resource — a custom Page) with grouped form sections.

### 3A.2 — Invoice Model (Orders module)
**New tables:** `invoices`, `invoice_items`

```
invoices:
  id, order_id (FK), invoice_number (unique, sequential), 
  subtotal_fils, vat_fils, discount_fils, total_fils,
  cr_number, vat_number (snapshot at invoice time),
  issued_at, created_at, updated_at

invoice_items:
  id, invoice_id (FK), variant_id (FK nullable),
  name_en, name_ar, sku, quantity,
  unit_price_fils, vat_rate (decimal 5,4 default 0.1000),
  vat_fils, total_fils
```

**Invoice number format:** `INV-{YYYY}-{sequential 6-digit zero-padded}` — sequence stored in `store_settings.last_invoice_sequence`, incremented in a DB transaction with `lockForUpdate()`.

**When generated:** On `PaymentCaptured` event (or COD order confirmation). `GenerateInvoiceJob` (queued, idempotent — checks if invoice exists before creating).

**API:** `GET /orders/{id}/invoice` — returns invoice JSON (also used for PDF generation later).

### 3A.3 — Shipment Model (Orders module)
**New tables:** `shipments`, `shipment_items`

```
shipments:
  id, order_id (FK), shipment_number (unique),
  carrier (string), tracking_number (nullable),
  shipped_at (nullable), delivered_at (nullable),
  notes (text nullable), created_by (FK users nullable),
  created_at, updated_at

shipment_items:
  id, shipment_id (FK), order_item_id (FK),
  quantity_shipped
```

**Status flow:** Order gains `shipped` and `delivered` statuses. `OrderFulfilled` event already exists — fire it when shipment is marked delivered.

**Admin action:** "Create Shipment" on order detail page — select items, enter carrier + tracking.

### 3A.4 — COD Payment Method
No Tap integration. On checkout, if method = `cash_on_delivery`:
- Order created with `payment_method = 'cod'`, `order_status = 'pending_collection'`
- Invoice generated immediately (not waiting for payment capture)
- `PaymentCaptured`-equivalent event fired when admin marks as collected

New `order_status` values: `pending_collection`, `collected`.

Admin action: "Mark as Collected" button on COD orders.

---

## Phase 3B — Catalog Expansion

### 3B.1 — Product Types

Add `product_type` enum to `products` table: `'simple'`, `'configurable'`, `'bundle'`, `'downloadable'`, `'virtual'`.

Existing products are `configurable` (have variants). Products without variants are `simple`.

**Bundle Products**
New tables:
```
bundle_options:
  id, product_id (FK), name_en, name_ar, required (bool), sort_order

bundle_option_products:
  id, bundle_option_id (FK), product_id (FK), 
  default_quantity, min_quantity, max_quantity,
  price_override_fils (nullable -- null = use product price)
```
Bundle price = sum of selected option products' prices. Bundle has no variants. Cart item stores selected configuration as JSON.

**Downloadable Products**
New tables:
```
downloadable_links:
  id, product_id (FK), name_en, name_ar,
  file_path (stored in private S3/local disk),
  downloads_allowed (int, 0 = unlimited),
  sort_order

downloadable_link_purchases:
  id, downloadable_link_id (FK), order_item_id (FK), order_id (FK),
  download_count, last_downloaded_at, expires_at (nullable)
```
Download endpoint: `GET /downloads/{token}` — token is HMAC-signed, expires 24h per access, decrements `download_count`. No shipping step for downloadable orders.

**Virtual Products**
No additional tables. `product_type = 'virtual'`. At checkout, if ALL items are virtual — skip shipping address + shipping method selection entirely.

### 3B.2 — Meilisearch Integration (Real-time, Bilingual)

**Index name:** `products`

**Indexed fields:**
```json
{
  "id": 1,
  "name_en": "Blue T-Shirt",
  "name_ar": "تيشيرت أزرق",
  "description_en": "...",
  "description_ar": "...",
  "sku_list": ["SKU-001", "SKU-001-M", "SKU-001-L"],
  "category_ids": [1, 3],
  "category_names_en": ["Clothing", "T-Shirts"],
  "category_names_ar": ["ملابس", "تيشيرتات"],
  "price_fils": 5000,
  "is_active": true,
  "in_stock": true,
  "product_type": "configurable",
  "tags": ["summer", "casual"]
}
```

**Trigger:** `ProductObserver` fires `IndexProductJob` (queued) on `created`/`updated`/`deleted`. Job is idempotent.

**Meilisearch settings:** `searchableAttributes: ['name_en', 'name_ar', 'description_en', 'description_ar', 'sku_list', 'tags']`, `filterableAttributes: ['category_ids', 'is_active', 'in_stock', 'product_type', 'price_fils']`, `sortableAttributes: ['price_fils']`.

**API:** `GET /search?q=...&locale=ar&category=...&min_price=...&max_price=...&sort=price_asc`

---

## Phase 3C — Customer Features

### 3C.1 — Customer Groups

**New table:** `customer_groups`
```
id, name_en, name_ar, slug (unique),
description (text nullable),
is_default (bool -- one group is default for new registrations)
```

**Add to `users` table:** `customer_group_id (FK nullable, null = default group)`

**Group-specific pricing:** New table `variant_group_prices`
```
id, variant_id (FK), customer_group_id (FK),
price_fils, compare_at_price_fils (nullable),
unique(variant_id, customer_group_id)
```

**Group visibility:** `product_group_visibility` table
```
id, product_id (FK), customer_group_id (FK)
-- if rows exist for a product, only those groups can see it
-- no rows = visible to all
```

**Impact on cart:** `CartService` reads group pricing for authenticated users. Guest users get standard price. `CouponService` checks coupon's allowed groups.

**Impact on payments:** `customer_group_id` can restrict which payment methods appear at checkout (stored in `group_payment_methods` pivot).

**Admin:** Customer Groups Filament resource — CRUD, assign members, configure group prices per variant inline.

### 3C.2 — Wishlist

**New tables:**
```
wishlists:
  id, user_id (FK), share_token (string unique nullable),
  is_public (bool default false), created_at

wishlist_items:
  id, wishlist_id (FK), variant_id (FK), added_at
  unique(wishlist_id, variant_id)
```

**Share token:** UUID generated on demand, stored as signed hash. `GET /wishlists/shared/{token}` returns public wishlist (no auth).

**API endpoints:**
- `GET /wishlist` — authenticated user's wishlist
- `POST /wishlist/items` — add variant
- `DELETE /wishlist/items/{variantId}` — remove
- `POST /wishlist/share` — generate share token, return URL
- `POST /wishlist/items/{variantId}/move-to-cart` — move item to cart
- `GET /wishlists/shared/{token}` — public, no auth

### 3C.3 — Product Compare

No persistent DB table — compare list lives in user session / frontend state. Backend provides:

**API endpoint:** `POST /compare` — accepts array of up to 4 variant IDs, returns comparison matrix:
```json
{
  "products": [...],
  "attributes": {
    "Color": ["Red", "Blue", null, "Green"],
    "Size": ["M", "L", "XL", null],
    "Material": ["Cotton", "Cotton", "Polyester", "Cotton"]
  }
}
```
Attributes are merged across all 4 products' JSONB attribute fields. Nulls where a product doesn't have that attribute.

---

## Phase 3D — Commerce Engine

### 3D.1 — Shipping Module

**New module:** `app/Modules/Shipping/`

**New tables:**
```
shipping_zones:
  id, name_en, name_ar, countries (json array), regions (json array nullable)

shipping_methods:
  id, shipping_zone_id (FK), carrier (string), name_en, name_ar,
  type (enum: 'flat_rate', 'free_threshold', 'carrier_api'),
  rate_fils (nullable), free_threshold_fils (nullable),
  is_active (bool), sort_order,
  config (jsonb -- carrier-specific API keys etc)

order_shipping:
  id, order_id (FK), shipping_method_id (FK),
  carrier, method_name_en, method_name_ar,
  rate_fils (snapshot), tracking_number (nullable)
```

**Carrier interface:**
```php
interface ShippingCarrierInterface {
    public function getRates(Address $address, array $items): array;
    public function createShipment(Order $order, Shipment $shipment): string; // returns tracking number
    public function trackShipment(string $trackingNumber): ShipmentStatus;
}
```

Built-in implementations: `FlatRateCarrier`, `FreeThresholdCarrier`. Stubs: `AramexCarrier`, `DHLCarrier` (implement interface, throw `NotImplementedException` until API keys configured).

**Cart integration:** `ShippingService::getAvailableRates(cart, address)` called during checkout. Rates cached per cart for 10 minutes.

**Checkout change:** Shipping address + method selection step added. Virtual-only carts skip this.

### 3D.2 — Promotion Rule Engine

**New module:** `app/Modules/Promotions/`

**New tables:**
```
promotion_rules:
  id, name_en, name_ar, description (text nullable),
  is_active (bool), priority (int -- lower = applied first),
  is_exclusive (bool -- if true, stops other rules from applying),
  starts_at (nullable), expires_at (nullable),
  max_uses_global (nullable), max_uses_per_user (nullable),
  created_at, updated_at

promotion_conditions:
  id, promotion_rule_id (FK),
  type (enum: 'cart_total', 'item_qty', 'customer_group', 'product_in_cart', 'category_in_cart'),
  operator (enum: 'gte', 'lte', 'eq', 'in', 'not_in'),
  value (jsonb)

promotion_actions:
  id, promotion_rule_id (FK),
  type (enum: 'percent_off_cart', 'fixed_off_cart', 'free_shipping', 'percent_off_items', 'bogo'),
  value (jsonb -- e.g. {"percent": 10} or {"amount_fils": 500})

promotion_usages:
  id, promotion_rule_id (FK), user_id (FK nullable), order_id (FK nullable),
  used_at
```

**RuleEngine service:** `PromotionService::getApplicableRules(Cart $cart, User|null $user): Collection` — evaluates conditions, returns matching rules ordered by priority. Applied in `CartService` after coupon calculation. Exclusive rule stops further rule application.

**Relationship to coupons:** Promotions are auto-applied (no code). Coupons require a code. Both can stack unless a promotion is `exclusive`. Promotion discount tracked separately in cart as `promotion_discount_fils`.

### 3D.3 — Multi-Currency Display

**New table:** `currencies`
```
id, code (char 3, unique), name_en, name_ar,
exchange_rate_from_bhd (decimal 15,6),
symbol_en, symbol_ar,
decimal_places (int default 3),
is_active (bool), is_default (bool),
updated_at
```

Seeded: BHD (default, rate=1), KWD, SAR, AED.

**Storage:** Always BHD fils in DB. Never store converted amounts.

**Display:** `CurrencyService::convert(int $fils, string $currency): int` — returns converted minor units. API responses include `display_price` field with formatted string based on `Accept-Currency` header or `?currency=` param.

**Exchange rates:** Manual update via admin + optional scheduled job to fetch from open exchange rates API.

---

## Phase 3E — Post-Purchase & Loyalty

### 3E.1 — RMA / Returns Management

**New module:** `app/Modules/Returns/`

**New tables:**
```
return_requests:
  id, order_id (FK), user_id (FK),
  request_number (unique, sequential: RMA-YYYY-XXXXXX),
  status (enum: 'pending', 'approved', 'rejected', 'completed'),
  reason (enum: 'defective', 'wrong_item', 'not_as_described', 'changed_mind', 'other'),
  notes (text nullable), admin_notes (text nullable),
  resolution (enum: 'refund', 'store_credit', 'exchange', nullable),
  resolution_amount_fils (nullable),
  created_at, updated_at

return_request_items:
  id, return_request_id (FK), order_item_id (FK),
  quantity_returned, condition (enum: 'unopened', 'opened', 'damaged')
```

**Status flow:** `pending` → `approved`/`rejected` (admin) → `completed` (after refund/credit issued).

**Events:** `ReturnRequested`, `ReturnApproved`, `ReturnRejected`, `ReturnCompleted` — Notifications module listens to send customer emails.

**Resolution options:** 
- `refund` → triggers `RefundService::createRefund()` (existing Payments module)
- `store_credit` → credits `loyalty_points` with equivalent fils value (Phase 3E.2)
- `exchange` → creates new order (Phase 4 scope — stub for now)

**API:** Customer-facing endpoints for submitting and tracking. Admin Filament resource for review workflow.

### 3E.2 — Loyalty / Points System

**New module:** `app/Modules/Loyalty/`

**New tables:**
```
loyalty_accounts:
  id, user_id (FK unique), points_balance (int default 0),
  lifetime_points_earned (int default 0), created_at

loyalty_transactions:
  id, loyalty_account_id (FK),
  type (enum: 'earn', 'redeem', 'expire', 'adjust', 'store_credit'),
  points (int -- positive=earn, negative=redeem/expire),
  reference_type (string nullable -- 'order', 'return', 'admin'),
  reference_id (int nullable),
  description_en (string), description_ar (string),
  expires_at (nullable), created_at

loyalty_config:
  key (string unique), value (string)
  -- keys: points_per_fil (e.g. '1' = 1 point per 1 fil spent)
  --       fils_per_point (e.g. '10' = 10 fils per point when redeeming)
  --       max_redeem_percent (e.g. '0.20' = max 20% of order total)
  --       points_expiry_days (e.g. '365', 0 = no expiry)
```

**Earn:** `EarnPointsJob` on `PaymentCaptured` — calculates points from order subtotal_fils using config rate, credits account.

**Redeem:** At checkout, optional "Use points" toggle. `CartService` calculates max redeemable points (capped at `max_redeem_percent` of total). Applied as `points_discount_fils` in cart.

**Admin:** LoyaltyConfig settings page + manual adjustment action on CustomerResource.

### 3E.3 — Inventory Audit Ledger

**New table:** `inventory_movements`
```
id, variant_id (FK),
type (enum: 'sale', 'cancellation', 'return', 'manual_in', 'manual_out', 'reservation', 'release'),
quantity_delta (int -- positive=stock in, negative=stock out),
quantity_after (int -- snapshot of stock after movement),
reference_type (string nullable), reference_id (int nullable),
notes (text nullable),
created_by (FK users nullable),
created_at
```

**Integration:** `InventoryService` writes a movement record for every stock change — currently silently updates `inventory_items.quantity`. No change to existing locking pattern.

**Admin:** Read-only movements log tab on VariantResource. Filterable by type and date.

---

## Phase 3F — Full Admin + Analytics

### 3F.1 — Filament Resource Completion

All resources use Filament v5 Schema API (`$schema->schema([...])`). Reference `ProductResource.php` for conventions.

**Catalog:**
- `ProductResource` — extend to support all product types with dynamic form sections (bundle options inline, downloadable links inline, variant grid)
- `CategoryResource` — already exists, verify completeness
- `AttributeResource` — already exists, verify completeness

**Orders:**
- `OrderResource` — full CRUD: order detail, status history timeline, invoice download button, "Create Shipment" action, "Mark Collected" for COD
- `InvoiceResource` — read-only list + detail view
- `ShipmentResource` — create/edit with tracking updates

**Inventory:**
- `InventoryResource` — per-variant stock level + manual adjustment action + movements log tab

**Shipping:**
- `ShippingZoneResource` — CRUD zones + methods inline
- `ShippingMethodResource` — nested under zones

**Customers (extend existing):**
- `CustomerResource` — add group assignment, loyalty points balance + manual adjust action, return requests tab

**Loyalty:**
- `LoyaltyConfigPage` — custom Filament page for earn/redeem config
- `LoyaltyTransactionResource` — read-only audit log

**Returns:**
- `ReturnRequestResource` — list + detail + approve/reject actions

**Settings:**
- `StoreSettingsPage` — custom Filament page grouped by: Legal (CR, VAT), Branding (name AR/EN, logo), Commerce (default currency, points config)

**Promotions:**
- `PromotionRuleResource` — CRUD with conditions + actions as repeaters

**Customer Groups:**
- `CustomerGroupResource` — CRUD + member list + variant pricing tab

### 3F.2 — Analytics Dashboard

**Filament dashboard widgets** (custom `StatsOverviewWidget` + chart widgets):

**KPI row:** Total Revenue (month), Orders (month), New Customers (month), Avg Order Value — with delta vs last month.

**Charts:**
- Revenue by day (last 30 days) — line chart
- Orders by status — donut chart
- Top 10 products by revenue — bar chart
- Low stock alerts — table widget (variants with qty < threshold)

**Reports page** (custom Filament page):
- Date range filter
- Report types: Sales Summary, Product Performance, Customer Acquisition
- Export to CSV via queued job + download link

---

## Module Dependency Order

```
3A (Foundation: settings, invoices, shipments, COD)
  └─ 3B (Catalog: product types + search)
  └─ 3C (Customer: groups + wishlist + compare)
       └─ 3D (Commerce: shipping + promotions + currency)
            └─ 3E (Post-purchase: RMA + loyalty + inventory ledger)
                 └─ 3F (Admin + Analytics: ties everything together)
```

3B and 3C can be built in parallel after 3A completes.
3D requires customer groups (for promotion conditions) and shipping zones (new in 3D).
3E requires orders + payments + loyalty foundation.
3F wraps everything — built last.

---

## New Events to Add (extend event map in CLAUDE.md)

| Event | Fired by | Listened by |
|---|---|---|
| `InvoiceGenerated` | Orders | Notifications (send invoice email) |
| `ShipmentCreated` | Orders | Notifications (shipping update) |
| `ShipmentDelivered` | Orders | Notifications (delivery confirmation) |
| `CODCollected` | Payments | Orders (mark paid), Notifications (receipt) |
| `ReturnRequested` | Returns | Notifications (admin alert + customer confirm) |
| `ReturnApproved` | Returns | Notifications (customer email), Payments (trigger refund if resolution=refund) |
| `ReturnRejected` | Returns | Notifications (customer email) |
| `ReturnCompleted` | Returns | Notifications (customer email), Loyalty (store credit if resolution=store_credit) |
| `PointsEarned` | Loyalty | Notifications (points earned email — optional) |
| `PointsRedeemed` | Loyalty | Loyalty (ledger entry) |
| `PromotionApplied` | Promotions | Cart (log) |
| `StockMovementRecorded` | Inventory | — (audit only) |

---

## New Tables Summary (by phase)

**3A:** `store_settings`, `invoices`, `invoice_items`, `shipments`, `shipment_items`
**3B:** `bundle_options`, `bundle_option_products`, `downloadable_links`, `downloadable_link_purchases`
**3C:** `customer_groups`, `variant_group_prices`, `product_group_visibility`, `group_payment_methods`, `wishlists`, `wishlist_items`
**3D:** `shipping_zones`, `shipping_methods`, `order_shipping`, `promotion_rules`, `promotion_conditions`, `promotion_actions`, `promotion_usages`, `currencies`
**3E:** `return_requests`, `return_request_items`, `loyalty_accounts`, `loyalty_transactions`, `loyalty_config`, `inventory_movements`
**3F:** No new tables — Filament resources only

**Total new tables: 28**

---

## Testing Requirements

Every phase must reach 100% feature test coverage before moving to next phase. Pattern:
- Service layer unit-style feature tests (all edge cases)
- Controller/API endpoint tests (auth, validation, ownership)
- Event/listener integration tests
- Admin panel tests (Filament `livewire` test helpers)

Minimum test counts (estimated):
- 3A: ~25 tests
- 3B: ~35 tests
- 3C: ~30 tests
- 3D: ~40 tests
- 3E: ~40 tests
- 3F: ~30 tests (admin)

---

## Session Execution Guide

Each phase starts a fresh session:

```
"Read docs/superpowers/specs/2026-04-03-phase3-complete-platform-design.md 
and CLAUDE.md. We are executing Phase 3A. 
Use the writing-plans skill to produce the implementation plan, 
then execute it."
```

Within phases, use `superpowers:dispatching-parallel-agents` for independent tasks.
After each phase, run the full test suite and update CLAUDE.md section 8 progress.

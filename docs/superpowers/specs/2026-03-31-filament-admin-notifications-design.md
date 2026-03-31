# Design: Filament Admin Panel + Notifications Module
**Date:** 2026-03-31
**Phase:** 2 — Commerce (final two items)

---

## Scope

Two independent subsystems completing Phase 2:
1. **Filament v3 Admin Panel** — full CRUD for all modules, RBAC via Spatie Permission
2. **Notifications Module** — 3 transactional emails via Resend

---

## 1. Schema Changes

One migration: `add_fulfillment_fields_to_orders_table`

| Column | Type | Default | Purpose |
|---|---|---|---|
| `locale` | `string(5)` | `'ar'` | Drives email language in Notifications |
| `tracking_number` | `string` nullable | `null` | Set by admin on fulfillment |
| `fulfilled_at` | `timestamp` nullable | `null` | Set when admin marks order fulfilled |

- All columns nullable or have defaults — zero-downtime safe
- `locale` captured at checkout from frontend request; existing orders default to `'ar'`
- `down()` drops all three columns

---

## 2. Filament Admin Panel

### Installation
- `filament/filament` v3
- `spatie/laravel-permission`
- Panel path: `/admin`, guard: `web`
- Resource discovery: glob `app/Modules/*/Filament/Resources/`

### RBAC
- `User` model: `HasRoles` trait
- `AdminPanelProvider::canAccessPanel()`: `$user->hasRole('super_admin')`
- `Gate::before()` wildcard: super_admin bypasses all permission checks
- `AdminSeeder`: creates `super_admin` role + default admin user (credentials from `.env`)
- Future roles (ops, support) added to seeder with explicit permissions — no structural changes

### Directory Structure
```
app/Modules/Orders/Filament/Resources/
    OrderResource.php
    OrderResource/
        Pages/ListOrders.php
        Pages/ViewOrder.php
        RelationManagers/OrderItemsRelationManager.php
        RelationManagers/StatusHistoryRelationManager.php

app/Modules/Catalog/Filament/Resources/
    ProductResource.php
    ProductResource/
        Pages/ListProducts.php, CreateProduct.php, EditProduct.php
        RelationManagers/VariantsRelationManager.php
        RelationManagers/ImagesRelationManager.php
        RelationManagers/TagsRelationManager.php
        RelationManagers/ReviewsRelationManager.php
    CategoryResource.php
    CategoryResource/
        Pages/ListCategories.php, CreateCategory.php, EditCategory.php
        RelationManagers/ChildCategoriesRelationManager.php
    AttributeResource.php
    AttributeResource/
        Pages/ListAttributes.php, CreateAttribute.php, EditAttribute.php
        RelationManagers/AttributeValuesRelationManager.php

app/Modules/Payments/Filament/Resources/
    TapTransactionResource.php   (read-only)
    RefundResource.php

app/Modules/Customers/Filament/Resources/
    CustomerResource.php         (read-only)
    CustomerResource/
        RelationManagers/OrdersRelationManager.php

app/Modules/Cart/Filament/Resources/
    CouponResource.php
    CouponResource/
        RelationManagers/ApplicableItemsRelationManager.php
        RelationManagers/UsageHistoryRelationManager.php
```

### Resource Specifications

#### OrderResource
- **Pages:** List, View (no Edit — orders are actioned not edited)
- **Table columns:** order_number, customer name, status badge (colored), total BHD, created_at, locale flag
- **Table actions:**
  - **Mark Fulfilled** — modal: optional tracking_number input → sets `fulfilled_at = now()`, status → `fulfilled`, fires `OrderFulfilled`
  - **Cancel** — confirmation modal → fires `OrderCancelled` (inventory + coupon release handled by existing listeners)
  - **Override Status** — modal: status select + required note → appends to `order_status_history`
- **Relation managers:** OrderItems (read-only), StatusHistory (read-only timeline)

#### ProductResource
- **Form fields:** name_ar, name_en, description_ar, description_en, price_fils, category (select), is_active toggle, tags (multi-select)
- **Relation managers:**
  - Variants: sku, price_fils, stock, attributes JSONB
  - Images: file upload
  - Reviews: list with approve/hide toggle

#### CategoryResource
- **Form fields:** name_ar, name_en, slug (auto-generated), parent_id (nullable select), image upload, sort_order

#### AttributeResource
- **Form fields:** name_ar, name_en, type (select/text/color)
- **Relation manager:** AttributeValues — label_ar, label_en, value

#### TapTransactionResource
- **Read-only list/view:** charge_id, amount (formatted BHD), status badge, order link
- **View page:** full `tap_response` JSON in expandable code block

#### RefundResource
- **Table:** customer name, order number, amount BHD, reason, status badge, requested_at
- **Actions:**
  - **Approve** → fires `RefundApproved`
  - **Reject** — modal: required rejection reason textarea → fires `RefundRejected`

#### CustomerResource
- **Read-only:** profile fields (name, email, phone), address list
- **Relation manager:** Orders (read-only, links to OrderResource)

#### CouponResource
- **Form fields:** code, discount_type (percentage/fixed_fils), discount_value, minimum_order_amount_fils, maximum_discount_fils, max_uses_global, max_uses_per_user, starts_at, expires_at, is_active
- **Relation managers:**
  - ApplicableItems: type selector (product/category) + searchable select for target model
  - UsageHistory: read-only list of coupon_usage rows

---

## 3. Notifications Module

### Setup
- Install `resend/resend-laravel`
- `MAIL_MAILER=resend`, `RESEND_API_KEY` in env
- `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` configurable

### Mailables

| Class | Event trigger | Queue | Content |
|---|---|---|---|
| `OrderConfirmationMail` | `OrderPlaced` | `notifications` | Order #, items, subtotal, VAT 10%, total BHD |
| `PaymentReceiptMail` | `PaymentCaptured` | `notifications` | Payment confirmed, Tap charge ID, amount, invoice ref |
| `ShippingUpdateMail` | `OrderFulfilled` | `notifications` | Order shipped, tracking number (if set) |

All implement `ShouldQueue`. All live in `app/Modules/Notifications/Mail/`.

### Locale Handling
- Each mailable calls `app()->setLocale($order->locale)` in `content()`
- Markdown templates use `__()` translation keys
- Lang files: `lang/ar/emails.php` + `lang/en/emails.php`
- RTL template styling: **TODO** (deferred — Markdown default for now)

### Listeners
All in `app/Modules/Notifications/Listeners/`:
- `SendOrderConfirmationEmail` — listens `OrderPlaced`, dispatches `OrderConfirmationMail`
- `SendPaymentReceiptEmail` — listens `PaymentCaptured`, dispatches `PaymentReceiptMail`
- `SendShippingUpdateEmail` — listens `OrderFulfilled`, dispatches `ShippingUpdateMail`

Registered in `NotificationsServiceProvider::boot()`.

### Failure Handling
- Queue retries: 3 attempts, exponential backoff
- Failed jobs → `failed_jobs` table
- No custom retry logic for MVP

---

## 4. Testing Strategy

### Admin Panel (Pest)
- `actingAs($adminUser)` + Filament's `livewire()` helper
- Test per resource: list renders, create/edit form validates, actions fire correct events
- Test `canAccessPanel()` rejects non-admin users

### Notifications (Pest)
- `Mail::fake()` + assert mailable dispatched with correct recipient and locale
- Test listener responds to each event
- Test locale is set correctly on the mailable (AR order → AR email, EN order → EN email)

---

## 5. Out of Scope (Phase 3)
- Dashboard widgets (revenue stats, low stock alerts, pending refunds)
- Additional admin roles (ops manager, support agent)
- Courier tracking links in shipping emails
- Custom RTL email templates
- Refund notification emails (RefundApproved/Rejected listeners)

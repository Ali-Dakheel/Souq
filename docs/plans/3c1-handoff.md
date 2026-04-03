# Phase 3C.1 Handoff — Customer Groups

**Date:** 2026-04-03  
**Branch:** create fresh from `main` (start: `git checkout -b feature/3c1-customer-groups`)  
**Starting test count:** 265/265 passing  
**Base commit:** `6642434`

---

## What this feature does

Customer Groups segment authenticated users into priced tiers. Groups affect:
1. **Variant pricing** — group-specific price overrides per variant
2. **Product visibility** — hide products from certain groups
3. **Payment methods** — restrict payment methods per group (optional, can stub for MVP)
4. **Cart pricing** — `CartService` reads group price for the authenticated user

Guest users always get the standard variant price.

---

## Tables to create (5 migrations)

### 1. `customer_groups`
```
id (bigint PK)
name_en (string 255)
name_ar (string 255)
slug (string unique)
description (text nullable)
is_default (bool default false)  -- exactly one row should have is_default=true
timestamps
```

### 2. Add `customer_group_id` to `users`
```sql
ALTER TABLE users ADD COLUMN customer_group_id BIGINT UNSIGNED NULL;
-- FK: customer_groups.id, nullOnDelete (group deleted = user falls back to default)
-- Index on customer_group_id
```
**Zero-downtime:** nullable with no default (safe to add).

### 3. `variant_group_prices`
```
id (bigint PK)
variant_id (FK → variants.id, cascadeOnDelete)
customer_group_id (FK → customer_groups.id, cascadeOnDelete)
price_fils (unsignedInteger NOT NULL)
compare_at_price_fils (unsignedInteger nullable)
unique(variant_id, customer_group_id)
timestamps
```

### 4. `product_group_visibility`
```
id (bigint PK)
product_id (FK → products.id, cascadeOnDelete)
customer_group_id (FK → customer_groups.id, cascadeOnDelete)
-- Semantics: if ANY rows exist for product_id, only those groups can see it.
--            Zero rows = visible to all.
unique(product_id, customer_group_id)
(no timestamps needed)
```
Set `$timestamps = false` on model.

### 5. `group_payment_methods`  ← stub for MVP, just the table
```
id (bigint PK)
customer_group_id (FK → customer_groups.id, cascadeOnDelete)
payment_method (string 50)  -- 'tap_card', 'benefit_pay', 'cod', etc.
unique(customer_group_id, payment_method)
(no timestamps)
```
No service logic needed yet — table only.

---

## New module: `app/Modules/Customers/`

Customer Groups live in the Customers module (already exists for auth/profiles/addresses).

### New files

**Models:**
- `app/Modules/Customers/Models/CustomerGroup.php`
  - `$fillable`: name_en, name_ar, slug, description, is_default
  - Relationships: `users()` hasMany User, `variantGroupPrices()` hasMany VariantGroupPrice, `productVisibilities()` hasMany ProductGroupVisibility
  - Scopes: `scopeDefault()`

- `app/Modules/Customers/Models/VariantGroupPrice.php`
  - `$fillable`: variant_id, customer_group_id, price_fils, compare_at_price_fils
  - Relationships: `variant()` belongsTo, `group()` belongsTo CustomerGroup
  - Cast: price_fils → integer, compare_at_price_fils → integer (nullable)

- `app/Modules/Customers/Models/ProductGroupVisibility.php`
  - `$fillable`: product_id, customer_group_id
  - `$timestamps = false`

**Update `User.php`:**
- Add `customer_group_id` to `$fillable`
- Add relationship: `customerGroup()` belongsTo CustomerGroup

**Update `Variant.php` (Catalog module):**
- Add relationship: `groupPrices()` hasMany VariantGroupPrice

**Service:**
- `app/Modules/Customers/Services/CustomerGroupService.php`
  - `listGroups(): Collection`
  - `createGroup(array $data): CustomerGroup` — slugify name_en if slug not provided, enforce single default (set others to false if is_default=true)
  - `updateGroup(CustomerGroup $group, array $data): CustomerGroup` — same default enforcement
  - `deleteGroup(CustomerGroup $group): void` — throw if trying to delete default group
  - `setGroupPrice(Variant $variant, CustomerGroup $group, array $data): VariantGroupPrice`
  - `removeGroupPrice(Variant $variant, CustomerGroup $group): void`
  - `getGroupPriceForUser(?User $user, Variant $variant): int` — returns group price fils if found, else variant price_fils

**Update `CartService.php`:**
- In the method that gets variant price for cart items, call `CustomerGroupService::getGroupPriceForUser($user, $variant)` for authenticated users
- Guest users: use standard variant price

**Requests:**
- `app/Modules/Customers/Requests/StoreCustomerGroupRequest.php`
  - name_en: required, string, max:255
  - name_ar: required, string, max:255
  - slug: sometimes, string, max:255, unique:customer_groups,slug
  - description: nullable, string
  - is_default: boolean
- `app/Modules/Customers/Requests/UpdateCustomerGroupRequest.php` (same, slug unique ignore current)
- `app/Modules/Customers/Requests/StoreGroupPriceRequest.php`
  - variant_id: required, exists:variants,id
  - price_fils: required, integer, min:0
  - compare_at_price_fils: nullable, integer, min:0

**Controller:**
- `app/Modules/Customers/Controllers/CustomerGroupController.php`
  - `index()` — GET /groups
  - `store()` — POST /groups (auth:sanctum)
  - `show()` — GET /groups/{group}
  - `update()` — PUT/PATCH /groups/{group} (auth:sanctum)
  - `destroy()` — DELETE /groups/{group} (auth:sanctum)
  - `setPrice()` — POST /groups/{group}/prices (auth:sanctum)
  - `removePrice()` — DELETE /groups/{group}/prices/{variant} (auth:sanctum)

**Resources:**
- `CustomerGroupResource` — id, name_en, name_ar, slug, description, is_default, created_at
- `VariantGroupPriceResource` — id, variant_id, customer_group_id, price_fils, compare_at_price_fils

**Routes (add to Customers module routes file):**
```php
Route::prefix('api/v1')->middleware('api')->group(function () {
    Route::get('groups', [CustomerGroupController::class, 'index']);
    Route::get('groups/{group}', [CustomerGroupController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('groups', [CustomerGroupController::class, 'store']);
        Route::put('groups/{group}', [CustomerGroupController::class, 'update']);
        Route::patch('groups/{group}', [CustomerGroupController::class, 'update']);
        Route::delete('groups/{group}', [CustomerGroupController::class, 'destroy']);
        Route::post('groups/{group}/prices', [CustomerGroupController::class, 'setPrice']);
        Route::delete('groups/{group}/prices/{variant}', [CustomerGroupController::class, 'removePrice']);
    });
});
```

**Filament resource:**
- `app/Modules/Customers/Filament/Resources/CustomerGroupResource.php`
  - Table: name_en, name_ar, slug, is_default badge, users_count
  - Form (Schema API — Filament v5): name_en, name_ar, slug (auto-generated), description, is_default toggle
  - No inline variant pricing in Filament for MVP (keep it simple)

---

## CartService integration detail

In `CartService`, find where cart item totals are computed. The relevant method reads `$variant->price_fils`. Change it to:

```php
$priceFilsForUser = $this->customerGroupService->getGroupPriceForUser(
    Auth::user(),   // null for guest
    $variant
);
```

Inject `CustomerGroupService` via constructor into `CartService`. Use `app()` resolution if circular dependency (unlikely here — Customers → Cart is fine, Cart → Customers is safe).

---

## Seeder

`database/seeders/CustomerGroupSeeder.php`:
- Create one default group: `{ name_en: 'General', name_ar: 'عام', slug: 'general', is_default: true }`
- Register in `DatabaseSeeder.php`

---

## Tests to write

File: `tests/Feature/Customers/CustomerGroupTest.php`

1. **CRUD tests (~10)**
   - list groups returns paginated collection
   - create group returns 201 with correct fields
   - create group with is_default=true sets others to false
   - create group without slug auto-generates slug
   - update group
   - delete non-default group
   - delete default group returns 422
   - get group by id
   - unauthenticated create/update/delete returns 401

2. **Pricing tests (~6)**
   - set group price on variant returns 201
   - update group price (upsert) returns 201
   - remove group price returns 204
   - getGroupPriceForUser returns group price for authenticated user in that group
   - getGroupPriceForUser returns standard price for guest
   - getGroupPriceForUser returns standard price when no group price set

3. **Cart integration tests (~4)**
   - cart item uses group price for authenticated user with group price set
   - cart item uses standard price for guest
   - cart item uses standard price for user without group price
   - cart total reflects group price correctly

**Total target: ~20 tests**

---

## Key gotchas

- `is_default` enforcement: when creating/updating with `is_default=true`, SET ALL OTHERS to false in a single query before setting the new one. Wrap in DB::transaction.
- `customer_group_id` on users is nullable — null means "use default group logic" at the service level, NOT a FK violation.
- `CartService` already uses `Auth::user()` — pass it directly to `getGroupPriceForUser()`.
- Filament v5: use `Schema $schema: Schema` (NOT `Form $form: Form`) — see CLAUDE.md.
- When creating Variants in tests, always include `'attributes' => []` (JSONB NOT NULL).
- `ProductGroupVisibility` and `GroupPaymentMethod` models: `$timestamps = false`.

---

## After 3C.1 merges — next tasks

| Phase | What | Notes |
|---|---|---|
| 3C.2 | Wishlist | See spec §3C.2 — additive migration for share_token + is_public needed |
| 3C.3 | Product Compare | No DB — POST /compare endpoint only |
| 3B.2 | Meilisearch | ProductObserver → IndexProductJob, bilingual index, GET /search |

---

## Session start command

```
Read docs/plans/3c1-handoff.md and CLAUDE.md. We are on main with 265/265 tests passing.
Implement Phase 3C.1 Customer Groups in full: migrations, models, service, controller, resources,
routes, Filament resource, CartService integration, and ~20 feature tests.
Use the subagent-driven-development workflow: implementer → spec reviewer → code quality reviewer per task.

- PHP binary: /c/Users/User/.config/herd/bin/php84/php
- Working directory: C:\Users\User\Desktop\souq\Souq\backend
- Full spec: docs/superpowers/specs/2026-04-03-phase3-complete-platform-design.md §3C.1
```

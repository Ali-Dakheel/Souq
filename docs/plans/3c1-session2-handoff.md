# Phase 3C.1 Session 2 Handoff — Customer Groups (continuation)

**Date:** 2026-04-03  
**Branch:** `feature/3c1-customer-groups`  
**Worktree:** `.worktrees/feature-3c1-customer-groups`  
**Starting tests:** 265/265 passing  
**PHP binary:** `/c/Users/User/.config/herd/bin/php84/php`  
**Working dir for commands:** `C:\Users\User\Desktop\souq\Souq\.worktrees\feature-3c1-customer-groups\backend`

---

## Session 1 completed tasks

### Task 1: Migrations ✅ DONE (in worktree)
5 migrations created and run:
- `2026_04_03_000015_create_customer_groups_table.php`
- `2026_04_03_000016_add_customer_group_id_to_users_table.php`
- `2026_04_03_000017_create_variant_group_prices_table.php`
- `2026_04_03_000018_create_product_group_visibility_table.php`
- `2026_04_03_000019_create_group_payment_methods_table.php`

### Task 2: Models ✅ DONE (in worktree)
- `app/Modules/Customers/Models/CustomerGroup.php` — created
- `app/Modules/Customers/Models/VariantGroupPrice.php` — created
- `app/Modules/Customers/Models/ProductGroupVisibility.php` — created
- `app/Models/User.php` — customer_group_id added to fillable + customerGroup() relation
- `app/Modules/Catalog/Models/Variant.php` — groupPrices() hasMany added

### Task 3: CustomerGroupService ✅ DONE (in worktree)
`app/Modules/Customers/Services/CustomerGroupService.php` — all 7 methods:
- listGroups(), createGroup(), updateGroup(), deleteGroup()
- setGroupPrice(), removeGroupPrice(), getGroupPriceForUser()

### Task 4: HTTP layer ⚠️ PARTIALLY DONE — FILES IN WRONG DIRECTORY
The implementer accidentally created files in `backend/` (main repo) instead of the worktree.

**Files in MAIN backend that need to be moved to worktree:**
- `app/Modules/Customers/Requests/StoreCustomerGroupRequest.php`
- `app/Modules/Customers/Requests/UpdateCustomerGroupRequest.php`
- `app/Modules/Customers/Requests/StoreGroupPriceRequest.php`
- `app/Modules/Customers/Controllers/CustomerGroupController.php`
- `app/Modules/Customers/Resources/CustomerGroupResource.php`
- `app/Modules/Customers/Resources/VariantGroupPriceResource.php`
- Routes added to `app/Modules/Customers/routes.php` in main (also need in worktree)

**Action needed at session start:**
```bash
MAIN="/c/Users/User/Desktop/souq/Souq/backend"
WT="/c/Users/User/Desktop/souq/Souq/.worktrees/feature-3c1-customer-groups/backend"

# Copy files to worktree
cp $MAIN/app/Modules/Customers/Requests/StoreCustomerGroupRequest.php  $WT/app/Modules/Customers/Requests/
cp $MAIN/app/Modules/Customers/Requests/UpdateCustomerGroupRequest.php  $WT/app/Modules/Customers/Requests/
cp $MAIN/app/Modules/Customers/Requests/StoreGroupPriceRequest.php      $WT/app/Modules/Customers/Requests/
cp $MAIN/app/Modules/Customers/Controllers/CustomerGroupController.php   $WT/app/Modules/Customers/Controllers/
cp $MAIN/app/Modules/Customers/Resources/CustomerGroupResource.php       $WT/app/Modules/Customers/Resources/
cp $MAIN/app/Modules/Customers/Resources/VariantGroupPriceResource.php   $WT/app/Modules/Customers/Resources/

# Also sync routes.php (copy the full file)
cp $MAIN/app/Modules/Customers/routes.php $WT/app/Modules/Customers/routes.php

# Then remove from main backend (restore originals with git)
cd $MAIN
git checkout app/Modules/Customers/routes.php
git checkout app/Models/User.php
git checkout app/Modules/Catalog/Models/Variant.php
rm app/Modules/Customers/Requests/StoreCustomerGroupRequest.php
rm app/Modules/Customers/Requests/UpdateCustomerGroupRequest.php
rm app/Modules/Customers/Requests/StoreGroupPriceRequest.php
rm app/Modules/Customers/Controllers/CustomerGroupController.php
rm app/Modules/Customers/Resources/CustomerGroupResource.php
rm app/Modules/Customers/Resources/VariantGroupPriceResource.php
```

---

## Remaining tasks (not yet done)

### Task 5: CartService integration
File: `app/Modules/Cart/Services/CartService.php`
- Inject `CustomerGroupService` via constructor
- Find where cart item price is computed (look for `$variant->price_fils`)
- Replace with: `$this->customerGroupService->getGroupPriceForUser(Auth::user(), $variant)`
- Use `app()` resolution if there's a circular dependency issue

### Task 6: Seeder
File: `database/seeders/CustomerGroupSeeder.php`
- Create: `{ name_en: 'General', name_ar: 'عام', slug: 'general', is_default: true }` using firstOrCreate
- Register in `database/seeders/DatabaseSeeder.php`

### Task 7: Filament admin resource
File: `app/Modules/Customers/Filament/Resources/CustomerGroupResource.php`
- **CRITICAL:** Use Filament v5 Schema API (`Schema $schema: Schema`, NOT `Form $form: Form`)
- Table: name_en, name_ar, slug, is_default badge (TextColumn->badge()), users_count
- Form: name_en, name_ar, slug, description, is_default toggle
- Discovered via `discoverResources()` — no manual registration needed

### Task 8: Feature tests
File: `tests/Feature/Customers/CustomerGroupTest.php`
- CRUD tests (~10), pricing tests (~6), cart integration tests (~4)
- Total target: ~20 tests
- See full test list in `docs/plans/3c1-handoff.md`

---

## Key gotchas for remaining work

### CartService
- `CartService` is in `app/Modules/Cart/Services/CartService.php`
- It uses `Auth::user()` already — pass directly to `getGroupPriceForUser()`
- Variant prices come from `$variant->price_fils` — find ALL occurrences

### Filament v5 (NOT v3)
- Always use `Schema $schema: Schema` in form() methods
- Use `TextColumn::make('is_default')->badge()->color(fn ($state) => $state ? 'success' : 'gray')`
- `withCount('users')` on the query, then `TextColumn::make('users_count')`

### Tests
- Always include `'attributes' => []` in Variant::create() calls (JSONB NOT NULL)
- Use `actingAs($user)` for authenticated endpoints
- For CartService tests: create a user, assign to a group, add items to cart, verify prices

---

## Session 2 start command

```
Read docs/plans/3c1-session2-handoff.md. We are implementing Phase 3C.1 Customer Groups.

First, execute the file migration commands from the handoff doc to move HTTP layer files 
from the main backend to the worktree and clean up the main backend.

Then continue with the subagent-driven-development workflow for tasks 5-8:
- Task 5: CartService integration (CustomerGroupService injection)
- Task 6: CustomerGroupSeeder
- Task 7: Filament CustomerGroupResource (v5 Schema API)
- Task 8: ~20 feature tests in CustomerGroupTest.php

PHP binary: /c/Users/User/.config/herd/bin/php84/php
Worktree backend: C:\Users\User\Desktop\souq\Souq\.worktrees\feature-3c1-customer-groups\backend
```

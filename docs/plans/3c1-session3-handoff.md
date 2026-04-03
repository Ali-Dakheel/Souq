# Phase 3C.1 Session 3 Handoff — Customer Groups (continuation)

**Date:** 2026-04-03  
**Branch:** `feature/3c1-customer-groups`  
**Worktree:** `.worktrees/feature-3c1-customer-groups`  
**Starting tests:** 265/265 passing (confirmed after Task 5)  
**PHP binary:** `/c/Users/User/.config/herd/bin/php84/php`  
**Working dir for commands:** `C:\Users\User\Desktop\souq\Souq\.worktrees\feature-3c1-customer-groups\backend`

---

## Session 2 completed tasks

### Task 5: CartService integration ✅ DONE

**What was done:**
- `CustomerGroupService` injected into `CartService` constructor (`private readonly CustomerGroupService $customerGroupService`)
- `Auth` facade imported in CartService
- In `addItem()`, `price_fils_snapshot` now uses `$this->customerGroupService->getGroupPriceForUser(Auth::user(), $variant)` instead of `$variant->effective_price_fils`
- `CustomerGroupService::getGroupPriceForUser()` fallback stays as `$variant->price_fils` (not `effective_price_fils`) — spec-compliant

**All 265 tests still passing.**

---

## Remaining tasks

### Task 6: CustomerGroupSeeder

File: `database/seeders/CustomerGroupSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Modules\Customers\Models\CustomerGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerGroupSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $exists = CustomerGroup::where('slug', 'general')->exists();
            if (! $exists) {
                // Set all others to non-default before creating
                CustomerGroup::query()->update(['is_default' => false]);
                CustomerGroup::create([
                    'name_en' => 'General',
                    'name_ar' => 'عام',
                    'slug' => 'general',
                    'is_default' => true,
                ]);
            }
        });
    }
}
```

OR use firstOrCreate pattern (simpler, safe to re-run):

```php
CustomerGroup::firstOrCreate(
    ['slug' => 'general'],
    ['name_en' => 'General', 'name_ar' => 'عام', 'is_default' => true]
);
```

Register in `database/seeders/DatabaseSeeder.php`:
```php
$this->call(CustomerGroupSeeder::class);
```

(DatabaseSeeder currently calls: AdminSeeder, StoreSettingsSeeder)

---

### Task 7: Filament CustomerGroupResource (v5 Schema API)

File to create: `app/Modules/Customers/Filament/Resources/CustomerGroupResource.php`

**CRITICAL:** Filament v5 is installed (`"filament/filament": "^5.4"`). Use `Schema $schema: Schema`, NOT `Form $form: Form`.

The `AdminPanelProvider` already has:
```php
->discoverResources(
    in: app_path('Modules/Customers/Filament/Resources'),
    for: 'App\\Modules\\Customers\\Filament\\Resources',
)
```
So the resource will be auto-discovered — no manual registration needed.

Pages subdirectory needed: `app/Modules/Customers/Filament/Resources/CustomerGroupResource/Pages/`

**Spec:**
- Table columns: `name_en` (searchable, sortable), `name_ar`, `slug`, `is_default` (badge: success=true/gray=false), `users_count` (requires `withCount('users')` on query)
- Form fields: `name_en` (required), `name_ar` (required), `slug` (optional, auto-generated from name_en), `description` (textarea, nullable), `is_default` (toggle)
- NavigationGroup: `'Customers'`, icon: `heroicon-o-user-group`, navigationSort: 8

**Reference existing CustomerResource at:**
`app/Modules/Customers/Filament/Resources/CustomerResource.php`

**Key Filament v5 patterns:**
```php
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

public static function form(Schema $schema): Schema
{
    return $schema->schema([
        TextInput::make('name_en')->required()->maxLength(255),
        // ...
    ]);
}

public static function table(Table $table): Table
{
    return $table->columns([
        TextColumn::make('name_en')->searchable()->sortable(),
        TextColumn::make('is_default')->badge()
            ->color(fn ($state) => $state ? 'success' : 'gray'),
        TextColumn::make('users_count')->label('Users'),
    ]);
}
```

For `users_count`, override `getEloquentQuery()`:
```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->withCount('users');
}
```

---

### Task 8: Feature tests (~20 tests)

File: `tests/Feature/Customers/CustomerGroupTest.php`

**Important:** Do NOT use `php artisan make:test` — it double-nests paths. Write the file directly.

**Test list:**

**CRUD (~10 tests):**
1. `list_groups_returns_collection` — GET /api/v1/groups returns 200 with array
2. `create_group_returns_201` — POST /api/v1/groups with auth returns 201, correct fields
3. `create_group_with_is_default_true_sets_others_to_false` — two groups, set new as default, verify old is false
4. `create_group_without_slug_auto_generates_slug` — omit slug, verify slug is generated from name_en
5. `update_group` — PUT /api/v1/groups/{id} updates fields
6. `delete_non_default_group_returns_204` — 204 response
7. `delete_default_group_returns_422` — 422 response
8. `show_group_returns_correct_fields` — GET /api/v1/groups/{id}
9. `unauthenticated_create_returns_401` — no token → 401
10. `unauthenticated_delete_returns_401` — no token → 401

**Pricing (~6 tests):**
11. `set_group_price_returns_201` — POST /api/v1/groups/{id}/prices with variant_id + price_fils
12. `update_group_price_upserts` — set price twice, only one row exists
13. `remove_group_price_returns_204` — DELETE /api/v1/groups/{id}/prices/{variant_id}
14. `get_group_price_for_user_returns_group_price` — service method returns group price for user in group
15. `get_group_price_for_guest_returns_standard_price` — null user → $variant->price_fils
16. `get_group_price_no_group_price_returns_standard` — user in group but no price set → $variant->price_fils

**Cart integration (~4 tests):**
17. `cart_item_uses_group_price_for_user_with_group_price` — addItem with authenticated user in group → snapshot = group price
18. `cart_item_uses_standard_price_for_guest` — guest cart → snapshot = variant->price_fils
19. `cart_item_uses_standard_price_for_user_without_group_price` — auth user, no group price → snapshot = variant->price_fils
20. `cart_total_reflects_group_price` — calculateTotals returns correct subtotal using group price

**Test setup helpers:**
```php
use App\Models\User;
use App\Modules\Catalog\Models\Variant;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Category;
use App\Modules\Cart\Services\CartService;
use App\Modules\Customers\Models\CustomerGroup;
use App\Modules\Customers\Services\CustomerGroupService;

// When creating Variants, ALWAYS include 'attributes' => [] (JSONB NOT NULL)
$variant = Variant::create([
    'product_id' => $product->id,
    'sku' => 'SKU-'.uniqid(),
    'price_fils' => 10000,
    'is_available' => true,
    'attributes' => [],
]);

// Cart integration tests need CartService from container
$cartService = app(CartService::class);
```

---

## Key files already in place (worktree)

| File | Status |
|---|---|
| `app/Modules/Customers/Models/CustomerGroup.php` | ✅ Done |
| `app/Modules/Customers/Models/VariantGroupPrice.php` | ✅ Done |
| `app/Modules/Customers/Models/ProductGroupVisibility.php` | ✅ Done |
| `app/Models/User.php` | ✅ customer_group_id in fillable + relation |
| `app/Modules/Catalog/Models/Variant.php` | ✅ groupPrices() relation |
| `app/Modules/Customers/Services/CustomerGroupService.php` | ✅ Done |
| `app/Modules/Customers/Controllers/CustomerGroupController.php` | ✅ Done |
| `app/Modules/Customers/Requests/StoreCustomerGroupRequest.php` | ✅ Done |
| `app/Modules/Customers/Requests/UpdateCustomerGroupRequest.php` | ✅ Done |
| `app/Modules/Customers/Requests/StoreGroupPriceRequest.php` | ✅ Done |
| `app/Modules/Customers/Resources/CustomerGroupResource.php` | ✅ Done |
| `app/Modules/Customers/Resources/VariantGroupPriceResource.php` | ✅ Done |
| `app/Modules/Customers/routes.php` | ✅ Routes added |
| `app/Modules/Cart/Services/CartService.php` | ✅ Group pricing injected |
| Migrations (5) | ✅ Run in worktree |

---

## Session 3 start command

```
Read docs/plans/3c1-session3-handoff.md. We are implementing Phase 3C.1 Customer Groups.

Continue with the subagent-driven-development workflow for remaining tasks:
- Task 6: CustomerGroupSeeder (simple seeder + register in DatabaseSeeder)
- Task 7: Filament CustomerGroupResource (v5 Schema API — CRITICAL: use Schema $schema: Schema)
- Task 8: ~20 feature tests in CustomerGroupTest.php

PHP binary: /c/Users/User/.config/herd/bin/php84/php
Worktree backend: C:\Users\User\Desktop\souq\Souq\.worktrees\feature-3c1-customer-groups\backend

All work goes in the WORKTREE, not the main backend.
After all tasks complete, commit everything in the worktree and run the full test suite.
```

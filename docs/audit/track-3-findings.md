# Track 3: Database Schema, Migration Safety & Model Alignment Audit

**Audited:** 81 migrations, 49 models, config/database.php
**Date:** 2026-04-10

## Summary

| Severity | Count |
|---|---|
| BLOCKING | 5 |
| SHOULD FIX | 12 |
| OPTIONAL | 8 |

---

## BLOCKING

### B-01: `ProductGroupVisibility` model missing `$table` declaration

- **File:** `app/Modules/Customers/Models/ProductGroupVisibility.php`
- **Rule:** Model-migration alignment / irregular table name
- **Current:** No `$table` property. Laravel will pluralize the class name to `product_group_visibilities`, but the migration creates `product_group_visibility` (singular).
- **Fix:** Add `protected $table = 'product_group_visibility';`

### B-02: `User::wishlists()` is `HasMany` but `wishlists.user_id` has UNIQUE constraint

- **File:** `app/Models/User.php` line 67
- **Rule:** Model-migration alignment / relationship matches constraint
- **Current:** `public function wishlists(): HasMany` — but the `wishlists` table has `user_id` as `UNIQUE`, enforcing at most one wishlist per user.
- **Fix:** Change to `public function wishlist(): HasOne` — a HasMany against a unique column is misleading and allows code to call `$user->wishlists()->create()` multiple times, which will throw a unique violation at DB level.

### B-03: `LoyaltyTransaction` model missing `created_at` cast

- **File:** `app/Modules/Loyalty/Models/LoyaltyTransaction.php`
- **Rule:** Model-migration alignment / casts match column types
- **Current:** `$timestamps = false` and `created_at` is defined via `$table->timestamp('created_at')->useCurrent()` in migration but there is no `'created_at' => 'datetime'` cast. The `created_at` column will be returned as a raw string, not a Carbon instance, when accessed.
- **Fix:** Add `'created_at' => 'datetime'` to `$casts`.

### B-04: `InventoryMovement` model missing `created_at` cast

- **File:** `app/Modules/Inventory/Models/InventoryMovement.php`
- **Rule:** Model-migration alignment / casts match column types
- **Current:** Same pattern as B-03. `$timestamps = false`, migration has `$table->timestamp('created_at')->useCurrent()`, but no `'created_at' => 'datetime'` cast.
- **Fix:** Add `'created_at' => 'datetime'` to `$casts`.

### B-05: Currency module referenced in CLAUDE.md Phase 3D.3 as complete but no migration or model exists

- **File:** `database/migrations/` (missing), `app/Modules/Currency/` (missing)
- **Rule:** Schema completeness
- **Current:** CLAUDE.md section 8 marks "3D.3 Multi-currency display" as complete with `currencies` table, `CurrencyService`, Filament resource, etc. But no `currencies` migration and no `Currency` module directory exist in the codebase.
- **Fix:** Either create the migration and module, or update CLAUDE.md to remove the checkmark. If the currency feature was built in the frontend-only repo and the backend was never implemented, CLAUDE.md is misleading. **Note:** This could be a documentation-only issue if currencies are served from a different source; verify intent.

---

## SHOULD FIX

### S-01: `banners.start_date` is NOT NULL without DEFAULT — zero-downtime risk

- **File:** `database/migrations/2026_03_28_000027_create_banners_table.php` line 19
- **Rule:** Zero-downtime migration safety — non-nullable without default
- **Current:** `$table->date('start_date');` — NOT NULL, no default. This is a CREATE TABLE so it's safe on initial deploy, but if you ever need to add rows programmatically you must always supply this value. Not a live issue today, but note for consistency.
- **Fix:** Add `->default(now())` or make nullable if semantics allow.

### S-02: Multiple `enum` columns used instead of string + CHECK constraint

- **Files:** 15+ migrations use `$table->enum(...)` (attributes, customer_profiles, orders, refunds, etc.)
- **Rule:** PostgreSQL best practice / zero-downtime migration safety
- **Current:** Enum columns in PostgreSQL create a CHECK constraint. Adding new enum values requires `ALTER TABLE ... DROP CONSTRAINT` + re-add, which is done correctly in later migrations (000007, 000008, 000009) but is fragile. The `orders` table has been through 3 separate enum-expansion migrations already.
- **Fix:** For future tables, prefer `$table->string('column_name')` with a CHECK constraint gated on `pgsql`. Existing enums are fine but track the maintenance cost.

### S-03: `ReturnRequest` model missing `$casts` for enum and money fields

- **File:** `app/Modules/Returns/Models/ReturnRequest.php`
- **Rule:** Model-migration alignment / casts match column types
- **Current:** No `$casts` array at all. `resolution_amount_fils` should be cast to `integer`, and the `status`, `reason`, `resolution` fields are enums with no explicit casting (works as strings, but inconsistent with other models).
- **Fix:** Add `protected $casts = ['resolution_amount_fils' => 'integer'];`

### S-04: `ReturnRequestItem` model missing `$casts`

- **File:** `app/Modules/Returns/Models/ReturnRequestItem.php`
- **Rule:** Model-migration alignment / casts match column types
- **Current:** No `$casts` array. `quantity_returned` should be cast to `integer` for type consistency.
- **Fix:** Add `protected $casts = ['quantity_returned' => 'integer'];`

### S-05: `order_status_history` SQLite recreation drops FK constraints permanently

- **File:** `database/migrations/2026_04_03_000009_add_cod_statuses_to_order_status_history.php`
- **Rule:** Zero-downtime migration safety
- **Current:** The SQLite branch recreates the `order_status_history` table with `FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE` but then copies data with `INSERT INTO ... SELECT * FROM ...`. However, foreign key checking is turned off during this process and the recreation uses raw SQL that may not exactly match the original Blueprint's column types/sizes.
- **Fix:** This is a testing-only concern (SQLite is only used in tests per project rules), but the raw SQL recreation loses any column metadata differences. Acceptable for now but fragile.

### S-06: `orders` SQLite recreation in migration 000007 loses FK for `delivery_zone_id` and `delivery_method_id`

- **File:** `database/migrations/2026_04_03_000007_add_shipped_delivered_statuses_to_orders.php`
- **Rule:** Zero-downtime migration safety
- **Current:** The recreated `orders` table only includes FKs for `user_id`, `shipping_address_id`, `billing_address_id` — but the original migration also had FKs for `delivery_zone_id` and `delivery_method_id`. These FKs are lost in SQLite after this migration runs.
- **Fix:** Not critical since SQLite is test-only, and migration 2026_04_04_000004 drops those columns anyway. But if tests run migrations in order, there's a window where FKs are missing.

### S-07: `coupon_usage` table — `CouponUsage` model missing `order` relationship

- **File:** `app/Modules/Cart/Models/CouponUsage.php`
- **Rule:** Model-migration alignment / relationships match foreign keys
- **Current:** The migration defines `order_id` FK but the model only has `coupon()` and `user()` relationships. No `order()` relationship exists.
- **Fix:** Add `public function order(): BelongsTo { return $this->belongsTo(Order::class); }`

### S-08: `product_tag_pivot` — no model, `product_id` index missing

- **File:** `database/migrations/2026_03_28_000009_create_product_tag_pivot_table.php`
- **Rule:** Missing indexes on foreign keys
- **Current:** The pivot table has FK on `product_id` (via the cascade) but no explicit `$table->index('product_id')`. There IS a unique composite `['product_id', 'product_tag_id']` which covers lookups by `product_id` first, so this is partially mitigated. The `product_tag_id` index is present.
- **Fix:** Low priority — the unique composite index covers `product_id` lookups. No action needed unless query patterns change.

### S-09: `cart_abandonments.cart_id` FK index missing

- **File:** `database/migrations/2026_03_30_000004_create_cart_abandonments_table.php`
- **Rule:** Missing indexes on foreign keys
- **Current:** `cart_id` has a FK constraint and `nullOnDelete()` but no explicit index. Only `user_id`, `session_id`, and `abandoned_at` are indexed.
- **Fix:** Add `$table->index('cart_id');`

### S-10: `Shipment` model missing `created_by` index usage note

- **File:** `database/migrations/2026_04_03_000005_create_shipments_table.php`
- **Rule:** Missing indexes on foreign keys
- **Current:** `created_by` has FK constraint but no index. The `shipments` table has indexes on `order_id`, `status`, `tracking_number` but not on `created_by`.
- **Fix:** Add `$table->index('created_by');` — needed if admin dashboard filters shipments by creator.

### S-11: `downloadable_links` table FK `product_id` — redundant index

- **File:** `database/migrations/2026_04_03_000013_create_downloadable_links_table.php`
- **Rule:** Index hygiene (not critical)
- **Current:** Has both FK constraint and explicit `$table->index('product_id')`. Laravel auto-creates an index for FK constraints on some databases. Minor bloat.
- **Fix:** Optional — remove explicit index if FK auto-creates one.

### S-12: `config/database.php` default connection is `sqlite`, not `pgsql`

- **File:** `config/database.php` line 20
- **Rule:** Production configuration
- **Current:** `'default' => env('DB_CONNECTION', 'sqlite')` — falls back to SQLite. This is fine if `.env` always sets `DB_CONNECTION=pgsql`, but risky if `.env` is missing in a deploy scenario.
- **Fix:** Change default to `'pgsql'` to match the production stack, or verify deploy pipeline always sets the env var.

---

## OPTIONAL

### O-01: `WishlistItem` model includes `added_at` in `$fillable` but original migration uses `useCurrent()`

- **File:** `app/Modules/Customers/Models/WishlistItem.php`
- **Current:** `added_at` is in fillable but the column auto-fills via `useCurrent()`. Harmless but unnecessary in fillable.

### O-02: `CategoryImage` explicitly sets `$table` but it matches Laravel convention

- **File:** `app/Modules/Catalog/Models/CategoryImage.php`
- **Current:** `protected $table = 'category_images'` — Laravel would infer this. No harm but unnecessary.

### O-03: Several models with `$timestamps = false` that have `created_at` via `useCurrent()` should document this

- **Files:** `ProductReviewVote`, `CouponApplicableItem`, `OrderItem`, `OrderStatusHistory`, `ShipmentItem`, `PromotionCondition`, `PromotionAction`, `PromotionUsage`, `LoyaltyTransaction`, `InventoryMovement`
- **Current:** These models disable Eloquent timestamps but have `created_at` populated by the DB default. This is a valid pattern but could confuse developers who expect `created_at` to be auto-managed by Eloquent.

### O-04: `bundle_option_products` table has no timestamps but `BundleOptionProduct` model has `$timestamps = false`

- **File:** Both aligned correctly. No issue.

### O-05: `media` table has no corresponding model in `app/Modules/`

- **File:** `database/migrations/2026_03_28_000026_create_media_table.php`
- **Current:** The `media` table migration exists but no model was found in any module. This may be intentional if using a package like Spatie MediaLibrary, but worth confirming.

### O-06: `store_hours`, `store_closures`, `notification_preferences`, `push_notification_tokens`, `banners`, `static_pages` — no corresponding models found

- **Files:** Various migrations with no models
- **Current:** These tables exist in migrations but no Eloquent models were found in any module. They may be managed through Filament directly or planned for future phases.

### O-07: `variant_group_prices` uses `unsignedInteger` for `price_fils` instead of `integer`

- **File:** `database/migrations/2026_04_03_000017_create_variant_group_prices_table.php`
- **Current:** `$table->unsignedInteger('price_fils')` — while prices should always be positive, every other `_fils` column uses plain `integer`. Inconsistency. Not a bug since prices are inherently positive.

### O-08: `product_type` on `products` table uses `string` instead of `enum`

- **File:** `database/migrations/2026_04_03_000010_add_product_type_to_products_table.php`
- **Current:** `$table->string('product_type')->default('simple')` — no validation at DB level. The model uses string comparison (`=== 'bundle'` etc.) which is correct but no DB constraint prevents invalid values.
- **Fix:** Consider adding a CHECK constraint gated on pgsql, or validate in Form Request only.

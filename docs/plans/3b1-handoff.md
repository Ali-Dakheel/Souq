# Phase 3B.1 Handoff — Product Types

**Date:** 2026-04-03  
**Branch:** `feature/3b1-product-types`  
**Status:** Tasks 1–3 complete. Task 4 (tests) remaining.  
**Test baseline:** 240/240 passing

---

## Commit history for this branch

```
8fe4292  feat(3B.1): add download token endpoint GET /downloads/{token}
fd0e283  feat(3B.1): add product type models, resources, service methods, routes
d5aa396  feat(3B.1): add product_type + bundle + downloadable migrations
73a64c1  feat: Phase 3A complete — security fixes + 64 new tests (221/221)  ← branch point
```

---

## What is done (Tasks 1–3)

### Task 1 ✅ — Migrations (commit d5aa396)

5 new migration files in `backend/database/migrations/`:

| File | What it does |
|---|---|
| `2026_04_03_000010_add_product_type_to_products_table.php` | Adds `product_type` string column, default `'simple'` |
| `2026_04_03_000011_create_bundle_options_table.php` | `bundle_options` table (product_id, name_en, name_ar, required, sort_order) |
| `2026_04_03_000012_create_bundle_option_products_table.php` | `bundle_option_products` table (qty/price per option-product, unique on bundle_option_id+product_id) |
| `2026_04_03_000013_create_downloadable_links_table.php` | `downloadable_links` table (product_id, file_path, downloads_allowed) |
| `2026_04_03_000014_create_downloadable_link_purchases_table.php` | `downloadable_link_purchases` table (link_id, order_item_id, order_id, download_count, last_downloaded_at, expires_at) |

All migrations run. All use string column (not DB enum) for SQLite test compatibility.

### Task 2 ✅ — Models + Service (commit fd0e283)

**New models in `app/Modules/Catalog/Models/`:**
- `BundleOption.php` — belongs to Product, has many BundleOptionProduct, ordered by sort_order
- `BundleOptionProduct.php` — belongs to BundleOption + Product, $timestamps=false
- `DownloadableLink.php` — belongs to Product, has many DownloadableLinkPurchase, ordered by sort_order
- `DownloadableLinkPurchase.php` — belongs to DownloadableLink + Order (for user_id lookup)

**Updated `Product.php`:**
- `product_type` added to `$fillable`
- Relationships: `bundleOptions()`, `downloadableLinks()`
- Helpers: `isBundle()`, `isDownloadable()`, `isVirtual()`, `isSimple()`, `isConfigurable()`

**New API resources in `app/Modules/Catalog/Resources/`:**
- `BundleOptionResource.php`
- `BundleOptionProductResource.php`
- `DownloadableLinkResource.php` — does NOT expose `file_path` (security)

**Updated `ProductResource.php`:** adds `product_type`, `bundle_options` (whenLoaded), `downloadable_links` (whenLoaded)

**Updated form requests:** `StoreProductRequest` + `UpdateProductRequest` — `product_type` validated with `Rule::in(['simple','configurable','bundle','downloadable','virtual'])`

**Updated `ProductService.php`:** added `createBundleOption()`, `addProductToBundleOption()`, `createDownloadableLink()` — all validate product type first.

**Updated `ProductController.php`:** added `storeBundleOption()`, `addBundleOptionProduct()`, `storeDownloadableLink()`

**Updated `routes.php`:** 3 new routes under `auth:sanctum`:
```
POST /api/v1/products/{product}/bundle-options
POST /api/v1/products/{product}/bundle-options/{option}/products
POST /api/v1/products/{product}/downloadable-links
```

### Task 3 ✅ — Download token endpoint (commit 8fe4292)

**New `app/Modules/Catalog/Services/DownloadService.php`:**
- `generateToken(DownloadableLinkPurchase)` — HMAC-SHA256 signed, 24h TTL, base64url payload
- `validateAndDecodeToken(string $token, int $userId)` — verifies sig, expiry, ownership, download limit
- `recordDownload(DownloadableLinkPurchase)` — increments count, updates last_downloaded_at

**New `app/Modules/Catalog/Controllers/DownloadController.php`:**
- `GET /api/v1/downloads/{token}` — auth:sanctum, returns file stream or 403/404 JSON

**19 tests added** in `tests/Feature/Catalog/DownloadTest.php` — all passing.

---

## What remains (Task 4)

### Task 4 🔴 — Feature tests for ALL product type functionality (~35 more tests)

The DownloadTest (19 tests) covers the download endpoint. But we still need Pest feature tests covering:

**File:** `tests/Feature/Catalog/ProductTypeTest.php`

1. **product_type field validation**
   - `store product with valid product_type (simple/configurable/bundle/downloadable/virtual)` — 5 tests
   - `store product with invalid product_type returns 422`
   - `update product product_type`

2. **Bundle option CRUD**
   - `create bundle option on bundle product returns 201`
   - `create bundle option on non-bundle product returns 422` (InvalidArgumentException → 422)
   - `create bundle option requires name_en and name_ar`
   - `add product to bundle option returns 201`
   - `add same product twice to bundle option returns 422` (unique constraint)
   - `product resource includes bundle_options when loaded`

3. **Downloadable link CRUD**
   - `create downloadable link on downloadable product returns 201`
   - `create downloadable link on non-downloadable product returns 422`
   - `create downloadable link requires name_en, name_ar, file_path`
   - `downloadable link resource does not expose file_path`
   - `product resource includes downloadable_links when loaded`

4. **ProductService type validation**
   - `createBundleOption throws InvalidArgumentException for non-bundle`
   - `createDownloadableLink throws InvalidArgumentException for non-downloadable`

**Instructions for the next session:**

```
Read docs/plans/3b1-handoff.md. We are on branch feature/3b1-product-types with 240/240 tests passing.
Task 4 is to write feature tests in tests/Feature/Catalog/ProductTypeTest.php covering the items listed
in the handoff file. After tests pass, commit and then merge to main.
PHP binary: /c/Users/User/.config/herd/bin/php84/php
Working directory: C:\Users\User\Desktop\souq\Souq\backend
```

---

## After 3B.1 merges — remaining Phase 3B+3C work

Execute in this order (each is a fresh session):

| Phase | What | Key files |
|---|---|---|
| 3C.1 | Customer Groups — new module, `customer_groups` table, `variant_group_prices`, `product_group_visibility`, CartService pricing | spec: docs/superpowers/specs/2026-04-03-phase3-complete-platform-design.md §3C.1 |
| 3C.2 | Wishlist — tables exist (wishlists + wishlist_items migrations), need `share_token`+`is_public` additive migration, full module | spec §3C.2 |
| 3C.3 | Product Compare — no DB, POST /compare returns attribute matrix | spec §3C.3 |
| 3B.2 | Meilisearch — ProductObserver → IndexProductJob, bilingual index, GET /search | spec §3B.2 |

---

## Key gotchas for this branch

- `product_type` is a plain `string` column (not DB enum) — SQLite test compatibility
- `DownloadableLinkResource` must NOT include `file_path` in the JSON response
- `DownloadService::validateAndDecodeToken` uses `hash_equals()` for timing-safe HMAC comparison
- `DownloadableLinkPurchase` needs the `order()` relationship to get `user_id` for ownership check
- Test helpers: when creating a `Variant` in tests, always include `'attributes' => []` (JSONB NOT NULL)

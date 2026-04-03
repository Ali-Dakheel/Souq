# 3C.2 + 3C.3 Handoff — Session Continuity Prompt

**Date:** 2026-04-03  
**Status:** Implementation in progress (or complete — check git log)

---

## To Continue This Session

Paste this into a new Claude Code session:

```
Read CLAUDE.md and docs/superpowers/specs/2026-04-03-phase3-complete-platform-design.md.

Current state: Phase 3B and 3C.1 are complete (285/285 tests on main).

We are implementing 3C.2 (Wishlist) and 3C.3 (Product Compare) in parallel worktrees.

Check git log and branch list:
  git log --oneline -10
  git branch -a

Then check if worktrees exist:
  git worktree list

If branches feature/3c2-wishlist and feature/3c3-compare exist and are NOT yet merged:
  - Run tests in each worktree
  - If tests pass, merge both to main and push
  - Then update CLAUDE.md section 8 to mark 3C.2 and 3C.3 complete

If branches do NOT exist yet, implement them:
  - 3C.2 spec is in the phase3 design doc section "3C.2 — Wishlist"
  - 3C.3 spec is in the phase3 design doc section "3C.3 — Product Compare"
  - Use dispatching-parallel-agents skill to run both in worktrees

After merge, next phase is 3B.2 (Meilisearch) or 3D depending on what 3B.2 status is.
PHP binary: /c/Users/User/.config/herd/bin/php84/php
Backend tests: cd backend && /c/Users/User/.config/herd/bin/php84/php artisan test --parallel
```

---

## Spec Summary (from original design doc)

### 3C.2 — Wishlist

**Tables:**
```
wishlists:      id, user_id (FK unique), share_token (string unique nullable),
                is_public (bool default false), created_at, updated_at

wishlist_items: id, wishlist_id (FK cascade), variant_id (FK cascade), added_at
                unique(wishlist_id, variant_id)
```

**API routes (all under /api/v1, auth:sanctum except shared):**
- `GET    /wishlist`                                — own wishlist
- `POST   /wishlist/items`                          — add variant_id
- `DELETE /wishlist/items/{variantId}`              — remove by variant ID
- `POST   /wishlist/share`                          — generate share token
- `POST   /wishlist/items/{variantId}/move-to-cart` — move to cart
- `GET    /wishlists/shared/{token}`                — public, no auth

**Key rules:**
- No `quantity` column on wishlist_items (spec omits it)
- Share token: random UUID stored as-is (not HMAC signed — keep simple)
- CartService::addItem() already exists for move-to-cart
- WishlistService in Customers module

---

### 3C.3 — Product Compare

**No DB table.** Stateless endpoint.

**Route:** `POST /api/v1/compare` — public, no auth

**Request:**
```json
{ "variant_ids": [1, 2, 3, 4] }   // max 4 variants
```

**Response:**
```json
{
  "products": [
    { "id": 1, "name": "T-Shirt", "variant": { "id": 1, "sku": "...", "price_fils": 5000 } },
    ...
  ],
  "attributes": {
    "Color": ["Red", "Blue", null, "Green"],
    "Size":  ["M",   "L",   "XL", null]
  }
}
```

**Logic:** Load variants with product. Union all attribute keys from JSONB. For each attribute key, map each variant's value (null if missing).

**Files to create:**
- `CompareService` in `app/Modules/Catalog/Services/`
- `CompareRequest` in `app/Modules/Catalog/Requests/`
- Controller method on `ProductController`
- Route in `app/Modules/Catalog/routes.php`

---

## Architecture Rules (from CLAUDE.md)

- Controllers thin — logic in Services
- Form Requests for validation
- API Resources for JSON responses
- No floats for money
- SQLite-compatible migrations (no native enums, no ALTER TABLE DROP CONSTRAINT)
- Filament v5 uses `Schema $schema` not `Form $form`

## Test Counts

- Before 3C.2 + 3C.3: **285/285**
- Target after: **315+/315+** (estimate ~15 per feature)

## PHP binary
`/c/Users/User/.config/herd/bin/php84/php`

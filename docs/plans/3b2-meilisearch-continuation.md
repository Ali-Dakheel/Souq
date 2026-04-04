# 3B.2 Meilisearch — Continuation Prompt

**Branch:** `feature/3b2-meilisearch`
**Worktree:** `C:\Users\User\Desktop\souq\Souq\.worktrees\feature\3b2-meilisearch\backend`
**PHP:** `/c/Users/User/.config/herd/bin/php84/php`
**Composer:** `/c/Users/User/.config/herd/bin/php84/php /c/Users/User/.config/herd/bin/composer.phar`
**Baseline tests:** 318/318 (main)

---

## Completed

### Task 1 ✅ (commit fd5e25c)
- Installed `laravel/scout ^11`, `meilisearch/meilisearch-php ^1.16`, `http-interop/http-factory-guzzle ^1.2`
- Published `config/scout.php` with full `index-settings` for `Product::class`
  - searchable: `name_en, name_ar, description_en, description_ar, sku_list, tags`
  - filterable: `category_ids, is_active, in_stock, product_type, price_fils`
  - sortable: `price_fils`
- Added `SCOUT_DRIVER=null` to `.env.testing`
- Added `SCOUT_DRIVER`, `MEILISEARCH_HOST`, `MEILISEARCH_KEY` to `.env.example`

---

## Remaining Tasks

### Task 2: Product model — Searchable trait + toSearchableArray
**File:** `app/Modules/Catalog/Models/Product.php`

Add to the model:
```php
use Laravel\Scout\Searchable;
```

Add trait to class declaration:
```php
class Product extends Model
{
    use SoftDeletes, Searchable;
```

Add these methods:
```php
public function shouldBeSearchable(): bool
{
    return $this->is_available;
}

public function toSearchableArray(): array
{
    $this->loadMissing(['category', 'variants.inventory', 'tags']);

    return [
        'id'                  => $this->id,
        'name_en'             => $this->name['en'] ?? '',
        'name_ar'             => $this->name['ar'] ?? '',
        'description_en'      => $this->description['en'] ?? '',
        'description_ar'      => $this->description['ar'] ?? '',
        'sku_list'            => $this->variants->pluck('sku')->toArray(),
        'category_ids'        => array_filter([$this->category_id]),
        'category_names_en'   => [$this->category?->name['en'] ?? ''],
        'category_names_ar'   => [$this->category?->name['ar'] ?? ''],
        'price_fils'          => $this->base_price_fils,
        'is_active'           => $this->is_available,
        'in_stock'            => $this->variants
            ->sum(fn ($v) => $v->inventory?->quantity_available ?? 0) > 0,
        'product_type'        => $this->product_type,
        'tags'                => $this->tags->pluck('name')->toArray(),
    ];
}
```

Key gotchas:
- `name` and `description` are JSONB arrays cast to `array` — access with `['en']` / `['ar']`
- `base_price_fils` is the product-level price (integer fils, never float)
- `$variant->inventory` is `HasOne` → `InventoryItem`, which has `quantity_available`
- Tags have a `name` field (not `name_en`/`name_ar` — tags are not bilingual in this schema)
- Use `loadMissing` not `load` to avoid redundant queries

No observer needed — Scout's `queue` option in `config/scout.php` triggers auto-queuing via `MakeSearchable`/`RemoveFromSearch` jobs when `SCOUT_QUEUE=true` in production. In tests, `SCOUT_DRIVER=null` skips all indexing.

Commit after this task.

---

### Task 3: SearchRequest + searchProducts() + search endpoint

**File 1: `app/Modules/Catalog/Requests/SearchRequest.php`** (new)
```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'q'            => ['required', 'string', 'min:1', 'max:255'],
            'locale'       => ['sometimes', 'string', Rule::in(['ar', 'en'])],
            'category'     => ['sometimes', 'integer', 'exists:categories,id'],
            'min_price'    => ['sometimes', 'integer', 'min:0'],
            'max_price'    => ['sometimes', 'integer', 'min:0', 'gte:min_price'],
            'sort'         => ['sometimes', 'string', Rule::in(['price_asc', 'price_desc'])],
            'in_stock'     => ['sometimes', 'boolean'],
            'product_type' => ['sometimes', 'string', Rule::in(['simple', 'configurable', 'bundle', 'downloadable', 'virtual'])],
            'per_page'     => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page'         => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
```

**File 2: Add `searchProducts()` to `app/Modules/Catalog/Services/ProductService.php`**
```php
public function searchProducts(
    string $query,
    array $filters = [],
    int $perPage = 20,
    int $page = 1
): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
    $builder = Product::search($query);

    if (isset($filters['category'])) {
        $builder->where('category_ids', (int) $filters['category']);
    }
    if (isset($filters['min_price'])) {
        $builder->where('price_fils', '>=', (int) $filters['min_price']);
    }
    if (isset($filters['max_price'])) {
        $builder->where('price_fils', '<=', (int) $filters['max_price']);
    }
    if (isset($filters['in_stock'])) {
        $builder->where('in_stock', filter_var($filters['in_stock'], FILTER_VALIDATE_BOOLEAN));
    }
    if (isset($filters['product_type'])) {
        $builder->where('product_type', $filters['product_type']);
    }

    match ($filters['sort'] ?? null) {
        'price_asc'  => $builder->orderBy('price_fils', 'asc'),
        'price_desc' => $builder->orderBy('price_fils', 'desc'),
        default      => null,
    };

    return $builder->paginate($perPage, 'page', $page);
}
```

**File 3: Add `search()` to `app/Modules/Catalog/Controllers/ProductController.php`**
```php
public function search(SearchRequest $request): ProductCollection
{
    try {
        $results = $this->productService->searchProducts(
            $request->validated('q'),
            $request->only(['category', 'min_price', 'max_price', 'in_stock', 'product_type', 'sort']),
            (int) $request->integer('per_page', 20),
            (int) $request->integer('page', 1),
        );

        return new ProductCollection($results);
    } catch (\Meilisearch\Exceptions\ApiException $e) {
        abort(503, 'Search service temporarily unavailable.');
    }
}
```
Add `use App\Modules\Catalog\Requests\SearchRequest;` to imports.

**File 4: Add route to `app/Modules/Catalog/routes.php`**
```php
// Search — Meilisearch full-text
Route::get('search', [ProductController::class, 'search']);
```
Add this line before the `compare` route (still inside the `api/v1` prefix group, no auth required).

Commit after this task.

---

### Task 4: Feature tests — SearchTest

**File:** `tests/Feature/Catalog/SearchTest.php`

Look at existing test files like `tests/Feature/Catalog/ProductTest.php` for the exact style/helpers used.

Key test patterns:
```php
// Every test — disable Scout indexing
config(['scout.driver' => 'null']);

// To test search results, mock Scout:
use Laravel\Scout\EngineManager;
// OR use the 'collection' driver for in-memory search

// To test 503, mock Meilisearch throwing:
$this->mock(\Laravel\Scout\EngineManager::class, function ($mock) {
    $mock->shouldReceive('engine')->andThrow(new \Meilisearch\Exceptions\ApiException(...));
});
```

Tests to write (~20):
1. `test_search_requires_q_parameter` — GET /api/v1/search → 422
2. `test_search_q_too_long` — q=str_repeat(256) → 422
3. `test_search_invalid_sort` — sort=invalid → 422
4. `test_search_per_page_max_100` — per_page=101 → 422
5. `test_search_returns_paginated_products` — basic search returns 200 with data/meta
6. `test_search_filters_by_category` — category param hits Scout where()
7. `test_search_filters_by_min_price` — min_price param
8. `test_search_filters_by_max_price` — max_price param
9. `test_search_filters_by_in_stock` — in_stock=true
10. `test_search_filters_by_product_type` — product_type=simple
11. `test_search_sorts_by_price_asc` — sort=price_asc
12. `test_search_sorts_by_price_desc` — sort=price_desc
13. `test_search_respects_per_page` — per_page=5
14. `test_search_meilisearch_down_returns_503` — engine throws ApiException → 503
15. `test_product_is_indexed_on_create` — Queue::fake(), create product, assert MakeSearchable queued
16. `test_product_is_removed_on_delete` — Queue::fake(), delete product, assert RemoveFromSearch queued
17. `test_searchable_array_has_required_fields` — toSearchableArray() returns all required keys
18. `test_searchable_array_price_fils_is_integer` — price_fils is int
19. `test_searchable_array_in_stock_computed` — in_stock true when inventory > 0
20. `test_should_be_searchable_false_when_unavailable` — is_available=false → shouldBeSearchable()=false

For tests 6-14 (filter/sort), mock Scout's engine or use the collection driver (`config(['scout.driver' => 'collection'])`).
For tests 15-16, use `Queue::fake()` and assert `\Laravel\Scout\Jobs\MakeSearchable::class` was queued.

Run: `php artisan test --compact tests/Feature/Catalog/SearchTest.php`

Commit after passing.

---

## Architecture decisions already made (DO NOT change)
- **No custom ProductObserver** — Scout's built-in observer handles it via `Searchable` trait
- **No custom IndexProductJob** — Scout's `MakeSearchable` job is used
- **No custom artisan command** — use `php artisan scout:sync-index-settings`
- **`SCOUT_DRIVER=null` in tests** — zero Meilisearch dependency in CI
- **`queue` driven by `SCOUT_QUEUE` env** — false in local dev, true in production
- **Price is always integer fils** — `price_fils` in the index, never float

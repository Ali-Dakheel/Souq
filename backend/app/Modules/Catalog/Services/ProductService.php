<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\BundleOption;
use App\Modules\Catalog\Models\BundleOptionProduct;
use App\Modules\Catalog\Models\DownloadableLink;
use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * Paginated product list with optional filters.
     *
     * Supported filters:
     *   category_id   int
     *   tag_id        int
     *   available     bool
     *   min_price     int  (fils)
     *   max_price     int  (fils)
     *   search        string
     */
    public function listProducts(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Product::with(['category', 'variants.inventory', 'tags'])
            ->when(
                isset($filters['category_id']),
                fn ($q) => $q->byCategory((int) $filters['category_id'])
            )
            ->when(
                isset($filters['tag_id']),
                fn ($q) => $q->byTag((int) $filters['tag_id'])
            )
            ->when(
                isset($filters['available']),
                fn ($q) => $q->available()
            )
            ->when(
                isset($filters['min_price']),
                fn ($q) => $q->where('base_price_fils', '>=', (int) $filters['min_price'])
            )
            ->when(
                isset($filters['max_price']),
                fn ($q) => $q->where('base_price_fils', '<=', (int) $filters['max_price'])
            )
            ->when(
                isset($filters['search']) && $filters['search'] !== '',
                fn ($q) => $q->where(function ($sub) use ($filters) {
                    $term = '%'.mb_strtolower($filters['search']).'%';
                    $sub->whereRaw('LOWER(name::text) LIKE ?', [$term])
                        ->orWhere('slug', 'LIKE', $term);
                })
            )
            ->orderBy('sort_order')
            ->orderByDesc('created_at');

        return $query->paginate($perPage);
    }

    public function getProduct(int|string $idOrSlug): Product
    {
        $query = Product::with([
            'category.image',
            'variants.inventory',
            'tags',
        ]);

        return is_numeric($idOrSlug)
            ? $query->findOrFail((int) $idOrSlug)
            : $query->where('slug', $idOrSlug)->firstOrFail();
    }

    /**
     * Create a product with its variants and inventory items.
     *
     * @param  array{
     *     name: array,
     *     slug?: string,
     *     description?: array,
     *     category_id?: int,
     *     base_price_fils: int,
     *     is_available?: bool,
     *     images?: array,
     *     sort_order?: int,
     *     tag_ids?: int[],
     *     variants?: array<int, array{sku: string, attributes: array, price_fils?: int, is_available?: bool, quantity_available?: int, low_stock_threshold?: int}>,
     * } $data
     */
    public function createProduct(array $data): Product
    {
        $data['slug'] ??= $this->generateSlug($data['name']);

        $variantsData = $data['variants'] ?? [];
        $tagIds = $data['tag_ids'] ?? [];

        unset($data['variants'], $data['tag_ids']);

        $product = Product::create($data);

        foreach ($variantsData as $variantData) {
            $this->createVariantWithInventory($product, $variantData);
        }

        if (! empty($tagIds)) {
            $product->tags()->sync($tagIds);
        }

        return $product->load(['category.image', 'variants.inventory', 'tags']);
    }

    /**
     * Update a product's scalar fields, images, and tag associations.
     *
     * @param  array{
     *     name?: array,
     *     slug?: string,
     *     description?: array,
     *     category_id?: int|null,
     *     base_price_fils?: int,
     *     is_available?: bool,
     *     images?: array,
     *     sort_order?: int,
     *     tag_ids?: int[],
     * } $data
     */
    public function updateProduct(Product $product, array $data): Product
    {
        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['name'], $product->id);
        }

        $tagIds = $data['tag_ids'] ?? null;
        unset($data['tag_ids']);

        $product->update($data);

        if ($tagIds !== null) {
            $product->tags()->sync($tagIds);
        }

        return $product->fresh(['category.image', 'variants.inventory', 'tags']);
    }

    public function deleteProduct(Product $product): void
    {
        $product->delete();
    }

    public function updateImages(Product $product, array $images): Product
    {
        $product->update(['images' => $images]);

        return $product;
    }

    public function syncTags(Product $product, array $tagIds): Product
    {
        $product->tags()->sync($tagIds);

        return $product->fresh('tags');
    }

    public function createVariantWithInventory(Product $product, array $variantData): Variant
    {
        $quantityAvailable = $variantData['quantity_available'] ?? 0;
        $lowStockThreshold = $variantData['low_stock_threshold'] ?? 5;

        unset($variantData['quantity_available'], $variantData['low_stock_threshold']);

        $variant = $product->variants()->create($variantData);

        InventoryItem::create([
            'variant_id' => $variant->id,
            'quantity_available' => $quantityAvailable,
            'quantity_reserved' => 0,
            'low_stock_threshold' => $lowStockThreshold,
        ]);

        return $variant->load('inventory');
    }

    private function generateSlug(array|string $name, ?int $excludeId = null): string
    {
        $base = Str::slug(is_array($name) ? ($name['en'] ?? $name['ar'] ?? '') : $name);
        $slug = $base;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId)) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $excludeId): bool
    {
        return Product::where('slug', $slug)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }

    public function createBundleOption(Product $product, array $data): BundleOption
    {
        if (! $product->isBundle()) {
            throw new \InvalidArgumentException('Product must be of type "bundle" to add bundle options.');
        }

        return $product->bundleOptions()->create($data);
    }

    public function addProductToBundleOption(BundleOption $option, array $data): BundleOptionProduct
    {
        return $option->products()->create($data);
    }

    public function createDownloadableLink(Product $product, array $data): DownloadableLink
    {
        if (! $product->isDownloadable()) {
            throw new \InvalidArgumentException('Product must be of type "downloadable" to add downloadable links.');
        }

        return $product->downloadableLinks()->create($data);
    }
}

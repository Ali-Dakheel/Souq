<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'category_id',
        'base_price_fils',
        'is_available',
        'images',
        'sort_order',
        'product_type',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'images' => 'array',
        'is_available' => 'boolean',
        'base_price_fils' => 'integer',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class)->orderBy('sort_order');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductTag::class,
            'product_tag_pivot',
            'product_id',
            'product_tag_id'
        );
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function bundleOptions(): HasMany
    {
        return $this->hasMany(BundleOption::class);
    }

    public function downloadableLinks(): HasMany
    {
        return $this->hasMany(DownloadableLink::class);
    }

    public function isBundle(): bool
    {
        return $this->product_type === 'bundle';
    }

    public function isDownloadable(): bool
    {
        return $this->product_type === 'downloadable';
    }

    public function isVirtual(): bool
    {
        return $this->product_type === 'virtual';
    }

    public function isSimple(): bool
    {
        return $this->product_type === 'simple';
    }

    public function isConfigurable(): bool
    {
        return $this->product_type === 'configurable';
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    public function scopeByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByTag(Builder $query, int $tagId): Builder
    {
        return $query->whereHas(
            'tags',
            fn (Builder $q) => $q->where('product_tags.id', $tagId)
        );
    }

    /**
     * The lowest effective price across all available variants.
     * Returns base_price_fils if no variants are loaded/available.
     */
    public function getLowestPriceFilsAttribute(): int
    {
        if (! $this->relationLoaded('variants')) {
            return $this->base_price_fils;
        }

        $prices = $this->variants
            ->where('is_available', true)
            ->map(fn (Variant $variant) => $variant->effective_price_fils)
            ->values();

        return $prices->isEmpty() ? $this->base_price_fils : (int) $prices->min();
    }

    public function shouldBeSearchable(): bool
    {
        return (bool) $this->is_available;
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing(['category', 'variants.inventory', 'tags']);

        return [
            'id' => $this->id,
            'name_en' => $this->name['en'] ?? '',
            'name_ar' => $this->name['ar'] ?? '',
            'description_en' => $this->description['en'] ?? '',
            'description_ar' => $this->description['ar'] ?? '',
            'sku_list' => $this->variants->pluck('sku')->toArray(),
            'category_ids' => array_filter([$this->category_id]),
            'category_names_en' => [$this->category?->name['en'] ?? ''],
            'category_names_ar' => [$this->category?->name['ar'] ?? ''],
            'price_fils' => $this->base_price_fils,
            'is_active' => $this->is_available,
            'in_stock' => $this->variants
                ->sum(fn ($v) => $v->inventory?->quantity_available ?? 0) > 0,
            'product_type' => $this->product_type,
            'tags' => $this->tags->pluck('name')->toArray(),
        ];
    }
}

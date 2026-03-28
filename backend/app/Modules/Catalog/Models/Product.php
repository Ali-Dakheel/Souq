<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'category_id',
        'base_price_fils',
        'is_available',
        'images',
        'sort_order',
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
}

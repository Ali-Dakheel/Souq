<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Variant extends Model
{
    protected $fillable = [
        'product_id',
        'sku',
        'attributes',
        'price_fils',
        'is_available',
        'sort_order',
    ];

    protected $casts = [
        'attributes' => 'array',
        'price_fils' => 'integer',
        'is_available' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(InventoryItem::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    /**
     * The selling price for this variant.
     * Falls back to the parent product's base_price_fils when price_fils is null.
     */
    public function getEffectivePriceFilsAttribute(): int
    {
        if ($this->price_fils !== null) {
            return $this->price_fils;
        }

        if ($this->relationLoaded('product')) {
            return $this->product->base_price_fils;
        }

        return (int) $this->newQuery()
            ->join('products', 'products.id', '=', 'variants.product_id')
            ->where('variants.id', $this->id)
            ->value('products.base_price_fils');
    }
}

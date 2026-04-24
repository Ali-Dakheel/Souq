<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Customers\Models\VariantGroupPrice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property array<string, mixed> $attributes
 * @property int|null $price_fils
 * @property bool $is_available
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Product $product
 * @property InventoryItem|null $inventory
 * @property-read int $effective_price_fils
 */
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

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasOne<InventoryItem, $this> */
    public function inventory(): HasOne
    {
        return $this->hasOne(InventoryItem::class);
    }

    /** @return HasMany<VariantGroupPrice, $this> */
    public function groupPrices(): HasMany
    {
        return $this->hasMany(VariantGroupPrice::class);
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

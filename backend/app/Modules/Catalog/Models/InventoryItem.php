<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $variant_id
 * @property int $quantity_available
 * @property int $quantity_reserved
 * @property int $low_stock_threshold
 * @property-read int $quantity_on_sale
 */
class InventoryItem extends Model
{
    protected $table = 'inventory_items';

    protected $fillable = [
        'variant_id',
        'quantity_available',
        'quantity_reserved',
        'low_stock_threshold',
    ];

    protected $casts = [
        'quantity_available' => 'integer',
        'quantity_reserved' => 'integer',
        'low_stock_threshold' => 'integer',
    ];

    /** @return BelongsTo<Variant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    /**
     * @param  Builder<InventoryItem>  $query
     * @return Builder<InventoryItem>
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query
            ->whereColumn('quantity_available', '<=', 'low_stock_threshold')
            ->where('quantity_available', '>', 0);
    }

    /**
     * @param  Builder<InventoryItem>  $query
     * @return Builder<InventoryItem>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('quantity_available', '>', 0);
    }

    /**
     * Quantity not held by pending orders.
     */
    public function getQuantityOnSaleAttribute(): int
    {
        return max(0, $this->quantity_available - $this->quantity_reserved);
    }
}

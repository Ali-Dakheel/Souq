<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $variant_id
 * @property string|null $sku
 * @property array<string, string>|null $product_name
 * @property array<string, mixed>|null $variant_attributes
 * @property int $quantity
 * @property int $price_fils_per_unit
 * @property int $total_fils
 * @property Collection<int, ShipmentItem> $shipmentItems
 * @property-read int $quantity_shipped
 * @property-read int $quantity_to_ship
 */
class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'sku',
        'product_name',
        'variant_attributes',
        'quantity',
        'price_fils_per_unit',
        'total_fils',
    ];

    protected $casts = [
        'product_name' => 'array',
        'variant_attributes' => 'array',
        'quantity' => 'integer',
        'price_fils_per_unit' => 'integer',
        'total_fils' => 'integer',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Variant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    /** @return HasMany<ShipmentItem, $this> */
    public function shipmentItems(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function getQuantityShippedAttribute(): int
    {
        return (int) $this->shipmentItems->sum('quantity_shipped');
    }

    public function getQuantityToShipAttribute(): int
    {
        return $this->quantity - $this->quantity_shipped;
    }
}

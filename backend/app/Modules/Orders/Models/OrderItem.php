<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    public function shipmentItems(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function getQuantityShippedAttribute(): int
    {
        return $this->shipmentItems->sum('quantity_shipped');
    }

    public function getQuantityToShipAttribute(): int
    {
        return $this->quantity - $this->quantity_shipped;
    }
}

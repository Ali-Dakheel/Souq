<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $shipping_zone_id
 * @property string|null $carrier
 * @property string $name_en
 * @property string $name_ar
 * @property string $type
 * @property int|null $rate_fils
 * @property int|null $free_threshold_fils
 * @property bool $is_active
 * @property int $sort_order
 * @property array<string, mixed>|null $config
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ShippingZone $zone
 */
class ShippingMethod extends Model
{
    protected $fillable = [
        'shipping_zone_id', 'carrier', 'name_en', 'name_ar',
        'type', 'rate_fils', 'free_threshold_fils',
        'is_active', 'sort_order', 'config',
    ];

    protected $casts = [
        'rate_fils' => 'integer',
        'free_threshold_fils' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'config' => 'array',
    ];

    /** @return BelongsTo<ShippingZone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    /** @return HasMany<OrderShipping, $this> */
    public function orderShippings(): HasMany
    {
        return $this->hasMany(OrderShipping::class);
    }
}

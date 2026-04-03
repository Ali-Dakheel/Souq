<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function orderShippings(): HasMany
    {
        return $this->hasMany(OrderShipping::class);
    }
}

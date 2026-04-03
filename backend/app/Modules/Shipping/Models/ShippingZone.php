<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    protected $fillable = ['name_en', 'name_ar', 'countries', 'regions', 'is_active', 'sort_order'];

    protected $casts = [
        'countries' => 'array',
        'regions' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class)->orderBy('sort_order');
    }
}

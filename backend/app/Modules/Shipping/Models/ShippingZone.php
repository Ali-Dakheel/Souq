<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name_en
 * @property string $name_ar
 * @property array<int, string>|null $countries
 * @property array<int, string>|null $regions
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ShippingZone extends Model
{
    protected $fillable = ['name_en', 'name_ar', 'countries', 'regions', 'is_active', 'sort_order'];

    protected $casts = [
        'countries' => 'array',
        'regions' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return HasMany<ShippingMethod, $this> */
    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class)->orderBy('sort_order');
    }
}

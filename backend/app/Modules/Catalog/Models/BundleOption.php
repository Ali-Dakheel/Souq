<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $product_id
 * @property string $name_en
 * @property string $name_ar
 * @property bool $required
 * @property int $sort_order
 */
class BundleOption extends Model
{
    protected $fillable = [
        'product_id',
        'name_en',
        'name_ar',
        'required',
        'sort_order',
    ];

    protected $casts = [
        'required' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<BundleOptionProduct, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(BundleOptionProduct::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('orderBySort', function (Builder $query) {
            $query->orderBy('sort_order');
        });
    }
}

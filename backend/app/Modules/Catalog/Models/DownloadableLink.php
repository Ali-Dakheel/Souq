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
 * @property string $file_path
 * @property int $downloads_allowed
 * @property int $sort_order
 */
class DownloadableLink extends Model
{
    protected $fillable = [
        'product_id',
        'name_en',
        'name_ar',
        'file_path',
        'downloads_allowed',
        'sort_order',
    ];

    protected $casts = [
        'downloads_allowed' => 'integer',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<DownloadableLinkPurchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(DownloadableLinkPurchase::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('orderBySort', function (Builder $query) {
            $query->orderBy('sort_order');
        });
    }
}

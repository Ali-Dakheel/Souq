<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

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

<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

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

<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerGroup extends Model
{
    protected $fillable = ['name_en', 'name_ar', 'slug', 'description', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function variantGroupPrices(): HasMany
    {
        return $this->hasMany(VariantGroupPrice::class);
    }

    public function productVisibilities(): HasMany
    {
        return $this->hasMany(ProductGroupVisibility::class);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }
}

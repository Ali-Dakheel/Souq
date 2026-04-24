<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name_en
 * @property string $name_ar
 * @property string $slug
 * @property string|null $description
 * @property bool $is_default
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CustomerGroup extends Model
{
    protected $fillable = ['name_en', 'name_ar', 'slug', 'description', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<VariantGroupPrice, $this> */
    public function variantGroupPrices(): HasMany
    {
        return $this->hasMany(VariantGroupPrice::class);
    }

    /** @return HasMany<ProductGroupVisibility, $this> */
    public function productVisibilities(): HasMany
    {
        return $this->hasMany(ProductGroupVisibility::class);
    }

    /**
     * @param  Builder<CustomerGroup>  $query
     * @return Builder<CustomerGroup>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }
}

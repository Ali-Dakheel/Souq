<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property array<string, string> $name
 * @property string $slug
 * @property string|null $attribute_type
 * @property string|null $input_type
 * @property bool $is_filterable
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Attribute extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'attribute_type',
        'input_type',
        'is_filterable',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'is_filterable' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return HasMany<AttributeValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('sort_order');
    }

    /**
     * @param  Builder<Attribute>  $query
     * @return Builder<Attribute>
     */
    public function scopeFilterable(Builder $query): Builder
    {
        return $query->where('is_filterable', true);
    }
}

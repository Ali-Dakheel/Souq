<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $attribute_id
 * @property array<string, string> $name
 * @property string $value_key
 * @property string|null $display_value
 * @property int $sort_order
 */
class AttributeValue extends Model
{
    protected $table = 'attribute_values';

    protected $fillable = [
        'attribute_id',
        'name',
        'value_key',
        'display_value',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<Attribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}

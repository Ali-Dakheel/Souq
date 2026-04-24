<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $category_id
 * @property string $image_url
 * @property array<string, string>|null $alt_text
 */
class CategoryImage extends Model
{
    protected $table = 'category_images';

    protected $fillable = [
        'category_id',
        'image_url',
        'alt_text',
    ];

    protected $casts = [
        'alt_text' => 'array',
    ];

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use App\Modules\Catalog\Models\Variant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $wishlist_id
 * @property int $variant_id
 * @property Carbon|null $added_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Variant $variant
 */
class WishlistItem extends Model
{
    protected $fillable = [
        'wishlist_id',
        'variant_id',
        'added_at',
    ];

    protected $casts = [
        'added_at' => 'datetime',
    ];

    /** @return BelongsTo<Wishlist, $this> */
    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    /** @return BelongsTo<Variant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }
}

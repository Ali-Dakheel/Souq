<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $share_token
 * @property bool $is_public
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Wishlist extends Model
{
    protected $fillable = [
        'user_id',
        'share_token',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<WishlistItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }
}

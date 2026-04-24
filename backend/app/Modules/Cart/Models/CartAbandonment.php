<?php

declare(strict_types=1);

namespace App\Modules\Cart\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $cart_id
 * @property int|null $user_id
 * @property string|null $session_id
 * @property int $cart_total_fils
 * @property int $item_count
 * @property Carbon|null $abandoned_at
 * @property Carbon|null $recovered_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CartAbandonment extends Model
{
    protected $fillable = [
        'cart_id',
        'user_id',
        'session_id',
        'cart_total_fils',
        'item_count',
        'abandoned_at',
        'recovered_at',
    ];

    protected $casts = [
        'cart_total_fils' => 'integer',
        'item_count' => 'integer',
        'abandoned_at' => 'datetime',
        'recovered_at' => 'datetime',
    ];

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markRecovered(): void
    {
        $this->update(['recovered_at' => now()]);
    }
}

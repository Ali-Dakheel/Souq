<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $points_balance
 * @property int $lifetime_points_earned
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class LoyaltyAccount extends Model
{
    protected $fillable = [
        'user_id',
        'points_balance',
        'lifetime_points_earned',
    ];

    protected $casts = [
        'points_balance' => 'integer',
        'lifetime_points_earned' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<LoyaltyTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }
}

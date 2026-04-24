<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name_en
 * @property string $name_ar
 * @property string|null $description
 * @property bool $is_active
 * @property int $priority
 * @property bool $is_exclusive
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property int|null $max_uses_global
 * @property int|null $max_uses_per_user
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PromotionRule extends Model
{
    protected $fillable = [
        'name_en',
        'name_ar',
        'description',
        'is_active',
        'priority',
        'is_exclusive',
        'starts_at',
        'expires_at',
        'max_uses_global',
        'max_uses_per_user',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_exclusive' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'priority' => 'integer',
        'max_uses_global' => 'integer',
        'max_uses_per_user' => 'integer',
    ];

    /** @return HasMany<PromotionCondition, $this> */
    public function conditions(): HasMany
    {
        return $this->hasMany(PromotionCondition::class);
    }

    /** @return HasMany<PromotionAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(PromotionAction::class);
    }

    /** @return HasMany<PromotionUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
    }

    /**
     * @param  Builder<PromotionRule>  $query
     * @return Builder<PromotionRule>
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', $now);
            });
    }

    public function isExhaustedGlobally(): bool
    {
        if ($this->max_uses_global === null) {
            return false;
        }

        return $this->usages()->count() >= $this->max_uses_global;
    }

    public function isExhaustedForUser(int $userId): bool
    {
        if ($this->max_uses_per_user === null) {
            return false;
        }

        return $this->usages()
            ->where('user_id', $userId)
            ->count() >= $this->max_uses_per_user;
    }
}

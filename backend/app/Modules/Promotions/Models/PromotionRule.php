<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function conditions(): HasMany
    {
        return $this->hasMany(PromotionCondition::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(PromotionAction::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
    }

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
